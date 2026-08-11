<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Throwable;

class SystemMaintenanceService
{
    private const SCHEDULER_RESTART_CACHE_KEY = 'scheduler_should_restart';

    private const SCHEDULER_RESTART_TTL_MINUTES = 3;

    private const CONFIRMATION_PHRASE = 'HAPUS ANTREAN';

    public function queueTargets(): array
    {
        return [
            [
                'queue_connection' => 'redis',
                'redis_connection' => (string) config('queue.connections.redis.connection', 'default'),
                'queue_names' => ['default', 'news', 'apify', 'notification'],
            ],
            [
                'queue_connection' => 'redis-ai',
                'redis_connection' => (string) config('queue.connections.redis-ai.connection', config('queue.connections.redis.connection', 'default')),
                'queue_names' => ['ai-analysis', 'ai-backfill'],
            ],
        ];
    }

    public function requiredConfirmationPhrase(): string
    {
        return self::CONFIRMATION_PHRASE;
    }

    public function queueSnapshot(): array
    {
        $snapshot = [];

        foreach ($this->queueTargets() as $group) {
            foreach ($group['queue_names'] as $queue) {
                $snapshot[] = $this->queueCounts($group['queue_connection'], $group['redis_connection'], $queue);
            }
        }

        return $snapshot;
    }

    public function clearPendingQueues(): array
    {
        $results = [];
        $deletedJobs = 0;
        $succeededQueues = 0;
        $failedQueues = 0;

        foreach ($this->queueTargets() as $group) {
            foreach ($group['queue_names'] as $queue) {
                $target = [
                    'queue_connection' => $group['queue_connection'],
                    'redis_connection' => $group['redis_connection'],
                    'queue' => $queue,
                ];

                try {
                    $counts = $this->queueCounts($group['queue_connection'], $group['redis_connection'], $queue);

                    if ($counts['status'] === 'error') {
                        $results[] = $this->queueClearResult($target, 'failed', 0, 0, null, $counts['error']);
                        $failedQueues++;
                        continue;
                    }

                    if ($counts['pending'] === 0 && $counts['delayed'] === 0) {
                        $results[] = $this->queueClearResult($target, 'empty', 0, 0, null, null);
                        $succeededQueues++;
                        continue;
                    }

                    $redis = Redis::connection($group['redis_connection']);
                    $pendingRemoved = 0;
                    $delayedRemoved = 0;

                    if ($counts['pending'] > 0) {
                        $pendingRemoved = (int) $redis->del($this->queueKey($queue));
                    }

                    if ($counts['delayed'] > 0) {
                        $redis->zremrangebyrank($this->queueKey($queue) . ':delayed', 0, -1);
                        $delayedRemoved = $counts['delayed'];
                    }

                    $deletedJobs += $counts['pending'] + $counts['delayed'];
                    $succeededQueues++;
                    $results[] = $this->queueClearResult($target, 'cleared', $pendingRemoved, $delayedRemoved, $counts, null);
                } catch (Throwable $e) {
                    $failedQueues++;
                    $results[] = $this->queueClearResult(
                        $target,
                        'failed',
                        0,
                        0,
                        null,
                        $this->safeErrorMessage($e, 'Gagal membaca atau membersihkan antrean Redis.')
                    );
                }
            }
        }

        return [
            'deleted_jobs' => $deletedJobs,
            'succeeded_queues' => $succeededQueues,
            'failed_queues' => $failedQueues,
            'partial_failure' => $failedQueues > 0,
            'queues' => $results,
        ];
    }

    public function restartWorkers(): array
    {
        try {
            $exitCode = Artisan::call('queue:restart');

            if ((int) $exitCode !== 0) {
                return [
                    'status' => 'error',
                    'exit_code' => $exitCode,
                    'error' => 'Signal restart worker Laravel gagal dikirim.',
                ];
            }

            return [
                'status' => 'ok',
                'exit_code' => $exitCode,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $this->safeErrorMessage($e, 'Gagal mengirim signal restart worker.'),
            ];
        }
    }

    public function requestSchedulerRestart(): array
    {
        try {
            Cache::put(
                self::SCHEDULER_RESTART_CACHE_KEY,
                true,
                now()->addMinutes(self::SCHEDULER_RESTART_TTL_MINUTES)
            );

            return [
                'status' => 'ok',
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $this->safeErrorMessage($e, 'Gagal mengirim signal restart scheduler.'),
            ];
        }
    }

    public function clearApplicationCache(): array
    {
        try {
            $exitCode = Artisan::call('optimize:clear');

            if ((int) $exitCode !== 0) {
                return [
                    'status' => 'error',
                    'exit_code' => $exitCode,
                    'error' => 'Pembersihan cache Laravel gagal.',
                ];
            }

            return [
                'status' => 'ok',
                'exit_code' => $exitCode,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $this->safeErrorMessage($e, 'Gagal membersihkan cache aplikasi.'),
            ];
        }
    }

    public function queueCounts(string $queueConnection, string $redisConnection, string $queue): array
    {
        try {
            $redis = Redis::connection($redisConnection);
            $queueKey = $this->queueKey($queue);

            $pending = (int) $redis->llen($queueKey);
            $delayed = (int) $redis->zcard($queueKey . ':delayed');
            $reserved = (int) $redis->zcard($queueKey . ':reserved');

            return [
                'queue_connection' => $queueConnection,
                'redis_connection' => $redisConnection,
                'queue' => $queue,
                'status' => 'ok',
                'pending' => $pending,
                'delayed' => $delayed,
                'reserved' => $reserved,
                'total' => $pending + $delayed + $reserved,
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'queue_connection' => $queueConnection,
                'redis_connection' => $redisConnection,
                'queue' => $queue,
                'status' => 'error',
                'pending' => null,
                'delayed' => null,
                'reserved' => null,
                'total' => null,
                'error' => $this->safeErrorMessage($e, 'Status antrean Redis tidak tersedia.'),
            ];
        }
    }

    public function schedulerRestartSignalConsumerCount(): int
    {
        return 1;
    }

    public function schedulerRestartSignalKey(): string
    {
        return self::SCHEDULER_RESTART_CACHE_KEY;
    }

    private function queueClearResult(
        array $target,
        string $status,
        int $pendingRemoved,
        int $delayedRemoved,
        ?array $counts,
        ?string $error
    ): array {
        return [
            'queue_connection' => $target['queue_connection'],
            'redis_connection' => $target['redis_connection'],
            'queue' => $target['queue'],
            'status' => $status,
            'pending_removed' => $pendingRemoved,
            'delayed_removed' => $delayedRemoved,
            'pending_before' => $counts['pending'] ?? null,
            'delayed_before' => $counts['delayed'] ?? null,
            'reserved_before' => $counts['reserved'] ?? null,
            'safe_error' => $error,
        ];
    }

    private function safeErrorMessage(Throwable $e, string $fallback): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return $fallback;
        }

        $message = preg_replace('/(redis|pgsql|mysql|mongodb|sqlsrv):\/\/[^\\s]+/i', '[redacted]', $message) ?? $message;
        $message = preg_replace('/(?:password|passwd|token|secret)=\\S+/i', '$1=[redacted]', $message) ?? $message;
        $message = preg_replace('/[A-Za-z0-9_\\-]+\\.(?:local|internal|internal\\.local|svc|service)(?::\\d+)?/i', '[redacted-host]', $message) ?? $message;

        return mb_strimwidth($message, 0, 180, '...');
    }

    private function queueKey(string $queue): string
    {
        return 'queues:' . $queue;
    }
}
