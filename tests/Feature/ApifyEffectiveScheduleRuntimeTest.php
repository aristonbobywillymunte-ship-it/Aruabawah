<?php

namespace Tests\Feature;

use App\Jobs\ApifyScrapingJob;
use App\Models\ApifyActor;
use App\Models\ApifySetting;
use App\Models\Package;
use App\Models\Project;
use App\Models\SocialMediaItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Services\SocialProjectScrapePriorityService;
use Tests\TestCase;

class ApifyEffectiveScheduleRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_schedule_is_used_when_project_override_is_empty(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 30, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $actor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00', '21:00'],
                'social_run_times_override' => null,
            ]);

            $this->markLastRun($project, $actor, '20:30');

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertPushed(ApifyScrapingJob::class, 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_pre_first_slot_recovers_previous_day_last_slot_when_unfulfilled(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 11, 7, 0, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $actor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00', '21:00'],
                'social_run_times_override' => null,
            ]);

            $this->markLastRunAt($project, $actor, Carbon::create(2026, 8, 10, 20, 30, 0, 'Asia/Makassar'));

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertPushed(ApifyScrapingJob::class, 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_pre_first_slot_skips_when_previous_day_last_slot_is_already_fulfilled(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 11, 7, 0, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $actor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00', '21:00'],
                'social_run_times_override' => null,
            ]);

            $this->markLastRunAt($project, $actor, Carbon::create(2026, 8, 10, 21, 0, 0, 'Asia/Makassar'));

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertNothingPushed();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_multiple_missed_slots_collapse_to_latest_due_slot(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 11, 22, 0, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $actor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00', '21:00'],
                'social_run_times_override' => null,
            ]);

            $this->markLastRunAt($project, $actor, Carbon::create(2026, 8, 10, 20, 30, 0, 'Asia/Makassar'));

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertPushed(ApifyScrapingJob::class, 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_failed_scheduled_state_remains_due_for_next_tick(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 30, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $actor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00', '21:00'],
                'social_run_times_override' => null,
            ]);

            $this->seedDispatchState($project, $actor, Carbon::create(2026, 8, 10, 9, 0, 0, 'Asia/Makassar'), 'failed');

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertPushed(ApifyScrapingJob::class, 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_project_override_takes_precedence_over_package_schedule(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 30, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $actor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00', '21:00'],
                'social_run_times_override' => ['10:00', '22:00'],
            ]);

            $this->markLastRun($project, $actor, '20:30');

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertNothingPushed();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_invalid_project_override_safely_skips_automatic_social_dispatch(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 30, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $actor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00', '21:00'],
                'social_run_times_override' => ['10:00', ''],
            ]);

            $this->markLastRun($project, $actor, '20:30');

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertNothingPushed();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_malformed_project_override_safely_skips_automatic_social_dispatch(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 30, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $actor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00', '21:00'],
                'social_run_times_override' => ['10:00', 'bad'],
            ]);

            $this->markLastRun($project, $actor, '20:30');

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertNothingPushed();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_duplicate_project_override_safely_skips_automatic_social_dispatch(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 30, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $actor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00', '21:00'],
                'social_run_times_override' => ['10:00', '10:00'],
            ]);

            $this->markLastRun($project, $actor, '20:30');

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertNothingPushed();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_non_string_project_override_safely_skips_automatic_social_dispatch(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 30, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $actor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00', '21:00'],
                'social_run_times_override' => [123, '22:00'],
            ]);

            $this->markLastRun($project, $actor, '20:30');

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertNothingPushed();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_invalid_package_schedule_safely_skips_automatic_social_dispatch(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 30, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $actor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00'],
                'social_run_times_override' => null,
            ]);

            $this->markLastRun($project, $actor, '20:30');

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertNothingPushed();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_missing_package_safely_skips_automatic_social_dispatch(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 30, 0, 'Asia/Makassar'));

        try {
            $this->createApifySetting();
            $this->bindNoopPriorityService();

            $actor = ApifyActor::create([
                'platform' => 'Facebook',
                'actor_name' => 'Facebook Posts Search Scraper',
                'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
                'function_type' => 'Search Post',
                'status' => 'active',
                'default_limit' => 50,
                'interval_minutes' => 5,
                'memory_limit' => 1024,
                'range_mode' => '7d',
                'priority' => 1,
            ]);

            $project = Project::create([
                'name' => 'Apify No Package Project',
                'topics' => ['seno aji'],
                'package_id' => null,
            ]);

            $this->markLastRun($project, $actor, '20:30');

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertNothingPushed();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_comment_scraper_remains_active_even_when_social_schedule_is_invalid(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 30, 0, 'Asia/Makassar'));

        try {
            $this->createApifySetting();
            $this->bindNoopPriorityService();
            $package = Package::create([
                'name' => 'Comment Package',
                'price' => 100000,
                'use_portal' => true,
                'news_interval_minutes' => 5,
                'social_interval_minutes' => 10,
                'news_runs_per_day' => 2,
                'news_run_times' => ['08:00', '20:00'],
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00'],
                'is_active' => true,
            ]);

            $actor = ApifyActor::create([
                'platform' => 'Facebook',
                'actor_name' => 'Facebook Comments Scraper',
                'actor_slug' => 'apify/facebook-comments-scraper',
                'function_type' => 'Comment Scraper',
                'status' => 'active',
                'default_limit' => 20,
                'interval_minutes' => 999,
                'memory_limit' => 1024,
                'range_mode' => '7d',
                'priority' => 1,
            ]);

            $package->actors()->attach($actor->id, [
                'is_enabled' => true,
                'cost_per_run_usd' => 0.15,
                'default_limit' => 20,
                'memory_limit' => 1024,
            ]);

            $project = Project::create([
                'name' => 'Comment Project',
                'topics' => ['seno aji'],
                'package_id' => $package->id,
            ]);

            $item = SocialMediaItem::create([
                'platform' => 'Facebook',
                'post_url' => 'https://www.facebook.com/example/posts/123',
                'author_name' => 'Tester',
                'content' => 'Komentar perlu dicek',
                'comments_checked' => false,
                'posted_at' => now(),
                'raw_json' => '{}',
            ]);

            $project->socialMediaItems()->attach($item->id);

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertPushed(ApifyScrapingJob::class, 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_force_dispatch_still_bypasses_social_timing(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 30, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $actor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00', '21:00'],
                'social_run_times_override' => ['10:00', '22:00'],
            ]);

            $this->markLastRun($project, $actor, '20:30');

            Queue::fake();

            $this->artisan('scraping:run-apify', [
                '--no-telegram' => true,
                '--force-dispatch' => true,
            ])->assertExitCode(0);

            Queue::assertPushed(ApifyScrapingJob::class, 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_force_dispatch_success_does_not_fulfill_main_actor_slot(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 30, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $actor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00', '21:00'],
                'social_run_times_override' => null,
            ]);

            $this->markLastRun($project, $actor, '20:30', scheduledExecution: false);

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertPushed(ApifyScrapingJob::class, 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_comment_scraper_success_does_not_fulfill_main_actor_slot(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 30, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $mainActor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00', '21:00'],
                'social_run_times_override' => null,
            ]);

            $commentActor = ApifyActor::create([
                'platform' => 'Facebook',
                'actor_name' => 'Facebook Comments Scraper',
                'actor_slug' => 'apify/facebook-comments-scraper',
                'function_type' => 'Comment Scraper',
                'status' => 'active',
                'default_limit' => 20,
                'interval_minutes' => 5,
                'memory_limit' => 1024,
                'range_mode' => '7d',
                'priority' => 2,
            ]);

            $project->package->actors()->attach($commentActor->id, [
                'is_enabled' => true,
                'cost_per_run_usd' => 0.15,
                'default_limit' => 20,
                'memory_limit' => 1024,
            ]);

            $this->markLastRun($project, $commentActor, '20:30', scheduledExecution: false);
            $this->markLastRun($project, $mainActor, '20:30');

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertPushed(ApifyScrapingJob::class, 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_non_scheduled_execution_success_does_not_fulfill_main_actor_slot(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 30, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $actor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00', '21:00'],
                'social_run_times_override' => null,
            ]);

            $this->markLastRun($project, $actor, '20:30', scheduledExecution: false);

            Queue::fake();

            $this->artisan('scraping:run-apify', ['--no-telegram' => true])
                ->assertExitCode(0);

            Queue::assertPushed(ApifyScrapingJob::class, 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_project_id_remains_functional_for_automatic_social_routing(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 30, 0, 'Asia/Makassar'));

        try {
            $this->bindNoopPriorityService();
            [$project, $actor] = $this->createSocialProjectAndActor([
                'social_runs_per_day' => 2,
                'social_run_times' => ['09:00', '21:00'],
                'social_run_times_override' => null,
            ]);

            $this->markLastRun($project, $actor, '20:30');

            Queue::fake();

            $this->artisan('scraping:run-apify', [
                '--project-id' => $project->id,
                '--no-telegram' => true,
            ])->assertExitCode(0);

            Queue::assertPushed(ApifyScrapingJob::class, 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    protected function createSocialProjectAndActor(array $packageOverrides = []): array
    {
        $this->createApifySetting();

        $package = Package::create(array_merge([
            'name' => 'Apify Schedule Package',
            'price' => 100000,
            'use_portal' => true,
            'news_interval_minutes' => 5,
            'social_interval_minutes' => 10,
            'news_runs_per_day' => 2,
            'news_run_times' => ['08:00', '20:00'],
            'social_runs_per_day' => 2,
            'social_run_times' => ['09:00', '21:00'],
            'is_active' => true,
        ], $packageOverrides));

        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Posts Search Scraper',
            'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'default_limit' => 50,
            'interval_minutes' => 5,
            'memory_limit' => 1024,
            'range_mode' => '7d',
            'priority' => 1,
        ]);

        $package->actors()->attach($actor->id, [
            'is_enabled' => true,
            'cost_per_run_usd' => 0.2,
            'default_limit' => 50,
            'memory_limit' => 1024,
        ]);

        $project = Project::create([
            'name' => 'Apify Schedule Project',
            'topics' => ['seno aji'],
            'package_id' => $package->id,
            'social_run_times_override' => $packageOverrides['social_run_times_override'] ?? null,
        ]);

        return [$project, $actor];
    }

    protected function markLastRun(Project $project, ApifyActor $actor, string $time, bool $scheduledExecution = true): void
    {
        $completedAt = Carbon::create(2026, 8, 10, (int) substr($time, 0, 2), (int) substr($time, 3, 2), 0, 'Asia/Makassar');
        $this->seedDispatchState($project, $actor, $completedAt, 'success', $scheduledExecution);
    }

    protected function markLastRunAt(Project $project, ApifyActor $actor, Carbon $completedAt, bool $scheduledExecution = true): void
    {
        $this->seedDispatchState($project, $actor, $completedAt, 'success', $scheduledExecution);
    }

    protected function seedDispatchState(Project $project, ApifyActor $actor, Carbon $completedAt, string $status, bool $scheduledExecution = true): void
    {
        $queuedAt = $completedAt->copy()->subHour();
        \DB::table('apify_dispatch_states')->insert([
            'dispatch_key' => 'seed-' . $project->id . '-' . $actor->id . '-' . $completedAt->timestamp . '-' . $status . '-' . ($scheduledExecution ? 'scheduled' : 'unscheduled'),
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'platform' => $actor->platform,
            'keyword' => 'seno aji',
            'normalized_keyword' => 'seno aji',
            'window_start' => $completedAt->copy()->startOfDay(),
            'window_end' => $completedAt->copy()->endOfDay(),
            'status' => $status,
            'queued_at' => $queuedAt,
            'started_at' => $status === 'queued' ? null : $queuedAt,
            'completed_at' => $status === 'success' ? $completedAt : null,
            'next_retry_at' => null,
            'last_error_code' => null,
            'last_error_message' => null,
            'is_scheduled_execution' => $scheduledExecution,
            'created_at' => $completedAt,
            'updated_at' => $completedAt,
        ]);
    }

    protected function createApifySetting(): void
    {
        ApifySetting::create([
            'api_token' => 'test-token',
            'connection_status' => 'connected',
        ]);
    }

    protected function bindNoopPriorityService(): void
    {
        $this->app->instance(SocialProjectScrapePriorityService::class, new class extends SocialProjectScrapePriorityService {
            public function prioritize(\Illuminate\Support\Collection $projects): \Illuminate\Support\Collection
            {
                return $projects->values();
            }
        });
    }
}
