<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\Article;
use App\Services\AiProviderRouter;
use App\Services\AllProvidersFailedException;
use App\Services\RateLimitRetryException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateProjectAiInsightJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public function __construct(public int $projectId)
    {
    }

    public function handle(): void
    {
        $project = Project::find($this->projectId);
        if (!$project) {
            return;
        }

        // Kumpulkan data statistik terbaru
        $articles = Article::query()
            ->join('ai_analysis_results as ai', 'articles.id', '=', 'ai.article_id')
            ->where('ai.analysis_status', 'success')
            ->where(function ($contentQuery) use ($project) {
                $matchKeywords = array_values(array_unique(array_filter(array_merge(
                    $project->scrapeKeywordVariants(),
                    $project->scrapeContextKeywordVariants()
                ))));

                foreach ($matchKeywords as $index => $keyword) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $contentQuery->{$method}(function ($inner) use ($keyword) {
                        $inner->where('title', 'ilike', '%' . $keyword . '%')
                            ->orWhere('content', 'ilike', '%' . $keyword . '%')
                            ->orWhere('excerpt', 'ilike', '%' . $keyword . '%')
                            ->orWhere('ai.summary', 'ilike', '%' . $keyword . '%');
                    });
                }
            })
            ->select('articles.title', 'articles.content', 'articles.excerpt', 'articles.source_name', 'articles.published_at', 'ai.sentiment', 'ai.summary')
            ->latest('articles.published_at')
            ->limit(100)
            ->get();

        $total = $articles->count();

        $pos = $articles->where('sentiment', 'positive')->count();
        $neu = $articles->where('sentiment', 'neutral')->count();
        $neg = $articles->where('sentiment', 'negative')->count();

        // Siapkan Prompt
        $statsText = "Total Penyebutan/Artikel Dianalisis: {$total}\nSentimen Positif: {$pos}\nSentimen Netral: {$neu}\nSentimen Negatif: {$neg}\n\nBeberapa judul, sumber, dan ringkasan terbaru:\n";
        foreach ($articles->take(15) as $art) {
            $summarySnippet = trim((string) ($art->summary ?? ''));
            $summarySnippet = $summarySnippet !== '' ? ' | Ringkasan: ' . \Illuminate\Support\Str::limit($summarySnippet, 180) : '';
            $sourceName = trim((string) ($art->source_name ?? ''));
            $sourceSnippet = $sourceName !== '' ? " | Sumber: {$sourceName}" : '';
            $statsText .= "- [{$art->sentiment}] {$art->title}{$sourceSnippet}{$summarySnippet}\n";
        }

        $prompt = "Anda adalah analis isu berita dan reputasi media. Tulis kesimpulan dan rekomendasi untuk proyek / tokoh / brand bernama '{$project->name}' berdasarkan data berikut:\n\n{$statsText}\n\nFokuskan jawaban pada isu yang paling menonjol, arah pemberitaan, framing media, kecenderungan sentimen, potensi risiko reputasi, dan langkah respons yang perlu disiapkan. Hindari bahasa umum seperti 'kinerja baik' tanpa menyebut isu yang nyata di data.\n\nKeluarkan output dalam format JSON murni dengan skema berikut:\n{\n  \"summary\": \"(2 paragraf maksimal, naratif, spesifik ke isu berita paling dominan, siapa/apa yang disebut, bagaimana arah opini media, dan implikasi reputasi. Wajib menyebut angka statistik yang relevan)\",\n  \"recommendations\": [\"(Langkah respons isu 1 yang spesifik)\", \"(Langkah respons isu 2 yang spesifik)\", \"(Langkah respons isu 3 yang spesifik)\"]\n}";

        try {
            $router = app(AiProviderRouter::class);
            $result = $router->execute(
                'Anda harus merespon dengan format JSON murni tanpa markup apapun.',
                $prompt,
                ['response_format' => 'json_object'],
                $this->projectId,
                'project_insight'
            );
            $rawText = (string) ($result['text'] ?? '');
            $decoded = $this->decodeAiJson($rawText);

            if (! $decoded) {
                $retryPrompt = $this->buildValidationRetryPrompt($prompt, $rawText);
                $retryResult = $router->execute(
                    'Anda harus merespon dengan format JSON murni tanpa markup apapun.',
                    $retryPrompt,
                    ['response_format' => 'json_object'],
                    $this->projectId,
                    'project_insight'
                );
                $rawText = (string) ($retryResult['text'] ?? '');
                $decoded = $this->decodeAiJson($rawText);
            }

            if (! $decoded) {
                Log::error("Failed to decode AI project insight JSON: " . $rawText);
                return;
            }

            $normalized = $this->normalizeInsightResult($decoded);
            if ($normalized === null) {
                Log::error("AI project insight JSON missing required fields: " . $rawText);
                return;
            }

            $project->forceFill([
                'ai_insight_summary' => $normalized['summary'],
                'ai_insight_recommendations' => $normalized['recommendations'],
                'ai_insight_updated_at' => now(),
            ])->save();
        } catch (RateLimitRetryException $e) {
            Log::warning("Project AI insight deferred by rate limit for project {$this->projectId}.", [
                'delay_seconds' => $e->delaySeconds,
            ]);
            return;
        } catch (AllProvidersFailedException $e) {
            Log::warning("All AI providers failed for project insight {$this->projectId}.");
            return;
        } catch (\Exception $e) {
            Log::error("Error generating AI project summary: " . $e->getMessage());
        }
    }

    protected function decodeAiJson(string $rawText): ?array
    {
        $trimmed = trim($rawText);
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    protected function normalizeInsightResult(array $decoded): ?array
    {
        $summary = trim((string) ($decoded['summary'] ?? $decoded['kesimpulan'] ?? $decoded['conclusion'] ?? $decoded['insight_summary'] ?? $decoded['text'] ?? ''));
        $recommendations = $decoded['recommendations']
            ?? $decoded['rekomendasi']
            ?? $decoded['recommendation']
            ?? $decoded['action_items']
            ?? $decoded['insight_recommendations']
            ?? [];

        if ($summary === '') {
            return null;
        }

        if (is_string($recommendations)) {
            $maybeDecoded = json_decode($recommendations, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($maybeDecoded)) {
                $recommendations = $maybeDecoded;
            } else {
                $recommendations = preg_split('/\r\n|\r|\n|•|- /', $recommendations) ?: [];
            }
        }

        if (! is_array($recommendations)) {
            return null;
        }

        $recommendations = array_values(array_filter(array_map(function ($item) {
            if (is_array($item)) {
                $item = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return trim((string) $item);
        }, $recommendations)));

        if ($recommendations === []) {
            return null;
        }

        return [
            'summary' => $summary,
            'recommendations' => $recommendations,
        ];
    }

    protected function buildValidationRetryPrompt(string $originalPrompt, string $rawText): string
    {
        return "Output sebelumnya belum valid untuk disimpan. Ubah hasil berikut menjadi JSON murni yang hanya berisi dua key: summary dan recommendations.\n\n"
            . "Aturan:\n"
            . "- summary harus berupa narasi isu berita yang spesifik, fokus ke pemberitaan, framing media, dan dampak reputasi.\n"
            . "- recommendations harus berupa array berisi minimal 3 butir tindakan respons isu yang spesifik.\n"
            . "- Jangan tambahkan markdown, penjelasan, atau teks di luar JSON.\n\n"
            . "Prompt asli:\n{$originalPrompt}\n\n"
            . "Output sebelumnya:\n{$rawText}";
    }
}
