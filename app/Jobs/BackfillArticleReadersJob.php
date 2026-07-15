<?php

namespace App\Jobs;

use App\Models\AiAnalysisResult;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\AiProviderRouter;
use App\Services\AllProvidersFailedException;
use App\Services\RateLimitRetryException;

class BackfillArticleReadersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $payload;

    public $tries = 5;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
        $this->onConnection('redis-ai');
        $this->onQueue('ai-backfill');
    }

    public function handle(): void
    {
        $articleId = $this->payload['id'] ?? null;
        $projectId = $this->payload['project_id'] ?? null;
        $title = $this->payload['title'] ?? '';
        $content = $this->payload['content'] ?? '';
        $sourceName = $this->payload['source_name'] ?? '';

        if (!$articleId) {
            Log::warning('[Backfill] Article ID is missing.');
            return;
        }

        // Pastikan row exist dan project_estimated_readers masih null
        $existing = DB::table('ai_analysis_results')
            ->where('article_id', $articleId)
            ->where('analysis_status', 'success')
            ->where('reach_method', 'ai_reader_estimate_v1')
            ->first();

        if (!$existing) {
            Log::info("[Backfill] Article {$articleId} tidak memiliki ai_analysis_results success, lewati.");
            return;
        }

        if (!is_null($existing->project_estimated_readers)) {
            if (!is_null($existing->project_reach_score) && !is_null($existing->project_reach_level)) {
                Log::info("[Backfill] Article {$articleId} sudah memiliki reach dan score resmi, lewati.");
                return;
            }

            $this->persistOfficialReachScoreFromReaders($existing->id, (int) $existing->project_estimated_readers);
            Log::info("[Backfill] Article {$articleId} sudah punya reach, skor resmi dihitung ulang dari readers existing.");
            return;
        }

        $project = Project::find($projectId);
        $projectName = $project ? $project->name : 'Unknown';
        $projectDesc = $project ? $project->description : '';

        // Potong konten jika terlalu panjang
        $contentToAnalyze = mb_substr($content, 0, 8000);

        // Prompt khusus hanya untuk estimasi pembaca
        $systemPrompt = "Anda adalah asisten AI yang ahli dalam estimasi lalu lintas berita digital regional dan nasional di Indonesia. Tugas Anda HANYA memperkirakan jumlah pembaca artikel (jangkauan / reach).";
        
        $userPrompt = "Berikan estimasi jumlah pembaca artikel ini.
Faktor yang perlu dipertimbangkan:
- Media lokal/kecil: 10-100 pembaca
- Media regional: 100-1000 pembaca
- Media nasional: 1000+ pembaca

Informasi Artikel:
Sumber: {$sourceName}
Konteks Project (hanya untuk referensi relevansi audiens): {$projectName} - {$projectDesc}
Judul: {$title}

Konten:
{$contentToAnalyze}

Instruksi Output:
Berikan output berupa JSON murni dengan tepat SATU field:
{
    \"project_estimated_readers\": <integer (minimal 1)>
}";

        $router = app(AiProviderRouter::class);
        $options = ['response_format' => 'json_object'];

        try {
            $result = $router->execute($systemPrompt, $userPrompt, $options, $articleId, 'article_readers_backfill');
            $activeProvider = $result['provider'];
            $rawText = $result['text'];
            
            // Bersihkan markdown markdown json
            $rawText = preg_replace('/```json\s*/', '', $rawText);
            $rawText = preg_replace('/```\s*/', '', $rawText);
            
            $json = json_decode(trim($rawText), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("[Backfill] Invalid JSON response untuk Article {$articleId}: " . json_last_error_msg());
                // Jangan fail hard untuk invalid json agar tidak loop jika retry
                return;
            }

            $readers = $json['project_estimated_readers'] ?? null;

            if (!is_int($readers) || $readers < 1) {
                Log::error("[Backfill] Invalid readers value untuk Article {$articleId}: " . json_encode($readers));
                return;
            }

            Log::info('[Backfill] Provider metadata', [
                'article_id' => $articleId,
                'provider_id_used' => $activeProvider?->id,
                'provider_name' => $activeProvider?->name,
                'model_used' => $activeProvider?->model_name,
                'fallback_count' => $result['fallback_count'] ?? 0,
                'last_error_category' => $activeProvider?->last_failure_code,
            ]);

            $scoreData = $this->buildOfficialReachScorePayload($readers);

            DB::table('ai_analysis_results')
                ->where('id', $existing->id)
                ->update(array_merge([
                    'project_estimated_readers' => $readers,
                    'reach_method' => 'ai_reader_estimate_v1',
                    'updated_at' => now(),
                ], $scoreData));

            Log::info("[Backfill] Berhasil update Article {$articleId} dengan reach {$readers}.");

        } catch (RateLimitRetryException $e) {
            if ($this->attempts() >= $this->tries) {
                Log::warning("[Backfill] HTTP 429 dari provider untuk Article {$articleId}. Batas tries habis ({$this->tries}). Menghentikan job secara aman (deferred) untuk dijadwalkan ulang.");
                return;
            }

            // Local rate limit or transient rate limit
            $delay = $e->delaySeconds;
            // Jika delay fix 60 dari classifier (tanpa header Retry-After), terapkan backoff
            if ($delay === 60) {
                $attempts = $this->attempts();
                $delays = [60, 120, 300, 600];
                $baseDelay = $delays[min($attempts - 1, count($delays) - 1)];
                $delay = $baseDelay + rand(1, 15); // Jitter
            }

            Log::info("[Backfill] Transient rate limit hit. Releasing job for {$delay}s.");
            $this->release($delay);
            return;
        } catch (AllProvidersFailedException $e) {
            Log::warning("[Backfill] All AI providers failed untuk Article {$articleId}. Menghentikan job (deferred).");
            return;
        } catch (\Exception $e) {
            $msg = preg_replace('/key=[^&\s]+/', 'key=***', $e->getMessage());
            Log::error("[Backfill] Exception untuk Article {$articleId}: " . $msg);
            // safe exit as deferred
            return;
        }
    }

    protected function persistOfficialReachScoreFromReaders(int $rowId, int $readers): void
    {
        $scoreData = $this->buildOfficialReachScorePayload($readers);

        DB::table('ai_analysis_results')
            ->where('id', $rowId)
            ->update(array_merge([
                'updated_at' => now(),
            ], $scoreData));
    }

    protected function buildOfficialReachScorePayload(int $readers): array
    {
        $score = AiAnalysisResult::officialProjectReachScoreForReaders($readers);
        $level = AiAnalysisResult::officialProjectReachLevelForScore($score);

        return [
            'project_reach_score' => $score,
            'project_reach_level' => $level,
            'project_reach_band' => AiAnalysisResult::officialProjectReachBandForReaders($readers),
        ];
    }


}
