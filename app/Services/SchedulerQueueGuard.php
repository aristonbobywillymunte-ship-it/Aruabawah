<?php

namespace App\Services;

use App\Models\ApifyDispatchState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class SchedulerQueueGuard
{
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

        $activeState = ApifyDispatchState::query()
            ->whereIn('status', ['queued', 'processing'])
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
