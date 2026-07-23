<?php

namespace App\Services;

use App\Models\ApifyDispatchState;
use App\Models\AiProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class SchedulerQueueGuard
{
    private const STALE_APIFY_STATE_MINUTES = 45;
    private const AI_WORKER_ACTIVE_CACHE_KEY = 'ai-worker:active';
    private const AI_WORKER_ACTIVE_STALE_SECONDS = 30;

    public function aiBusyReason(): ?string
    {
        if ($this->aiWorkerRecentlyActive()) {
            return 'Worker AI masih aktif memproses job sebelumnya.';
        }

        if ($this->queueHasJobs('redis-ai', ['ai-analysis', 'ai-backfill'])) {
            return 'Masih ada job AI menunggu di antrean redis-ai.';
        }

        $processingCount = \App\Models\AiAnalysisDispatchState::query()
            ->where('status', 'processing')
            ->count();

        if ($processingCount > 0) {
            return 'Masih ada job AI yang sedang diproses worker.';
        }

        $activeProviderCount = AiProvider::query()
            ->where('is_active', true)
            ->count();

        if ($activeProviderCount <= 0) {
            return 'Tidak ada provider AI aktif.';
        }

        $availableProviderCount = app(AiProviderRouter::class)
            ->getAvailableProviders('article_analysis')
            ->count();

        if ($availableProviderCount <= 0) {
            $nextReadyProvider = AiProvider::query()
                ->where('is_active', true)
                ->whereNotNull('cooldown_until')
                ->orderBy('cooldown_until')
                ->first();

            if ($nextReadyProvider?->cooldown_until) {
                return "Semua provider AI aktif sedang cooldown. Provider terdekat siap lagi pada {$nextReadyProvider->cooldown_until}.";
            }

            return 'Tidak ada provider AI aktif yang siap dipakai.';
        }

        return null;
    }

    public function aiWorkerRecentlyActive(): bool
    {
        try {
            $lastActivity = Cache::get(self::AI_WORKER_ACTIVE_CACHE_KEY);
            if (! is_numeric($lastActivity)) {
                return false;
            }

            return ((int) $lastActivity) >= now()->subSeconds(self::AI_WORKER_ACTIVE_STALE_SECONDS)->timestamp;
        } catch (\Throwable $e) {
            Log::warning('[SchedulerGuard] Gagal membaca status aktivitas worker AI.', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function aiIsIdle(): bool
    {
        return $this->aiBusyReason() === null;
    }

    public function apifyIsIdle(): bool
    {
        return $this->apifyBusyReason() === null;
    }

    public function newsIsIdle(): bool
    {
        return $this->newsBusyReason() === null;
    }

    public function apifyBusyReason(): ?string
    {
        if ($this->queueHasJobs('redis', ['apify'])) {
            return 'Masih ada job Apify menunggu di antrean.';
        }

        if ($this->queueHasJobs('redis-ai', ['apify'])) {
            return 'Masih ada job Apify menunggu di antrean redis-ai.';
        }

        $activeThreshold = now()->subMinutes(self::STALE_APIFY_STATE_MINUTES);

        $activeState = ApifyDispatchState::query()
            ->whereIn('status', ['queued', 'processing'])
            ->where(function ($query) use ($activeThreshold) {
                $query->where(function ($queued) use ($activeThreshold) {
                    $queued->where('status', 'queued')
                        ->where('queued_at', '>=', $activeThreshold);
                })->orWhere(function ($processing) use ($activeThreshold) {
                    $processing->where('status', 'processing')
                        ->where('started_at', '>=', $activeThreshold);
                })->orWhere(function ($fallback) use ($activeThreshold) {
                    $fallback->whereIn('status', ['queued', 'processing'])
                        ->where('updated_at', '>=', $activeThreshold);
                });
            })
            ->orderByDesc('updated_at')
            ->first();

        if ($activeState) {
            return "Masih ada proses Apify aktif: {$activeState->platform} project #{$activeState->project_id} status {$activeState->status}.";
        }

        $retryState = ApifyDispatchState::query()
            ->where('status', 'retry_wait')
            ->where(function ($query) {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '>', now());
            })
            ->orderBy('next_retry_at')
            ->first();

        if ($retryState) {
            $retryAt = $retryState->next_retry_at
                ? $retryState->next_retry_at->format('H:i')
                : 'waktu belum ditentukan';

            return "Apify sedang menunggu pemulihan otomatis sampai {$retryAt}.";
        }

        return null;
    }

    public function newsBusyReason(): ?string
    {
        if ($this->queueHasJobs('redis', ['scraping', 'news'])) {
            return 'Masih ada job portal menunggu di antrean.';
        }

        if (Cache::has('news:run-active')) {
            return 'Scan portal sebelumnya masih berjalan.';
        }

        return null;
    }

    public function logSkip(string $area, string $reason, array $context = []): void
    {
        $channel = $area === 'apify' ? 'social_media' : 'portal_manual';

        Log::channel($channel)->info('[Scheduler] Jadwal dilewati karena proses sebelumnya belum selesai.', array_merge([
            'area' => $area,
            'reason' => $reason,
        ], $context));
    }

    private function queueHasJobs(string $connection, array $queues): bool
    {
        try {
            $queue = Queue::connection($connection);

            foreach ($queues as $name) {
                if ($queue->size($name) > 0) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[SchedulerGuard] Gagal membaca ukuran antrean.', [
                'connection' => $connection,
                'queues' => $queues,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    private function processIsRunning(string $needle): bool
    {
        if (! function_exists('shell_exec')) {
            return false;
        }

        try {
            $output = (string) shell_exec('ps aux 2>/dev/null');
        } catch (\Throwable) {
            return false;
        }

        foreach (explode("\n", $output) as $line) {
            if (str_contains($line, $needle) && ! str_contains($line, 'grep')) {
                return true;
            }
        }

        return false;
    }
}
