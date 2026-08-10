<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\NewsSource;
use App\Models\Package;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PortalScheduleFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_automatic_successful_execution_writes_fulfillment_and_skips_immediate_next_evaluation(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 20, 5, 0, 'Asia/Makassar'));

        try {
            $project = $this->createProject([
                'news_runs_per_day' => 2,
                'news_run_times' => ['08:00', '20:00'],
                'news_run_times_override' => null,
            ]);

            $this->fakeSuccessResponses();

            $exit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $exit);
            $this->assertNotNull($project->fresh()->portal_last_scheduled_success_at);
            $this->assertTrue($project->fresh()->portal_last_scheduled_success_at->equalTo(Carbon::now()));
            $this->assertSame(1, \DB::table('candidate_links')->count());

            $this->fakeSuccessResponses();
            $secondExit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $secondExit);
            $this->assertSame(1, \DB::table('candidate_links')->count());
            $this->assertSame(1, \DB::table('scraping_items')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_automatic_failed_execution_does_not_write_fulfillment_and_slot_remains_due(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 20, 5, 0, 'Asia/Makassar'));

        try {
            $project = $this->createProject([
                'news_runs_per_day' => 2,
                'news_run_times' => ['08:00', '20:00'],
                'news_run_times_override' => null,
            ]);
            $project->forceFill([
                'portal_last_scheduled_success_at' => Carbon::create(2026, 8, 10, 17, 0, 0, 'Asia/Makassar'),
            ])->save();

            $this->fakeFailureResponses();

            $exit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertTrue(
                $project->fresh()->portal_last_scheduled_success_at->equalTo(Carbon::create(2026, 8, 10, 17, 0, 0, 'Asia/Makassar'))
            );

            $this->fakeSuccessResponses();
            $retryExit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertNotNull($project->fresh()->portal_last_scheduled_success_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_manual_project_id_execution_does_not_consume_automatic_slot_before_or_after_schedule_time(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 20, 5, 0, 'Asia/Makassar'));

        try {
            $project = $this->createProject([
                'news_runs_per_day' => 2,
                'news_run_times' => ['08:00', '20:00'],
                'news_run_times_override' => null,
            ]);
            $project->forceFill([
                'portal_last_scheduled_success_at' => Carbon::create(2026, 8, 10, 17, 0, 0, 'Asia/Makassar'),
            ])->save();

            $this->fakeSuccessResponses();

            $manualExit = Artisan::call('scraping:run-news', [
                '--project-id' => $project->id,
                '--keyword' => 'seno aji',
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $manualExit);
            $this->assertTrue(
                $project->fresh()->portal_last_scheduled_success_at->equalTo(Carbon::create(2026, 8, 10, 17, 0, 0, 'Asia/Makassar'))
            );

            $automaticExit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $automaticExit);
            $this->assertNotNull($project->fresh()->portal_last_scheduled_success_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_zero_new_article_success_still_fulfills_slot(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 20, 5, 0, 'Asia/Makassar'));

        try {
            $project = $this->createProject([
                'news_runs_per_day' => 2,
                'news_run_times' => ['08:00', '20:00'],
                'news_run_times_override' => null,
            ]);
            $project->forceFill([
                'portal_last_scheduled_success_at' => Carbon::create(2026, 8, 10, 17, 0, 0, 'Asia/Makassar'),
            ])->save();

            Article::create([
                'title' => 'Portal 1',
                'content' => 'Existing content for reuse.',
                'url' => 'https://portal.test/articles/portal-1',
                'canonical_url' => 'https://portal.test/articles/portal-1',
                'source_name' => 'Portal Test',
            ]);

            $this->fakeSuccessResponses();

            $exit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $exit);
            $this->assertNotNull($project->fresh()->portal_last_scheduled_success_at);
            $this->assertSame(1, \DB::table('articles')->count());
            $this->assertSame(1, \DB::table('project_articles')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_project_override_schedule_still_fulfills_slot(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 20, 5, 0, 'Asia/Makassar'));

        try {
            $project = $this->createProject([
                'news_runs_per_day' => 2,
                'news_run_times' => ['21:00', '22:00'],
                'news_run_times_override' => ['08:00', '20:00'],
            ]);

            $this->fakeSuccessResponses();

            $exit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $exit);
            $this->assertNotNull($project->fresh()->portal_last_scheduled_success_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_package_inherited_schedule_still_fulfills_slot(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 20, 5, 0, 'Asia/Makassar'));

        try {
            $project = $this->createProject([
                'news_runs_per_day' => 2,
                'news_run_times' => ['08:00', '20:00'],
                'news_run_times_override' => null,
            ], 'Package Inherited Project');

            $this->fakeSuccessResponses();

            $exit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $exit);
            $this->assertNotNull($project->fresh()->portal_last_scheduled_success_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_legacy_interval_minutes_has_no_effect_on_fulfillment_due_check(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 20, 5, 0, 'Asia/Makassar'));

        try {
            $project = $this->createProject([
                'news_runs_per_day' => 2,
                'news_run_times' => ['08:00', '20:00'],
                'news_interval_minutes' => 999,
                'news_run_times_override' => null,
            ]);

            $this->fakeSuccessResponses();

            $exit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $exit);
            $this->assertNotNull($project->fresh()->portal_last_scheduled_success_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    protected function createProject(array $packageOverrides, string $projectName = 'Portal Fulfillment Project'): Project
    {
        $package = Package::create(array_merge([
            'name' => 'Portal Fulfillment Package',
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

        $project = Project::create([
            'name' => $projectName,
            'topics' => ['seno aji'],
            'package_id' => $package->id,
            'news_run_times_override' => $packageOverrides['news_run_times_override'] ?? null,
            'social_run_times_override' => $packageOverrides['social_run_times_override'] ?? null,
        ]);

        if (! NewsSource::query()->where('domain', 'portal.test')->exists()) {
            NewsSource::create([
                'name' => 'Fulfillment Portal Test',
                'domain' => 'portal.test',
                'base_url' => 'https://portal.test',
                'search_url' => 'https://portal.test/search?q={keyword}',
                'article_link_selector' => 'a.article-link',
                'search_result_selector' => 'a.article-link',
                'is_active' => true,
                'crawling_type' => 'html',
                'is_search_enabled' => true,
                'is_feed_enabled' => false,
                'is_sitemap_enabled' => false,
                'scrape_priority' => 1,
            ]);
        }

        return $project;
    }

    protected function fakeSuccessResponses(): void
    {
        $manualHtml = <<<HTML
            <html><body>
                <a class="article-link" href="https://portal.test/articles/portal-1">Portal 1</a>
            </body></html>
        HTML;

        $articleHtml = <<<HTML
            <html>
              <head>
                <title>Portal 1</title>
                <link rel="canonical" href="https://portal.test/articles/portal-1" />
                <meta property="og:title" content="Portal 1" />
                <meta property="article:published_time" content="2026-08-10T00:00:00Z" />
              </head>
              <body>
                <article>
                  <p>Seno Aji menghadiri acara penting di Samarinda.</p>
                  <p>%s</p>
                </article>
              </body>
            </html>
        HTML;

        $articleHtml = sprintf(
            $articleHtml,
            str_repeat('More repeated content to exceed the final article length requirement. ', 60)
        );

        Http::fake(function (Request $request) use ($manualHtml, $articleHtml) {
            $url = (string) $request->url();

            if (str_contains($url, '/search?q=')) {
                return Http::response($manualHtml, 200);
            }

            if ($url === 'https://portal.test/articles/portal-1') {
                return Http::response($articleHtml, 200);
            }

            if (str_contains($url, 'news.google.com/rss/search')) {
                return Http::response('<rss version="2.0"><channel></channel></rss>', 200);
            }

            return Http::response('', 404);
        });
    }

    protected function fakeFailureResponses(): void
    {
        Http::fake(function (Request $request) {
            $url = (string) $request->url();

            if (str_contains($url, '/search?q=')) {
                return Http::response('', 500);
            }

            if (str_contains($url, 'news.google.com/rss/search')) {
                return Http::response('', 500);
            }

            return Http::response('', 404);
        });
    }
}
