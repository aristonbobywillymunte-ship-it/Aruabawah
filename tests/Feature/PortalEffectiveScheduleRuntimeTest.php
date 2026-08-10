<?php

namespace Tests\Feature;

use App\Models\NewsSource;
use App\Models\Package;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PortalEffectiveScheduleRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_automatic_portal_run_uses_package_schedule_when_project_override_is_empty(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 20, 30, 0, 'Asia/Makassar'));

        try {
            $project = $this->createProjectWithPackage([
                'news_runs_per_day' => 2,
                'news_run_times' => ['19:00', '21:00'],
                'news_run_times_override' => null,
                'news_interval_minutes' => 999,
            ]);

            $project->forceFill(['portal_last_scheduled_success_at' => Carbon::now()->subHours(2)])->save();
            $this->fakePortalResponses();

            $exit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $exit);
            $this->assertGreaterThan(0, \DB::table('candidate_links')->count());
            $this->assertGreaterThan(0, \DB::table('scraping_items')->count());
            $this->assertNull($project->fresh()->news_run_times_override);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_automatic_portal_run_uses_project_override_instead_of_package_schedule(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 19, 30, 0, 'Asia/Makassar'));

        try {
            $project = $this->createProjectWithPackage([
                'news_runs_per_day' => 2,
                'news_run_times' => ['21:00', '22:00'],
                'news_run_times_override' => ['19:00', '21:00'],
                'news_interval_minutes' => 999,
            ]);

            $project->forceFill(['portal_last_scheduled_success_at' => Carbon::now()->subHours(2)])->save();
            $this->fakePortalResponses();

            $exit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $exit);
            $this->assertGreaterThan(0, \DB::table('candidate_links')->count());
            $this->assertGreaterThan(0, \DB::table('scraping_items')->count());
            $this->assertSame(['19:00', '21:00'], $project->fresh()->news_run_times_override);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_automatic_portal_run_skips_when_project_override_is_invalid_even_if_package_schedule_exists(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 19, 30, 0, 'Asia/Makassar'));

        try {
            $this->createProjectWithPackage([
                'news_runs_per_day' => 2,
                'news_run_times' => ['21:00', '22:00'],
                'news_run_times_override' => ['19:00'],
                'news_interval_minutes' => 999,
            ]);

            $this->fakePortalResponses();

            $exit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $exit);
            $this->assertSame(0, \DB::table('articles')->count());
            $this->assertSame(0, \DB::table('project_articles')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_automatic_portal_run_skips_when_no_effective_schedule_exists_and_interval_is_ignored(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 19, 30, 0, 'Asia/Makassar'));

        try {
            $this->createProjectWithPackage([
                'news_runs_per_day' => null,
                'news_run_times' => [],
                'news_run_times_override' => null,
                'news_interval_minutes' => 999,
            ]);

            $this->fakePortalResponses();

            $exit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $exit);
            $this->assertSame(0, \DB::table('articles')->count());
            $this->assertSame(0, \DB::table('project_articles')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_automatic_portal_run_skips_when_portal_is_disabled(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 19, 30, 0, 'Asia/Makassar'));

        try {
            $this->createProjectWithPackage([
                'use_portal' => false,
                'news_runs_per_day' => 2,
                'news_run_times' => ['19:00', '21:00'],
                'news_run_times_override' => null,
            ]);

            $this->fakePortalResponses();

            $exit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $exit);
            $this->assertSame(0, \DB::table('articles')->count());
            $this->assertSame(0, \DB::table('project_articles')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_explicit_project_id_preserves_manual_timing_bypass_semantics(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 7, 30, 0, 'Asia/Makassar'));

        try {
            $project = $this->createProjectWithPackage([
                'news_runs_per_day' => 2,
                'news_run_times' => ['21:00', '22:00'],
                'news_run_times_override' => ['19:00', '21:00'],
            ]);

            $project->forceFill(['portal_last_scheduled_success_at' => Carbon::now()->subMinutes(10)])->save();
            $this->fakePortalResponses();

            $exit = Artisan::call('scraping:run-news', [
                '--project-id' => $project->id,
                '--keyword' => 'seno aji',
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $exit);
            $this->assertGreaterThan(0, \DB::table('candidate_links')->count());
            $this->assertGreaterThan(0, \DB::table('scraping_items')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    protected function createProjectWithPackage(array $packageOverrides): Project
    {
        $package = Package::create(array_merge([
            'name' => 'Portal Runtime Package',
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
            'name' => 'Portal Runtime Project',
            'topics' => ['seno aji'],
            'package_id' => $package->id,
            'news_run_times_override' => $packageOverrides['news_run_times_override'] ?? null,
            'social_run_times_override' => $packageOverrides['social_run_times_override'] ?? null,
        ]);

        NewsSource::create([
            'name' => 'Runtime Portal Test',
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

        return $project;
    }

    protected function fakePortalResponses(): void
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
}
