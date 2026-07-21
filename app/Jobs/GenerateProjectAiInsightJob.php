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
            ->select('articles.title', 'ai.sentiment', 'ai.summary')
            ->latest('articles.published_at')
            ->limit(100)
            ->get();

        $total = $articles->count();
        if ($total === 0) {
            $project->update([
                'ai_insight_summary' => 'Belum ada data artikel yang dianalsis AI untuk menyusun ringkasan.',
                'ai_insight_recommendations' => [],
                'ai_insight_updated_at' => now(),
            ]);
            return;
        }

        $pos = $articles->where('sentiment', 'positive')->count();
        $neu = $articles->where('sentiment', 'neutral')->count();
        $neg = $articles->where('sentiment', 'negative')->count();

        // Siapkan Prompt
        $statsText = "Total Artikel Dianalisis: {$total}\nSentimen Positif: {$pos}\nSentimen Netral: {$neu}\nSentimen Negatif: {$neg}\n\nBeberapa judul dan ringkasan terbaru:\n";
        foreach ($articles->take(15) as $art) {
            $statsText .= "- [{$art->sentiment}] {$art->title}\n";
        }

        $prompt = "Anda adalah analis media cerdas (Media Intelligence). Berikan ringkasan reputasi proyek / tokoh / brand bernama '{$project->name}' berdasarkan data berikut:\n\n{$statsText}\n\nKeluarkan output dalam format JSON murni dengan skema berikut:\n{\n  \"summary\": \"(Paragraf narasi kondisi reputasi media, sebutkan angka statistiknya juga. Maksimal 2 paragraf)\",\n  \"recommendations\": [\"(Rekomendasi tindakan PR 1)\", \"(Rekomendasi tindakan PR 2)\", \"(Rekomendasi tindakan PR 3)\"]\n}";

        try {
            $router = app(AiProviderRouter::class);
            $result = $router->execute(
                'Anda harus merespon dengan format JSON murni tanpa markup apapun.',
                $prompt,
                ['response_format' => 'json_object'],
                $this->projectId,
                'project_insight'
            );
            $rawText = $result['text'];
            $rawText = str_replace(['```json', '```'], '', $rawText);
            $decoded = json_decode(trim($rawText), true);

            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['summary']) && isset($decoded['recommendations'])) {
                $project->update([
                    'ai_insight_summary' => $decoded['summary'],
                    'ai_insight_recommendations' => $decoded['recommendations'],
                    'ai_insight_updated_at' => now(),
                ]);
            } else {
                Log::error("Failed to decode AI project summary JSON: " . $rawText);
            }
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
}
