<?php

namespace App\Jobs;

use App\Models\AiPromptTemplate;
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
use Illuminate\Support\Str;

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

        $template = AiPromptTemplate::resolveActiveDefaultForSourceType('Laporan AI Media Intelligence', 'report')
            ?? AiPromptTemplate::resolvePreferredActiveForSourceType('report');

        if (! $template) {
            Log::error("Report AI prompt template not found for project insight {$this->projectId}.");
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
            ->limit(250)
            ->get();

        $articles = $articles->filter(function ($article) use ($project) {
            $content = implode("\n", array_filter([
                trim((string) ($article->title ?? '')),
                trim((string) ($article->content ?? '')),
                trim((string) ($article->excerpt ?? '')),
                trim((string) ($article->summary ?? '')),
            ]));

            return ! $this->shouldSkipGovernorArticleMatch($project, $content);
        })->values()->take(100);

        $total = $articles->count();

        $pos = $articles->where('sentiment', 'positive')->count();
        $neu = $articles->where('sentiment', 'neutral')->count();
        $neg = $articles->where('sentiment', 'negative')->count();

        $topSources = $articles
            ->groupBy(fn ($article) => $this->normalizeSourceLabel((string) ($article->source_name ?? '')))
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->take(5)
            ->map(fn (int $count, string $source) => "{$source} ({$count})")
            ->implode(', ');

        $topTopics = $this->deriveTopTopics($articles);
        $topArticles = $this->buildTopArticlesContext($articles->take(15));
        $viralMeta = $this->buildViralContext($articles);

        $renderedPrompt = $this->renderTemplatePrompt($template, [
            'project_name' => (string) $project->name,
            'period_start' => $articles->min('published_at')
                ? \Carbon\Carbon::parse($articles->min('published_at'))->format('Y-m-d')
                : now()->toDateString(),
            'period_end' => $articles->max('published_at')
                ? \Carbon\Carbon::parse($articles->max('published_at'))->format('Y-m-d')
                : now()->toDateString(),
            'total_mentions' => (string) $total,
            'positive_count' => (string) $pos,
            'neutral_count' => (string) $neu,
            'negative_count' => (string) $neg,
            'positive_pct' => $total > 0 ? (string) round(($pos / $total) * 100) : '0',
            'neutral_pct' => $total > 0 ? (string) round(($neu / $total) * 100) : '0',
            'negative_pct' => $total > 0 ? (string) round(($neg / $total) * 100) : '0',
            'top_sources' => $topSources !== '' ? $topSources : '-',
            'top_topics' => $topTopics !== '' ? $topTopics : '-',
            'top_articles' => $topArticles !== '' ? $topArticles : '-',
            'viral_status' => $viralMeta['viral_status'],
            'viral_desc' => $viralMeta['viral_desc'],
            'viral_recent_7d' => (string) $viralMeta['recent_7d'],
            'viral_basis' => $viralMeta['viral_basis'],
        ]);

        $renderedPrompt .= "\n\nDATA KONDISI VIRAL TAMBAHAN:\n"
            . "- Status Viral: {$viralMeta['viral_status']}\n"
            . "- Penjelasan Viral: {$viralMeta['viral_desc']}\n"
            . "- Penyebutan 7 Hari Terakhir: {$viralMeta['recent_7d']}\n"
            . "- Dasar Penilaian Viral: {$viralMeta['viral_basis']}\n"
            . "\nATURAN TAMBAHAN:\n"
            . "7. Sertakan penilaian khusus tentang kondisi viral ke dalam key viral_condition.\n"
            . "8. Penilaian viral harus menyebut status, alasan, dan implikasi reputasinya secara singkat tapi jelas.";

        try {
            $router = app(AiProviderRouter::class);
            $result = $router->execute(
                trim((string) $template->system_prompt),
                trim($renderedPrompt),
                ['response_format' => 'json_object'],
                $this->projectId,
                'project_insight'
            );
            $rawText = (string) ($result['text'] ?? '');
            $decoded = $this->decodeAiJson($rawText);

            if (! $decoded) {
                $retryPrompt = $this->buildValidationRetryPrompt(trim((string) $template->system_prompt), $rawText, trim($renderedPrompt));
                $retryResult = $router->execute(
                    trim((string) $template->system_prompt),
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
                'ai_insight_viral_summary' => $normalized['viral_condition'],
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
        $viralCondition = trim((string) ($decoded['viral_condition'] ?? $decoded['viral'] ?? $decoded['viral_summary'] ?? ''));

        if ($summary === '' || $viralCondition === '') {
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
            'viral_condition' => $viralCondition,
        ];
    }

    protected function buildValidationRetryPrompt(string $systemPrompt, string $rawText, string $originalPrompt): string
    {
        return "Output sebelumnya belum valid untuk disimpan. Ubah hasil berikut menjadi JSON murni yang hanya berisi tiga key: summary, recommendations, dan viral_condition.\n\n"
            . "Aturan:\n"
            . "- summary harus berupa narasi isu berita yang spesifik, fokus ke pemberitaan, framing media, dan dampak reputasi.\n"
            . "- recommendations harus berupa array berisi minimal 3 butir tindakan respons isu yang spesifik.\n"
            . "- viral_condition harus berupa satu paragraf khusus yang menilai kondisi viral secara eksplisit.\n"
            . "- Jangan tambahkan markdown, penjelasan, atau teks di luar JSON.\n\n"
            . "System prompt:\n{$systemPrompt}\n\n"
            . "Prompt asli:\n{$originalPrompt}\n\n"
            . "Output sebelumnya:\n{$rawText}";
    }

    protected function buildViralContext($articles): array
    {
        $periodEnd = $articles->max('published_at')
            ? \Carbon\Carbon::parse($articles->max('published_at'))->endOfDay()
            : now()->endOfDay();
        $cutoff = $periodEnd->copy()->subDays(7);

        $recent7d = $articles->filter(function ($article) use ($cutoff, $periodEnd) {
            if (empty($article->published_at)) {
                return false;
            }

            $publishedAt = \Carbon\Carbon::parse($article->published_at);

            return $publishedAt->betweenIncluded($cutoff, $periodEnd);
        })->count();

        if ($recent7d >= 100) {
            return [
                'viral_status' => 'Sangat Viral',
                'viral_desc' => 'Lonjakan percakapan sangat tinggi',
                'recent_7d' => $recent7d,
                'viral_basis' => 'Penyebutan 7 hari terakhir berada di atas ambang sangat viral.',
            ];
        }

        if ($recent7d >= 30) {
            return [
                'viral_status' => 'Mulai Viral',
                'viral_desc' => 'Ada peningkatan atensi',
                'recent_7d' => $recent7d,
                'viral_basis' => 'Penyebutan 7 hari terakhir menunjukkan kenaikan atensi yang konsisten.',
            ];
        }

        return [
            'viral_status' => 'Normal',
            'viral_desc' => 'Volume berita stabil',
            'recent_7d' => $recent7d,
            'viral_basis' => 'Penyebutan 7 hari terakhir belum melewati ambang viral.',
        ];
    }

    protected function renderTemplatePrompt(AiPromptTemplate $template, array $context): string
    {
        $replacements = [
            '{project_name}' => trim((string) ($context['project_name'] ?? '')),
            '{period_start}' => trim((string) ($context['period_start'] ?? '')),
            '{period_end}' => trim((string) ($context['period_end'] ?? '')),
            '{total_mentions}' => trim((string) ($context['total_mentions'] ?? '0')),
            '{positive_count}' => trim((string) ($context['positive_count'] ?? '0')),
            '{neutral_count}' => trim((string) ($context['neutral_count'] ?? '0')),
            '{negative_count}' => trim((string) ($context['negative_count'] ?? '0')),
            '{positive_pct}' => trim((string) ($context['positive_pct'] ?? '0')),
            '{neutral_pct}' => trim((string) ($context['neutral_pct'] ?? '0')),
            '{negative_pct}' => trim((string) ($context['negative_pct'] ?? '0')),
            '{top_sources}' => trim((string) ($context['top_sources'] ?? '-')),
            '{top_topics}' => trim((string) ($context['top_topics'] ?? '-')),
            '{top_articles}' => trim((string) ($context['top_articles'] ?? '-')),
            '{viral_status}' => trim((string) ($context['viral_status'] ?? 'Normal')),
            '{viral_desc}' => trim((string) ($context['viral_desc'] ?? 'Volume berita stabil')),
            '{viral_recent_7d}' => trim((string) ($context['viral_recent_7d'] ?? '0')),
            '{viral_basis}' => trim((string) ($context['viral_basis'] ?? '')),
        ];

        return strtr((string) $template->user_prompt_template, $replacements);
    }

    protected function buildTopArticlesContext($articles): string
    {
        $lines = [];

        foreach ($articles as $article) {
            $title = trim((string) ($article->title ?? ''));
            $source = trim((string) ($article->source_name ?? ''));
            $sentiment = trim((string) ($article->sentiment ?? ''));
            $summary = trim((string) ($article->summary ?? ''));
            $publishedAt = $article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('Y-m-d H:i') : '-';

            $line = "- {$title}";
            $metaParts = array_filter([
                $source !== '' ? "Sumber: {$source}" : null,
                $sentiment !== '' ? "Sentimen: {$sentiment}" : null,
                $publishedAt !== '-' ? "Waktu: {$publishedAt}" : null,
                $summary !== '' ? 'Ringkasan: ' . Str::limit($summary, 180) : null,
            ]);

            if ($metaParts !== []) {
                $line .= ' | ' . implode(' | ', $metaParts);
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    protected function deriveTopTopics($articles): string
    {
        $stopWords = ['dan', 'di', 'ke', 'dari', 'yang', 'untuk', 'dengan', 'ini', 'itu', 'pada', 'dalam', 'adalah', 'akan', 'juga', 'sudah', 'ada', 'bisa', 'atau', 'tidak', 'lebih', 'saat', 'oleh', 'para', 'telah', 'agar', 'atas', 'jika', 'karena', 'maka', 'namun', 'pun', 'serta', 'tentang', 'setelah', 'antara', 'hingga', 'ia', 'kami', 'kita', 'mereka', 'anda', 'bagi', 'dua', 'tiga', 'lain', 'hal', 'tahun', 'baru', 'terkait', 'pihak', 'sebuah', 'satu', 'tersebut', 'the', 'a', 'an', 'is', 'in', 'of', 'and', 'to', 'for', 'masa', 'jalan', 'jadi', 'pemerintah'];
        $wordFreq = [];

        foreach ($articles as $article) {
            $title = strtolower(preg_replace('/[^a-zA-Z0-9\s]/u', ' ', html_entity_decode(strip_tags((string) ($article->title ?? '')), ENT_QUOTES, 'UTF-8')));
            $words = array_filter(explode(' ', $title), function ($word) use ($stopWords) {
                $word = trim((string) $word);
                return mb_strlen($word) > 3 && ! in_array($word, $stopWords, true);
            });

            foreach ($words as $word) {
                $wordFreq[$word] = ($wordFreq[$word] ?? 0) + 1;
            }
        }

        arsort($wordFreq);

        return collect(array_slice($wordFreq, 0, 10, true))
            ->map(fn (int $count, string $word) => "{$word} ({$count})")
            ->implode(', ');
    }

    protected function normalizeSourceLabel(string $source): string
    {
        $source = trim($source);

        return $source !== '' ? $source : 'Sumber tidak diketahui';
    }

    /**
     * Prevent governor projects from absorbing wagub-only articles just because
     * they share a broad regional keyword such as "Kalimantan Timur".
     */
    protected function shouldSkipGovernorArticleMatch(Project $project, string $content): bool
    {
        $projectName = Str::lower($project->name ?? '');
        $contentLower = Str::lower($content);

        if (! Str::contains($projectName, 'gubernur')) {
            return false;
        }

        $hasWagubSignal = Str::contains($contentLower, [
            'wakil gubernur',
            'wagub',
            'seno aji',
        ]);

        if (! $hasWagubSignal) {
            return false;
        }

        $hasStrongGovernorSignal = preg_match('/(?<!wakil\s)gubernur\s+kaltim/iu', $contentLower) === 1
            || preg_match('/(?<!wakil\s)gubernur\s+kalimantan\s+timur/iu', $contentLower) === 1
            || Str::contains($contentLower, [
                'rudy mas',
                'rudy mas\'ud',
                'rudy mas’ud',
            ]);

        return ! $hasStrongGovernorSignal;
    }
}
