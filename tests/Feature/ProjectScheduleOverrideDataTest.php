<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectScheduleOverrideDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_persists_portal_and_social_schedule_overrides_as_arrays(): void
    {
        $package = Package::create([
            'name' => 'Override Package',
            'price' => 100000,
            'use_portal' => true,
            'news_interval_minutes' => 5,
            'social_interval_minutes' => 10,
            'is_active' => true,
        ]);

        $project = Project::create([
            'name' => 'Override Project',
            'package_id' => $package->id,
            'news_run_times_override' => ['07:00', '19:00'],
            'social_run_times_override' => ['10:00', '16:00', '22:00'],
        ]);

        $project->refresh();

        $this->assertSame(['07:00', '19:00'], $project->news_run_times_override);
        $this->assertSame(['10:00', '16:00', '22:00'], $project->social_run_times_override);
        $this->assertSame($package->id, $project->package_id);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'package_id' => $package->id,
        ]);
    }

    public function test_project_supports_null_schedule_overrides(): void
    {
        $project = Project::create([
            'name' => 'Null Override Project',
            'news_run_times_override' => null,
            'social_run_times_override' => null,
        ]);

        $project->refresh();

        $this->assertNull($project->news_run_times_override);
        $this->assertNull($project->social_run_times_override);
    }

    public function test_project_model_casts_override_values_to_arrays_and_keeps_package_relation(): void
    {
        $package = Package::create([
            'name' => 'Relation Package',
            'price' => 50000,
            'use_portal' => false,
            'news_interval_minutes' => 15,
            'social_interval_minutes' => 20,
            'is_active' => true,
        ]);

        $project = Project::create([
            'name' => 'Relation Project',
            'package_id' => $package->id,
            'news_run_times_override' => ['08:30', '20:30'],
            'social_run_times_override' => ['09:00'],
        ]);

        $loaded = Project::with('package')->findOrFail($project->id);

        $this->assertSame(['08:30', '20:30'], $loaded->news_run_times_override);
        $this->assertSame(['09:00'], $loaded->social_run_times_override);
        $this->assertNotNull($loaded->package);
        $this->assertSame($package->id, $loaded->package->id);
    }
}
