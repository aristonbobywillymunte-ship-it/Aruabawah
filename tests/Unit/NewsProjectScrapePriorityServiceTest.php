<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Services\NewsProjectScrapePriorityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsProjectScrapePriorityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_project_with_valid_keywords_is_prioritized_for_first_attempt_then_returns_to_normal_order(): void
    {
        DB::beginTransaction();

        try {
            $service = app(NewsProjectScrapePriorityService::class);

            $oldOne = Project::create([
                'name' => 'Priority Old One',
                'topics' => ['alpha news'],
                'is_active' => true,
            ]);
            $oldOne->update(['first_news_scrape_attempt_at' => Carbon::now()->subDays(3)]);

            $oldTwo = Project::create([
                'name' => 'Priority Old Two',
                'topics' => ['beta news'],
                'is_active' => true,
            ]);
            $oldTwo->update(['first_news_scrape_attempt_at' => Carbon::now()->subDays(2)]);

            $newProject = Project::create([
                'name' => 'Priority New Project',
                'topics' => ['gamma news'],
                'is_active' => true,
            ]);

            $inactive = Project::create([
                'name' => 'Priority Inactive',
                'topics' => ['inactive news'],
                'is_active' => false,
            ]);

            $keywordless = Project::create([
                'name' => 'Priority Keywordless',
                'topics' => [],
                'is_active' => true,
            ]);

            $projects = collect([$oldOne, $oldTwo, $newProject, $inactive, $keywordless]);
            $prioritized = $service->prioritize($projects);

            $this->assertSame(
                [$newProject->id, $oldTwo->id, $oldOne->id],
                $prioritized->pluck('id')->all(),
                'Project baru tanpa first-attempt marker harus diprioritaskan lebih dulu.'
            );

            $this->assertFalse($prioritized->pluck('id')->contains($inactive->id));
            $this->assertFalse($prioritized->pluck('id')->contains($keywordless->id));

            $service->recordAttempt($newProject);
            $newProject->refresh();

            $this->assertNotNull($newProject->first_news_scrape_attempt_at);

            $prioritizedAfterAttempt = $service->prioritize($projects);

            $this->assertSame($newProject->id, $prioritizedAfterAttempt->last()->id);
            $this->assertEqualsCanonicalizing(
                [$oldOne->id, $oldTwo->id, $newProject->id],
                $prioritizedAfterAttempt->pluck('id')->all(),
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_project_scrape_keywords_are_normalized_from_topics(): void
    {
        DB::beginTransaction();

        try {
            $project = Project::create([
                'name' => 'Keyword Normalization',
                'topics' => ['  wagub kaltim  ', '', '  ', 'samarinda'],
                'is_active' => true,
            ]);

            $this->assertSame(['wagub kaltim', 'samarinda'], $project->scrapeKeywords());
            $this->assertTrue($project->hasScrapeKeywords());
        } finally {
            DB::rollBack();
        }
    }
}
