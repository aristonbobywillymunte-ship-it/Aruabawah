<?php

namespace Tests\Feature;

use App\Console\Commands\RunApifyScraping;
use App\Jobs\ApifyScrapingJob;
use App\Models\ApifyActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApifyActorIntervalRuntimeRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_safely_is_independent_of_actor_interval_minutes(): void
    {
        $actorShort = $this->createActor('scrapeflow/facebook-posts-search-scraper-short', 5);
        $actorLong = $this->createActor('scrapeflow/facebook-posts-search-scraper-long', 720);

        $shortResult = ApifyScrapingJob::dispatchSafely([
            'platform' => 'Facebook',
            'keyword' => 'seno aji',
            'keywords' => ['seno aji'],
            'project_id' => 101,
            'actor_id' => $actorShort->id,
        ]);

        $longResult = ApifyScrapingJob::dispatchSafely([
            'platform' => 'Facebook',
            'keyword' => 'seno aji',
            'keywords' => ['seno aji'],
            'project_id' => 102,
            'actor_id' => $actorLong->id,
        ]);

        $this->assertTrue($shortResult);
        $this->assertTrue($longResult);
    }

    public function test_invalid_social_daily_schedule_does_not_fall_back_to_legacy_interval(): void
    {
        $command = new RunApifyScraping(app(\App\Services\SchedulerQueueGuard::class), app(\App\Services\SocialProjectScrapePriorityService::class));

        $invalidDailySchedule = $this->invokeProtected($command, 'normalizeDailyRunTimes', [null, ['19:00', '']]);

        $legacyIntervalMinutes = 999;

        $this->assertSame([], $invalidDailySchedule);
        $this->assertSame(999, $legacyIntervalMinutes);
    }

    public function test_failure_retry_cooldown_ignores_actor_interval_minutes(): void
    {
        $command = new RunApifyScraping(app(\App\Services\SchedulerQueueGuard::class), app(\App\Services\SocialProjectScrapePriorityService::class));

        $actorShort = $this->createActor('scrapeflow/facebook-posts-search-scraper-short-failure', 5, [
            'last_run_at' => now()->subMinutes(2),
            'last_run_status' => 'failed',
            'last_run_message' => 'Connection timeout',
        ]);
        $actorLong = $this->createActor('scrapeflow/facebook-posts-search-scraper-long-failure', 720, [
            'last_run_at' => now()->subMinutes(2),
            'last_run_status' => 'failed',
            'last_run_message' => 'Connection timeout',
        ]);

        $recoveryShort = $this->invokeProtected($command, 'actorRecoveryAt', [$actorShort]);
        $recoveryLong = $this->invokeProtected($command, 'actorRecoveryAt', [$actorLong]);

        $this->assertNotNull($recoveryShort);
        $this->assertNotNull($recoveryLong);
        $this->assertTrue($recoveryShort->equalTo($recoveryLong));
    }

    protected function createActor(string $slug, int $intervalMinutes, array $overrides = []): ApifyActor
    {
        return ApifyActor::create(array_merge([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Posts Search Scraper',
            'actor_slug' => $slug,
            'function_type' => 'Search Post',
            'status' => 'active',
            'default_limit' => 50,
            'interval_minutes' => $intervalMinutes,
            'memory_limit' => 1024,
            'range_mode' => '7d',
            'priority' => 1,
        ], $overrides));
    }

    protected function invokeProtected(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $refMethod = $reflection->getMethod($method);
        $refMethod->setAccessible(true);

        return $refMethod->invokeArgs($object, $arguments);
    }
}
