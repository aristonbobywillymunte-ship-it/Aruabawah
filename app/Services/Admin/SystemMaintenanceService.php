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

    /**
     * Queue targets are grouped by the underlying Redis connection name.
     * If the two Laravel queue connections point to the same Redis backend,
     * duplicates are removed before any action is executed.
     */
    public function queueTargets(): array
    {
        $redisConnection = (string) config('queue.connections.redis.connection', 'default');
        $redisAiConnection = (string) config('queue.connections.redis-ai.connection', $redisConnection);

        $targets = [
            $redisConnection => ['default', 'news', 'scraping', 'apify', 'notification'],
            $redisAiConnection => ['ai-analysis', 'ai-backfill', 'apify'],
        ];

        $normalized = [];

        foreach ($targets as $connection => $queues) {
            foreach ($queues as $queue) {
                $normalized[$connection . '|' . $queue] = [
                    'connection' => $connection,
                    'queue' => $queue,
                ];
            }
        }

        return array_values($normalized);
    }

    public function requiredConfirmationPhrase(): string
    {
        return self::CONFIRMATION_PHRASE;
    }

    public function queueSnapshot(): array
    {
        $snapshot = [];

        foreach ($this->queueTargets() as $target) {
            $snapshot[] = array_merge($target, $this->queueCounts($target['connection'], $target['queue']));
        }

        return $snapshot;
    }

    public function clearPendingQueues(): array
    {
        $deletedJobs = 0;
        $clearedQueues = [];

        foreach ($this->queueTargets() as $target) {
            $counts = $this->queueCounts($target['connection'], $target['queue']);
            $pending = $counts['pending'];
            $delayed = $counts['delayed'];

            if ($pending === 0 && $delayed === 0) {
                continue;
            }

            $redis = Redis::connection($target['connection']);
            $queueKey = $this->queueKey($target['queue']);

            if ($pending > 0) {
                $redis->del($queueKey);
            }

            if ($delayed > 0) {
                $redis->zremrangebyrank($queueKey . ':delayed', 0, -1);
            }

            $deletedJobs += $pending + $delayed;
            $clearedQueues[] = array_merge($target, $counts);
        }

        return [
            'deleted_jobs' => $deletedJobs,
            'queues' => $clearedQueues,
        ];
    }

    public function restartWorkers(): int
    {
        return Artisan::call('queue:restart');
    }

    public function requestSchedulerRestart(): void
    {
        Cache::put(
            self::SCHEDULER_RESTART_CACHE_KEY,
            true,
            now()->addMinutes(self::SCHEDULER_RESTART_TTL_MINUTES)
        );
    }

    public function clearApplicationCache(): int
    {
        return Artisan::call('optimize:clear');
    }

    public function queueCounts(string $redisConnection, string $queue): array
    {
        $redis = Redis::connection($redisConnection);
        $queueKey = $this->queueKey($queue);

        try {
            $pending = (int) $redis->llen($queueKey);
        } catch (Throwable) {
            $pending = 0;
        }

        try {
            $delayed = (int) $redis->zcard($queueKey . ':delayed');
        } catch (Throwable) {
            $delayed = 0;
        }

        try {
            $reserved = (int) $redis->zcard($queueKey . ':reserved');
        } catch (Throwable) {
            $reserved = 0;
        }

        return [
            'pending' => $pending,
            'delayed' => $delayed,
            'reserved' => $reserved,
            'total' => $pending + $delayed + $reserved,
        ];
    }

    private function queueKey(string $queue): string
    {
        return 'queues:' . $queue;
    }
}
