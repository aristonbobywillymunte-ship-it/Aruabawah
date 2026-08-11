<?php

namespace Tests\Unit;

use App\Services\Admin\SystemMaintenanceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SystemMaintenanceServiceTest extends TestCase
{
    private array $connections = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('queue.connections.redis.connection', 'primary-redis');
        Config::set('queue.connections.redis-ai.connection', 'ai-redis');
    }

    public function test_queue_snapshot_reports_pending_delayed_and_reserved_counts(): void
    {
        $this->fakeRedisConnections([
            'primary-redis' => new FakeQueueRedisConnection(
                lists: [
                    'queues:default' => ['job-a', 'job-b'],
                    'queues:news' => ['job-c'],
                ],
                sortedSets: [
                    'queues:default:delayed' => ['delayed-a'],
                    'queues:news:reserved' => ['reserved-a', 'reserved-b'],
                ],
            ),
            'ai-redis' => new FakeQueueRedisConnection(
                lists: [
                    'queues:ai-analysis' => ['ai-job'],
                ],
                sortedSets: [
                    'queues:ai-analysis:delayed' => ['ai-delayed-a', 'ai-delayed-b'],
                    'queues:ai-backfill:reserved' => ['ai-reserved-a'],
                ],
            ),
        ]);

        $snapshot = collect(app(SystemMaintenanceService::class)->queueSnapshot())
            ->keyBy(fn (array $row) => $row['connection'] . '|' . $row['queue']);

        $this->assertSame(2, $snapshot['primary-redis|default']['pending']);
        $this->assertSame(1, $snapshot['primary-redis|default']['delayed']);
        $this->assertSame(0, $snapshot['primary-redis|default']['reserved']);
        $this->assertSame(1, $snapshot['primary-redis|news']['pending']);
        $this->assertSame(2, $snapshot['primary-redis|news']['reserved']);
        $this->assertSame(1, $snapshot['ai-redis|ai-analysis']['pending']);
        $this->assertSame(2, $snapshot['ai-redis|ai-analysis']['delayed']);
        $this->assertSame(1, $snapshot['ai-redis|ai-backfill']['reserved']);
    }

    public function test_clear_pending_queues_keeps_reserved_jobs_intact(): void
    {
        $primary = new FakeQueueRedisConnection(
            lists: [
                'queues:default' => ['job-a', 'job-b'],
                'queues:news' => ['job-c'],
                'queues:apify' => ['job-d'],
            ],
            sortedSets: [
                'queues:default:delayed' => ['delayed-a'],
                'queues:news:reserved' => ['reserved-a'],
                'queues:apify:delayed' => ['delayed-b', 'delayed-c'],
                'queues:notification:reserved' => ['reserved-b'],
            ],
        );

        $ai = new FakeQueueRedisConnection(
            lists: [
                'queues:ai-analysis' => ['ai-job'],
                'queues:apify' => ['apify-ai-job'],
            ],
            sortedSets: [
                'queues:ai-analysis:delayed' => ['ai-delayed-a'],
                'queues:ai-backfill:reserved' => ['ai-reserved-a'],
            ],
        );

        $this->fakeRedisConnections([
            'primary-redis' => $primary,
            'ai-redis' => $ai,
        ]);

        $result = app(SystemMaintenanceService::class)->clearPendingQueues();

        $this->assertSame(10, $result['deleted_jobs']);
        $this->assertContains('queues:default', $primary->deletedKeys);
        $this->assertContains('queues:news', $primary->deletedKeys);
        $this->assertContains('queues:apify', $primary->deletedKeys);
        $this->assertContains('queues:default:delayed', $primary->trimmedKeys);
        $this->assertContains('queues:apify:delayed', $primary->trimmedKeys);
        $this->assertContains('queues:news:reserved', $primary->untouchedSortedSets);
        $this->assertContains('queues:notification:reserved', $primary->untouchedSortedSets);
        $this->assertContains('queues:ai-analysis', $ai->deletedKeys);
        $this->assertContains('queues:ai-analysis:delayed', $ai->trimmedKeys);
        $this->assertContains('queues:ai-backfill:reserved', $ai->untouchedSortedSets);
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

        app(SystemMaintenanceService::class)->requestSchedulerRestart();
    }

    public function test_worker_and_cache_commands_are_forwarded_to_artisan(): void
    {
        Artisan::shouldReceive('call')->once()->with('queue:restart')->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('optimize:clear')->andReturn(0);

        $service = app(SystemMaintenanceService::class);
        $this->assertSame(0, $service->restartWorkers());
        $this->assertSame(0, $service->clearApplicationCache());
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
    public array $deletedKeys = [];

    public array $trimmedKeys = [];

    public array $untouchedSortedSets = [];

    public function __construct(
        public array $lists = [],
        public array $sortedSets = [],
    ) {
        $this->untouchedSortedSets = array_keys($this->sortedSets);
    }

    public function llen(string $key): int
    {
        return count($this->lists[$key] ?? []);
    }

    public function zcard(string $key): int
    {
        return count($this->sortedSets[$key] ?? []);
    }

    public function del(string $key): int
    {
        $this->deletedKeys[] = $key;
        unset($this->lists[$key]);

        return 1;
    }

    public function zremrangebyrank(string $key, int $start, int $end): int
    {
        $this->trimmedKeys[] = $key;
        unset($this->sortedSets[$key]);

        return 1;
    }
}
