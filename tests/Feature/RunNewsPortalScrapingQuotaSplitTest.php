<?php

namespace Tests\Feature;

use App\Jobs\AiAnalysisJob;
use App\Models\AiPromptTemplate;
use App\Models\AiProvider;
use App\Models\NewsSource;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use App\Models\AiAnalysisDispatchState;
use Tests\TestCase;

class RunNewsPortalScrapingQuotaSplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_portal_completes_first_and_google_news_still_runs_in_same_cycle(): void
    {
        $project = Project::create([
            'name' => 'Quota Split Project',
            'topics' => ['seno aji'],
            'is_active' => true,
        ]);

        $source = NewsSource::create([
            'name' => 'Portal Test',
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

        $manualHtml = <<<HTML
            <html><body>
                <a class="article-link" href="https://portal.test/articles/manual-1">Manual 1</a>
                <a class="article-link" href="https://portal.test/articles/manual-2">Manual 2</a>
                <a class="article-link" href="https://portal.test/articles/manual-3">Manual 3</a>
            </body></html>
        HTML;

        $googleRss = <<<XML
            <rss version="2.0">
              <channel>
                <item>
                  <title>Seno Aji Manual 1</title>
                  <link>https://portal.test/articles/manual-1</link>
                  <description>Manual duplicate candidate</description>
                  <pubDate>Wed, 03 Jul 2026 00:00:00 GMT</pubDate>
                </item>
                <item>
                  <title>Seno Aji Google 1</title>
                  <link>https://portal.test/articles/google-1</link>
                  <description>Google item 1</description>
                  <pubDate>Wed, 03 Jul 2026 01:00:00 GMT</pubDate>
                </item>
                <item>
                  <title>Seno Aji Google 2</title>
                  <link>https://portal.test/articles/google-2</link>
                  <description>Google item 2</description>
                  <pubDate>Wed, 03 Jul 2026 02:00:00 GMT</pubDate>
                </item>
                <item>
                  <title>Seno Aji Google 3</title>
                  <link>https://portal.test/articles/google-3</link>
                  <description>Google item 3</description>
                  <pubDate>Wed, 03 Jul 2026 03:00:00 GMT</pubDate>
                </item>
              </channel>
            </rss>
        XML;

        $articleBodies = [
            'https://portal.test/articles/manual-1' => $this->portalArticleHtml(
                'https://portal.test/articles/manual-1',
                'Manual 1',
                'Seno Aji Manual 1',
                'Portal Test',
                '2026-07-03T00:00:00Z',
                str_repeat('Manual content one about Seno Aji. ', 120),
            ),
            'https://portal.test/articles/manual-2' => $this->portalArticleHtml(
                'https://portal.test/articles/manual-2',
                'Manual 2',
                'Seno Aji Manual 2',
                'Portal Test',
                '2026-07-03T00:10:00Z',
                str_repeat('Manual content two about Seno Aji. ', 120),
            ),
            'https://portal.test/articles/manual-3' => $this->portalArticleHtml(
                'https://portal.test/articles/manual-3',
                'Manual 3',
                'Seno Aji Manual 3',
                'Portal Test',
                '2026-07-03T00:20:00Z',
                str_repeat('Manual content three about Seno Aji. ', 120),
            ),
            'https://portal.test/articles/google-1' => $this->portalArticleHtml(
                'https://portal.test/articles/google-1',
                'Google 1',
                'Seno Aji Google 1',
                'Portal Test',
                '2026-07-03T01:00:00Z',
                str_repeat('Google content one about Seno Aji. ', 120),
            ),
            'https://portal.test/articles/google-2' => $this->portalArticleHtml(
                'https://portal.test/articles/google-2',
                'Google 2',
                'Seno Aji Google 2',
                'Portal Test',
                '2026-07-03T02:00:00Z',
                str_repeat('Google content two about Seno Aji. ', 120),
            ),
            'https://portal.test/articles/google-3' => $this->portalArticleHtml(
                'https://portal.test/articles/google-3',
                'Google 3',
                'Seno Aji Google 3',
                'Portal Test',
                '2026-07-03T03:00:00Z',
                str_repeat('Google content three about Seno Aji. ', 120),
            ),
        ];

        Http::fake(function (Request $request) use ($manualHtml, $googleRss, $articleBodies) {
            $url = (string) $request->url();
            if (str_contains($url, 'news.google.com/rss/search')) {
                return Http::response($googleRss, 200);
            }

            if (str_contains($url, '/search?q=')) {
                return Http::response($manualHtml, 200);
            }

            foreach ($articleBodies as $articleUrl => $html) {
                if ($url === $articleUrl) {
                    return Http::response($html, 200);
                }
            }

            return Http::response('', 404);
        });

        $exit = Artisan::call('scraping:run-news', [
            '--project-id' => $project->id,
            '--keyword' => 'seno aji',
            '--limit' => 3,
            '--no-telegram' => true,
            '--no-ai' => true,
            '--no-reach' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame(6, \DB::table('articles')->count());
        $this->assertSame(6, \DB::table('project_articles')->count());

        $this->assertSame(3, \DB::table('articles')->whereIn('canonical_url', [
            'https://portal.test/articles/manual-1',
            'https://portal.test/articles/manual-2',
            'https://portal.test/articles/manual-3',
        ])->count());

        $this->assertSame(3, \DB::table('articles')->whereIn('canonical_url', [
            'https://portal.test/articles/google-1',
            'https://portal.test/articles/google-2',
            'https://portal.test/articles/google-3',
        ])->count());

        $this->assertSame(
            1,
            \DB::table('articles')->where('canonical_url', 'https://portal.test/articles/manual-1')->count(),
            'Duplicate Google candidate should reuse the existing manual article.'
        );
    }

    public function test_normal_ai_flow_pushes_job_to_ai_queue_and_retry_wait_requeues_once_due(): void
    {
        Queue::fake();

        $project = Project::create([
            'name' => 'AI Queue Test Project',
            'topics' => ['seno aji'],
            'is_active' => true,
        ]);

        NewsSource::create([
            'name' => 'Portal Queue Test',
            'domain' => 'queue.test',
            'base_url' => 'https://queue.test',
            'search_url' => 'https://queue.test/search?q={keyword}',
            'article_link_selector' => 'a.article-link',
            'search_result_selector' => 'a.article-link',
            'is_active' => true,
            'crawling_type' => 'html',
            'is_search_enabled' => true,
            'is_feed_enabled' => false,
            'is_sitemap_enabled' => false,
            'scrape_priority' => 1,
        ]);

        AiProvider::create([
            'name' => 'Gemini Queue Test',
            'provider_type' => 'Gemini',
            'base_url' => 'https://generativelanguage.googleapis.com/v1',
            'api_key' => 'test-gemini-key',
            'model_name' => 'gemini-2.5-flash',
            'temperature' => 0.7,
            'max_tokens' => 2048,
            'requests_per_minute' => 60,
            'is_active' => true,
            'is_default' => true,
        ]);

        AiPromptTemplate::create([
            'name' => 'Analisis Berita Utama',
            'source_type' => 'article',
            'system_prompt' => 'System prompt test',
            'user_prompt_template' => 'Analisis artikel: {title}',
            'is_active' => true,
            'is_default' => true,
        ]);

        $manualHtml = <<<HTML
            <html><body>
                <a class="article-link" href="https://queue.test/articles/ai-1">AI 1</a>
            </body></html>
        HTML;

        $articleHtml = $this->portalArticleHtml(
            'https://queue.test/articles/ai-1',
            'AI 1',
            'Seno Aji AI 1',
            'Portal Queue Test',
            '2026-07-03T04:00:00Z',
            str_repeat('Queue content about Seno Aji. ', 120),
        );

        Http::fake(function (Request $request) use ($manualHtml, $articleHtml) {
            $url = (string) $request->url();

            if (str_contains($url, '/search?q=')) {
                return Http::response($manualHtml, 200);
            }

            if ($url === 'https://queue.test/articles/ai-1') {
                return Http::response($articleHtml, 200);
            }

            return Http::response('', 404);
        });

        $exit = Artisan::call('scraping:run-news', [
            '--project-id' => $project->id,
            '--keyword' => 'seno aji',
            '--limit' => 1,
            '--no-telegram' => true,
            '--no-reach' => true,
        ]);

        $this->assertSame(0, $exit);

        Queue::assertPushedOn('ai-analysis', AiAnalysisJob::class);

        $dispatchState = AiAnalysisDispatchState::query()->first();
        $this->assertNotNull($dispatchState);
        $this->assertSame('queued', $dispatchState->status);

        $dispatchState->forceFill(['status' => 'retry_wait', 'next_retry_at' => now()->subMinute()])->save();

        $decision = app(\App\Services\AiAnalysisDispatchStateService::class)->reserveQueuedState([
            'type' => 'article',
            'id' => $dispatchState->analyzable_id,
            'project_id' => $project->id,
            'title' => 'AI 1',
            'content' => str_repeat('Queue content about Seno Aji. ', 120),
            'url' => 'https://queue.test/articles/ai-1',
            'source_name' => 'Portal Queue Test',
            'published_at' => '2026-07-03T04:00:00Z',
        ], $dispatchState->prompt_template_id, $dispatchState->provider_context_hash);

        $this->assertTrue($decision['should_dispatch']);
        $this->assertSame('queued', $decision['status']);
    }

    private function portalArticleHtml(string $url, string $title, string $ogTitle, string $sourceName, string $publishedAt, string $content): string
    {
        return <<<HTML
            <html>
              <head>
                <title>{$title}</title>
                <link rel="canonical" href="{$url}" />
                <meta property="og:title" content="{$ogTitle}" />
                <meta property="article:published_time" content="{$publishedAt}" />
                <meta name="author" content="{$sourceName}" />
              </head>
              <body>
                <article>
                  <p>{$content}</p>
                </article>
              </body>
            </html>
        HTML;
    }
}
