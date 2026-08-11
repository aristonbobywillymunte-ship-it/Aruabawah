<?php

namespace Tests\Unit;

use App\Services\Admin\SystemMaintenanceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class SystemMaintenanceServiceTest extends TestCase
{
    private array $connections = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('queue.connections.redis.connection', 'default');
        Config::set('queue.connections.redis-ai.connection', 'default');
    }

    public function test_queue_targets_keep_all_logical_queues_when_underlying_connection_matches(): void
    {
        $this->fakeRedisConnections([
            'default' => new FakeQueueRedisConnection([
                'queues:default' => ['job-a'],
                'queues:news' => ['job-b'],
                'queues:apify' => ['job-c'],
                'queues:notification' => ['job-d'],
                'queues:ai-analysis' => ['job-e'],
                'queues:ai-backfill' => [],
            ]),
        ]);

        $snapshot = app(SystemMaintenanceService::class)->queueSnapshot();
        $queues = collect($snapshot)->pluck('queue')->all();

        $this->assertSame(
            ['default', 'news', 'apify', 'notification', 'ai-analysis', 'ai-backfill'],
            $queues
        );
        $this->assertContains('redis', collect($snapshot)->pluck('queue_connection')->all());
        $this->assertContains('redis-ai', collect($snapshot)->pluck('queue_connection')->all());
    }

    public function test_queue_snapshot_marks_redis_read_failure_as_error_not_zero(): void
    {
        $this->fakeRedisConnections([
            'default' => new FakeQueueRedisConnection([
                'queues:default' => ['job-a'],
            ], throwOnReadsFor: ['queues:default']),
        ]);

        $row = collect(app(SystemMaintenanceService::class)->queueSnapshot())
            ->firstWhere('queue', 'default');

        $this->assertSame('error', $row['status']);
        $this->assertNull($row['pending']);
        $this->assertNull($row['delayed']);
        $this->assertNull($row['reserved']);
        $this->assertNotEmpty($row['error']);
        $this->assertStringNotContainsString('127.0.0.1', strtolower($row['error']));
        $this->assertStringNotContainsString('password', strtolower($row['error']));
    }

    public function test_clear_pending_queues_reports_partial_failure_honestly(): void
    {
        $redis = new FakeQueueRedisConnection([
            'queues:default' => array_fill(0, 50, 'job-a'),
            'queues:default:delayed' => ['delayed-a'],
            'queues:default:reserved' => ['reserved-a'],
        ], throwOnTrimFor: ['queues:default:delayed']);

        $this->fakeRedisConnections([
            'default' => $redis,
        ]);

        $result = app(SystemMaintenanceService::class)->clearPendingQueues();

        $this->assertTrue($result['partial_failure']);
        $default = collect($result['queues'])->firstWhere('queue', 'default');
        $this->assertSame('partial', $default['status']);
        $this->assertSame(50, $default['pending_before']);
        $this->assertSame(1, $default['delayed_before']);
        $this->assertSame(50, $default['pending_removed']);
        $this->assertSame(0, $default['delayed_removed']);
        $this->assertSame('cleared', $default['pending_status']);
        $this->assertSame('failed', $default['delayed_status']);
        $this->assertSame(50, $result['deleted_jobs']);
        $this->assertTrue($result['partial_failure']);
        $this->assertArrayHasKey('queues:default:reserved', $redis->sets);
        $this->assertContains('queues:default', $redis->deletedKeys);
        $this->assertNotContains('queues:default:delayed', $redis->trimmedKeys);
    }

    public function test_pending_remove_count_uses_snapshot_not_del_return_value(): void
    {
        $redis = new FakeQueueRedisConnection([
            'queues:default' => array_fill(0, 50, 'job-a'),
            'queues:default:delayed' => [],
            'queues:default:reserved' => ['reserved-a'],
        ], deleteReturn: 1);

        $this->fakeRedisConnections(['default' => $redis]);

        $result = app(SystemMaintenanceService::class)->clearPendingQueues();
        $default = collect($result['queues'])->firstWhere('queue', 'default');

        $this->assertSame('cleared', $default['status']);
        $this->assertSame(50, $default['pending_removed']);
        $this->assertSame(50, $result['deleted_jobs']);
    }

    public function test_pending_failure_can_still_allow_delayed_clear_to_be_reported_independently(): void
    {
        $redis = new FakeQueueRedisConnection([
            'queues:default' => ['job-a', 'job-b'],
            'queues:default:delayed' => ['delayed-a'],
            'queues:default:reserved' => ['reserved-a'],
        ], throwOnDeleteFor: ['queues:default']);

        $this->fakeRedisConnections(['default' => $redis]);

        $result = app(SystemMaintenanceService::class)->clearPendingQueues();
        $default = collect($result['queues'])->firstWhere('queue', 'default');

        $this->assertSame('partial', $default['status']);
        $this->assertSame('failed', $default['pending_status']);
        $this->assertSame('cleared', $default['delayed_status']);
        $this->assertSame(0, $default['pending_removed']);
        $this->assertSame(1, $default['delayed_removed']);
        $this->assertSame(1, $result['deleted_jobs']);
        $this->assertTrue($result['partial_failure']);
    }

    public function test_reserved_sorted_sets_and_unrelated_keys_remain_untouched(): void
    {
        $redis = new FakeQueueRedisConnection([
            'queues:default' => ['job-a'],
            'queues:default:delayed' => ['delayed-a'],
            'queues:default:reserved' => ['reserved-a'],
            'foo' => ['bar'],
        ]);

        $this->fakeRedisConnections(['default' => $redis]);

        app(SystemMaintenanceService::class)->clearPendingQueues();

        $this->assertArrayHasKey('queues:default:reserved', $redis->sets);
        $this->assertArrayHasKey('foo', $redis->lists);
        $this->assertSame(['foo'], $redis->untouchedLists);
    }

    public function test_apify_is_not_treated_as_ai_queue_and_scraping_is_not_listed(): void
    {
        $groups = app(SystemMaintenanceService::class)->queueTargets();

        $this->assertSame(['default', 'news', 'apify', 'notification'], $groups[0]['queue_names']);
        $this->assertSame(['ai-analysis', 'ai-backfill'], $groups[1]['queue_names']);
        $this->assertNotContains('scraping', $groups[0]['queue_names']);
        $this->assertNotContains('apify', $groups[1]['queue_names']);
    }

    public function test_scheduler_restart_signal_is_written_with_a_ttl(): void
    {
        Cache::shouldReceive('put')
            ->once()
            ->with(
                'scheduler_should_restart',
                true,
                \Mockery::on(fn ($ttl) => $ttl instanceof \DateTimeInterface)
            );

        $result = app(SystemMaintenanceService::class)->requestSchedulerRestart();

        $this->assertSame('ok', $result['status']);
    }

    public function test_restart_worker_exit_zero_returns_success(): void
    {
        Artisan::shouldReceive('call')->once()->with('queue:restart')->andReturn(0);

        $service = app(SystemMaintenanceService::class);

        $this->assertSame('ok', $service->restartWorkers()['status']);
    }

    public function test_restart_worker_non_zero_returns_safe_failure(): void
    {
        Artisan::shouldReceive('call')->once()->with('queue:restart')->andReturn(1);

        $result = app(SystemMaintenanceService::class)->restartWorkers();

        $this->assertSame('error', $result['status']);
        $this->assertSame(1, $result['exit_code']);
        $this->assertStringNotContainsString('password', strtolower($result['error']));
    }

    public function test_restart_worker_exception_returns_safe_failure(): void
    {
        Artisan::shouldReceive('call')->once()->with('queue:restart')->andThrow(new \RuntimeException('redis://secret:secret@127.0.0.1:6379'));

        $result = app(SystemMaintenanceService::class)->restartWorkers();

        $this->assertSame('error', $result['status']);
        $this->assertStringNotContainsString('secret', strtolower($result['error']));
    }

    public function test_clear_cache_exit_zero_returns_success(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear')->andReturn(0);

        $result = app(SystemMaintenanceService::class)->clearApplicationCache();

        $this->assertSame('ok', $result['status']);
    }

    public function test_clear_cache_non_zero_returns_safe_failure(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear')->andReturn(2);

        $result = app(SystemMaintenanceService::class)->clearApplicationCache();

        $this->assertSame('error', $result['status']);
        $this->assertSame(2, $result['exit_code']);
    }

    public function test_clear_cache_exception_returns_safe_failure(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear')->andThrow(new \RuntimeException('pgsql://user:pass@localhost/db'));

        $result = app(SystemMaintenanceService::class)->clearApplicationCache();

        $this->assertSame('error', $result['status']);
        $this->assertStringNotContainsString('localhost', strtolower($result['error']));
    }

    private function fakeRedisConnections(array $connections): void
    {
        $this->connections = $connections;

        Redis::shouldReceive('connection')->andReturnUsing(function (string $name) {
            return $this->connections[$name];
        });
    }
}

class FakeQueueRedisConnection
{
    public array $lists = [];

    public array $sets = [];

    public array $deletedKeys = [];

    public array $trimmedKeys = [];

    public array $untouchedLists = [];

    public function __construct(
        array $entries,
        private array $throwOnReadsFor = [],
        private array $throwOnDeleteFor = [],
        private array $throwOnTrimFor = [],
        private int $deleteReturn = 1,
    )
    {
        foreach ($entries as $key => $values) {
            if (str_ends_with($key, ':delayed') || str_ends_with($key, ':reserved')) {
                $this->sets[$key] = $values;
            } else {
                $this->lists[$key] = $values;
                if ($key === 'foo') {
                    $this->untouchedLists[] = $key;
                }
            }
        }
    }

    public function llen(string $key): int
    {
        $this->maybeThrow($key);

        return count($this->lists[$key] ?? []);
    }

    public function zcard(string $key): int
    {
        $this->maybeThrow($key);

        return count($this->sets[$key] ?? []);
    }

    public function del(string $key): int
    {
        $this->maybeThrowDelete($key);
        $this->deletedKeys[] = $key;
        unset($this->lists[$key]);

        return $this->deleteReturn;
    }

    public function zremrangebyrank(string $key, int $start, int $end): int
    {
        $this->maybeThrowTrim($key);
        $this->trimmedKeys[] = $key;
        unset($this->sets[$key]);

        return 1;
    }

    private function maybeThrow(string $key): void
    {
        if (in_array($key, $this->throwOnReadsFor, true)) {
            throw new \RuntimeException('Redis connection failure for ' . $key);
        }
    }

    private function maybeThrowDelete(string $key): void
    {
        if (in_array($key, $this->throwOnDeleteFor, true)) {
            throw new \RuntimeException('Redis DEL failure for ' . $key);
        }
    }

    private function maybeThrowTrim(string $key): void
    {
        if (in_array($key, $this->throwOnTrimFor, true)) {
            throw new \RuntimeException('Redis ZREMRANGEBYRANK failure for ' . $key);
        }
    }
}
