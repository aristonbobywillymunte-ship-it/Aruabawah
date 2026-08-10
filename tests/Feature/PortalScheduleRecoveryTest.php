<?php

namespace Tests\Feature;

use App\Console\Commands\RunNewsPortalScraping;
use App\Models\NewsSource;
use App\Models\Package;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Tests\TestCase;

class PortalScheduleRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_due_slot_uses_previous_day_last_slot_before_first_slot(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 11, 7, 0, 0, 'Asia/Makassar'));

        try {
            $command = app(RunNewsPortalScraping::class);
            $result = $this->invokeProtected($command, 'latestDueSlotAt', [
                ['08:00', '20:00'],
                Carbon::now(),
            ]);

            $this->assertTrue($result->equalTo(Carbon::create(2026, 8, 10, 20, 0, 0, 'Asia/Makassar')));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_latest_due_slot_uses_today_latest_slot_after_midday(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 21, 0, 0, 'Asia/Makassar'));

        try {
            $command = app(RunNewsPortalScraping::class);
            $result = $this->invokeProtected($command, 'latestDueSlotAt', [
                ['08:00', '20:00'],
                Carbon::now(),
            ]);

            $this->assertTrue($result->equalTo(Carbon::create(2026, 8, 10, 20, 0, 0, 'Asia/Makassar')));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_successful_catch_up_fulfills_slot_and_immediate_next_evaluation_skips(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Asia/Makassar'));

        try {
            $project = $this->createProject([
                'news_runs_per_day' => 1,
                'news_run_times' => ['08:00'],
                'news_run_times_override' => null,
            ]);

            $this->fakeSuccessResponses('portal-recovery.test');

            $exit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $exit);
            $this->assertNotNull($project->fresh()->portal_last_scheduled_success_at);

            $secondExit = Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertSame(0, $secondExit);
            $this->assertSame(1, \DB::table('articles')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_failed_automatic_run_does_not_fulfill_and_cooldown_blocks_immediate_retry(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Asia/Makassar'));

        try {
            $project = $this->createProject([
                'news_runs_per_day' => 1,
                'news_run_times' => ['08:00'],
                'news_run_times_override' => null,
            ]);
            $baseline = Carbon::create(2026, 8, 9, 20, 0, 0, 'Asia/Makassar');
            $project->forceFill([
                'portal_last_scheduled_success_at' => $baseline,
            ])->save();

            $this->fakeFailureResponses('portal-recovery.test');

            Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertTrue($project->fresh()->portal_last_scheduled_success_at->equalTo($baseline));
            $this->assertSame(0, \DB::table('candidate_links')->count());

            $this->fakeSuccessResponses('portal-recovery.test');
            Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertTrue($project->fresh()->portal_last_scheduled_success_at->equalTo($baseline));

            Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 11, 0, 'Asia/Makassar'));
            Artisan::call('scraping:run-news', [
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

    public function test_manual_project_id_does_not_clear_or_fulfill_automatic_slot(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Asia/Makassar'));

        try {
            $project = $this->createProject([
                'news_runs_per_day' => 1,
                'news_run_times' => ['08:00'],
                'news_run_times_override' => null,
            ]);
            $project->forceFill([
                'portal_last_scheduled_success_at' => Carbon::create(2026, 8, 9, 20, 0, 0, 'Asia/Makassar'),
            ])->save();

            $this->fakeSuccessResponses('portal-recovery.test');

            Artisan::call('scraping:run-news', [
                '--project-id' => $project->id,
                '--keyword' => 'seno aji',
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertTrue(
                $project->fresh()->portal_last_scheduled_success_at->equalTo(Carbon::create(2026, 8, 9, 20, 0, 0, 'Asia/Makassar'))
            );

            Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertNotNull($project->fresh()->portal_last_scheduled_success_at);
            $this->assertTrue($project->fresh()->portal_last_scheduled_success_at->equalTo(Carbon::now()));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_zero_new_article_success_and_override_package_inheritance_still_work(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 20, 5, 0, 'Asia/Makassar'));

        try {
            $overrideProject = $this->createProject([
                'news_runs_per_day' => 2,
                'news_run_times' => ['21:00', '22:00'],
                'news_run_times_override' => ['08:00', '20:00'],
            ], 'Override Recovery Project', 'portal-recovery.override.test');

            $packageProject = $this->createProject([
                'news_runs_per_day' => 2,
                'news_run_times' => ['08:00', '20:00'],
                'news_run_times_override' => null,
            ], 'Package Recovery Project', 'portal-recovery.package.test');

            \App\Models\Article::create([
                'title' => 'Portal 1',
                'content' => 'Existing content for reuse.',
                'url' => 'https://portal-recovery.package.test/articles/portal-1',
                'canonical_url' => 'https://portal-recovery.package.test/articles/portal-1',
                'source_name' => 'Portal Recovery Test',
            ]);

            $this->fakeSuccessResponses('portal-recovery.override.test');
            $this->fakeSuccessResponses('portal-recovery.package.test');

            Artisan::call('scraping:run-news', [
                '--limit' => 1,
                '--no-telegram' => true,
                '--no-ai' => true,
                '--no-reach' => true,
            ]);

            $this->assertNotNull($overrideProject->fresh()->portal_last_scheduled_success_at);
            $this->assertNotNull($packageProject->fresh()->portal_last_scheduled_success_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    protected function createProject(array $packageOverrides, string $projectName = 'Portal Recovery Project', string $domain = 'portal-recovery.test'): Project
    {
        $package = Package::create(array_merge([
            'name' => 'Portal Recovery Package',
            'price' => 100000,
            'use_portal' => true,
            'news_interval_minutes' => 5,
            'social_interval_minutes' => 10,
            'news_runs_per_day' => 1,
            'news_run_times' => ['08:00'],
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

        if (! NewsSource::query()->where('domain', $domain)->exists()) {
            NewsSource::create([
                'name' => 'Portal Recovery Test',
                'domain' => $domain,
                'base_url' => 'https://' . $domain,
                'search_url' => 'https://' . $domain . '/search?q={keyword}',
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

    protected function fakeSuccessResponses(string $domain): void
    {
        $manualHtml = <<<HTML
            <html><body>
                <a class="article-link" href="https://{$domain}/articles/portal-1">Portal 1</a>
            </body></html>
        HTML;

        $articleHtml = <<<HTML
            <html>
              <head>
                <title>Portal 1</title>
                <link rel="canonical" href="https://{$domain}/articles/portal-1" />
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

        Http::fake(function (Request $request) use ($manualHtml, $articleHtml, $domain) {
            $url = (string) $request->url();

            if (str_contains($url, '/search?q=')) {
                return Http::response($manualHtml, 200);
            }

            if ($url === 'https://' . $domain . '/articles/portal-1') {
                return Http::response($articleHtml, 200);
            }

            if (str_contains($url, 'news.google.com/rss/search')) {
                return Http::response('<rss version="2.0"><channel></channel></rss>', 200);
            }

            return Http::response('', 404);
        });
    }

    protected function fakeFailureResponses(string $domain): void
    {
        Http::fake(function (Request $request) use ($domain) {
            $url = (string) $request->url();

            if (str_contains($url, '/search?q=')) {
                return Http::response('', 500);
            }

            if (str_contains($url, 'news.google.com/rss/search')) {
                return Http::response('', 500);
            }

            if ($url === 'https://' . $domain . '/articles/portal-1') {
                return Http::response('', 500);
            }

            return Http::response('', 404);
        });
    }

    private function invokeProtected(object $object, string $method, array $arguments = [])
    {
        $reflection = new ReflectionClass($object);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($object, $arguments);
    }
}
