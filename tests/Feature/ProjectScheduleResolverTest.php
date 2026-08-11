<?php

namespace Tests\Feature;

use App\Models\ApifyActor;
use App\Models\Package;
use App\Models\Project;
use App\Services\Scraping\ProjectScheduleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectScheduleResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_portal_schedule_from_project_override_when_complete(): void
    {
        $project = $this->createProjectWithPackage([
            'news_runs_per_day' => 2,
            'news_run_times' => ['08:00', '20:00'],
            'news_run_times_override' => ['07:00', '19:00'],
        ]);

        $result = app(ProjectScheduleResolver::class)->resolvePortal($project);

        $this->assertSame(['07:00', '19:00'], $result['times']);
        $this->assertSame('project', $result['source']);
        $this->assertNull($result['reason']);
    }

    public function test_it_resolves_portal_schedule_from_package_when_override_is_empty(): void
    {
        $project = $this->createProjectWithPackage([
            'news_runs_per_day' => 2,
            'news_run_times' => ['08:00', '20:00'],
            'news_run_times_override' => null,
        ]);

        $result = app(ProjectScheduleResolver::class)->resolvePortal($project);

        $this->assertSame(['08:00', '20:00'], $result['times']);
        $this->assertSame('package', $result['source']);
        $this->assertNull($result['reason']);
    }

    public function test_it_treats_blank_portal_override_as_empty_inherit_package(): void
    {
        $project = $this->createProjectWithPackage([
            'news_runs_per_day' => 2,
            'news_run_times' => ['08:00', '20:00'],
            'news_run_times_override' => ['', ''],
        ]);

        $result = app(ProjectScheduleResolver::class)->resolvePortal($project);

        $this->assertSame(['08:00', '20:00'], $result['times']);
        $this->assertSame('package', $result['source']);
        $this->assertNull($result['reason']);
    }

    public function test_it_rejects_invalid_portal_override_without_falling_back_to_package(): void
    {
        $project = $this->createProjectWithPackage([
            'news_runs_per_day' => 2,
            'news_run_times' => ['08:00', '20:00'],
            'news_run_times_override' => ['07:00'],
        ]);

        $result = app(ProjectScheduleResolver::class)->resolvePortal($project);

        $this->assertSame([], $result['times']);
        $this->assertSame('none', $result['source']);
        $this->assertSame('invalid_project_override', $result['reason']);
    }

    public function test_it_rejects_duplicate_portal_override_without_falling_back_to_package(): void
    {
        $project = $this->createProjectWithPackage([
            'news_runs_per_day' => 2,
            'news_run_times' => ['08:00', '20:00'],
            'news_run_times_override' => ['07:00', '07:00'],
        ]);

        $result = app(ProjectScheduleResolver::class)->resolvePortal($project);

        $this->assertSame([], $result['times']);
        $this->assertSame('none', $result['source']);
        $this->assertSame('invalid_project_override', $result['reason']);
    }

    public function test_it_reports_feature_disabled_when_portal_is_disabled(): void
    {
        $project = $this->createProjectWithPackage([
            'use_portal' => false,
            'news_runs_per_day' => 2,
            'news_run_times' => ['08:00', '20:00'],
        ]);

        $result = app(ProjectScheduleResolver::class)->resolvePortal($project);

        $this->assertSame([], $result['times']);
        $this->assertSame('none', $result['source']);
        $this->assertSame('feature_disabled', $result['reason']);
    }

    public function test_it_reports_package_missing_when_project_has_no_package(): void
    {
        $project = Project::create([
            'name' => 'No Package Project',
        ]);

        $result = app(ProjectScheduleResolver::class)->resolvePortal($project);

        $this->assertSame([], $result['times']);
        $this->assertSame('none', $result['source']);
        $this->assertSame('package_missing', $result['reason']);
    }

    public function test_it_resolves_social_schedule_from_package_or_project_override(): void
    {
        $project = $this->createProjectWithPackage([
            'social_runs_per_day' => 3,
            'social_run_times' => ['09:00', '15:00', '21:00'],
            'social_run_times_override' => ['10:00', '16:00', '22:00'],
        ], withSocialActor: true);

        $result = app(ProjectScheduleResolver::class)->resolveSocial($project);

        $this->assertSame(['10:00', '16:00', '22:00'], $result['times']);
        $this->assertSame('project', $result['source']);
        $this->assertNull($result['reason']);
    }

    public function test_it_reports_invalid_package_schedule_when_default_times_do_not_match_run_count(): void
    {
        $project = $this->createProjectWithPackage([
            'news_runs_per_day' => 2,
            'news_run_times' => ['08:00'],
        ]);

        $result = app(ProjectScheduleResolver::class)->resolvePortal($project);

        $this->assertSame([], $result['times']);
        $this->assertSame('none', $result['source']);
        $this->assertSame('invalid_package_schedule', $result['reason']);
    }

    public function test_it_reports_package_schedule_not_configured_when_default_times_are_missing(): void
    {
        $project = $this->createProjectWithPackage([
            'social_runs_per_day' => 3,
            'social_run_times' => [],
        ], withSocialActor: true);

        $result = app(ProjectScheduleResolver::class)->resolveSocial($project);

        $this->assertSame([], $result['times']);
        $this->assertSame('none', $result['source']);
        $this->assertSame('package_schedule_not_configured', $result['reason']);
    }

    public function test_it_reports_invalid_package_schedule_when_default_times_are_partially_configured(): void
    {
        $project = $this->createProjectWithPackage([
            'social_runs_per_day' => 3,
            'social_run_times' => ['09:00', '15:00'],
        ], withSocialActor: true);

        $result = app(ProjectScheduleResolver::class)->resolveSocial($project);

        $this->assertSame([], $result['times']);
        $this->assertSame('none', $result['source']);
        $this->assertSame('invalid_package_schedule', $result['reason']);
    }

    public function test_it_reports_invalid_package_schedule_when_default_times_contain_bad_values(): void
    {
        $project = $this->createProjectWithPackage([
            'social_runs_per_day' => 2,
            'social_run_times' => ['09:00', 'bad'],
        ], withSocialActor: true);

        $result = app(ProjectScheduleResolver::class)->resolveSocial($project);

        $this->assertSame([], $result['times']);
        $this->assertSame('none', $result['source']);
        $this->assertSame('invalid_package_schedule', $result['reason']);
    }

    protected function createProjectWithPackage(array $packageOverrides, bool $withSocialActor = false): Project
    {
        $package = Package::create(array_merge([
            'name' => 'Resolver Package',
            'price' => 100000,
            'use_portal' => true,
            'news_interval_minutes' => 5,
            'social_interval_minutes' => 10,
            'news_runs_per_day' => 2,
            'news_run_times' => ['08:00', '20:00'],
            'social_runs_per_day' => 3,
            'social_run_times' => ['09:00', '15:00', '21:00'],
            'is_active' => true,
        ], $packageOverrides));

        if ($withSocialActor) {
            $actor = ApifyActor::create([
                'platform' => 'Facebook',
                'actor_name' => 'Facebook Actor',
                'actor_slug' => 'facebook-actor',
                'function_type' => 'Search Post',
                'status' => 'active',
                'default_limit' => 50,
                'memory_limit' => 1024,
                'range_mode' => '7d',
            ]);

            $package->actors()->attach($actor->id, [
                'is_enabled' => true,
                'cost_per_run_usd' => 0.1,
                'default_limit' => 10,
                'memory_limit' => 256,
            ]);
        }

        return Project::create([
            'name' => 'Resolver Project',
            'package_id' => $package->id,
            'news_run_times_override' => $packageOverrides['news_run_times_override'] ?? null,
            'social_run_times_override' => $packageOverrides['social_run_times_override'] ?? null,
        ]);
    }
}
