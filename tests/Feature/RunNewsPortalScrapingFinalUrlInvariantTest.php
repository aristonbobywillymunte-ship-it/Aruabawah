<?php

namespace Tests\Feature;

use App\Console\Commands\RunNewsPortalScraping;
use App\Models\Project;
use App\Services\News\GoogleNewsUrlDecoderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunNewsPortalScrapingFinalUrlInvariantTest extends TestCase
{
    use DatabaseTransactions;

    public function test_google_news_candidates_are_rejected_when_final_url_remains_google(): void
    {
        $project = Project::create([
            'name' => 'Google Invariant Project',
            'topics' => ['seno aji'],
            'is_active' => true,
        ]);

        $this->app->instance(GoogleNewsUrlDecoderService::class, new class extends GoogleNewsUrlDecoderService {
            public function decode(string $googleUrl): array
            {
                return [
                    'success' => false,
                    'original_url' => null,
                    'method' => 'test',
                    'error' => 'forced decoder failure',
                    'trace' => [],
                ];
            }
        });

        $rss = <<<XML
            <rss version="2.0">
              <channel>
                <item>
                  <title>Seno Aji di Maratua</title>
                  <link>https://news.google.com/articles/abc</link>
                  <description>Wagub Kaltim menghadiri acara penting di Maratua.</description>
                  <pubDate>Wed, 03 Jul 2026 00:00:00 GMT</pubDate>
                </item>
              </channel>
            </rss>
        XML;

        $googleHtml = <<<HTML
            <html>
              <head>
                <title>Seno Aji di Maratua | Google News</title>
                <link rel="canonical" href="https://news.google.com/articles/abc" />
                <meta property="og:url" content="https://news.google.com/articles/abc" />
                <meta property="article:published_time" content="2026-07-03T00:00:00Z" />
                <meta property="og:title" content="Seno Aji di Maratua | Google News" />
              </head>
              <body>
                <article>
                  <p>Seno Aji menghadiri acara di Maratua.</p>
                  <p>This content is intentionally long to exceed the minimum threshold. </p>
                  <p>Wagub Kaltim, Maratua, and related references appear repeatedly. </p>
                  <p>More repeated content to exceed the final article length requirement. </p>
                  <p>More repeated content to exceed the final article length requirement. </p>
                  <p>More repeated content to exceed the final article length requirement. </p>
                  <p>More repeated content to exceed the final article length requirement. </p>
                  <p>More repeated content to exceed the final article length requirement. </p>
                  <p>More repeated content to exceed the final article length requirement. </p>
                </article>
              </body>
            </html>
        HTML;

        Http::fake(function (Request $request) use ($rss, $googleHtml) {
            $url = (string) $request->url();

            if (str_contains($url, 'news.google.com/rss/search')) {
                return Http::response($rss, 200);
            }

            if (str_contains($url, 'news.google.com/articles/abc')) {
                return Http::response($googleHtml, 200);
            }

            return Http::response('', 404);
        });

        Artisan::call('scraping:run-news', [
            '--project-id' => $project->id,
            '--keyword' => 'seno aji',
            '--limit' => 1,
            '--no-telegram' => true,
            '--no-ai' => true,
            '--no-reach' => true,
        ]);

        $this->assertSame(0, \DB::table('articles')->count());
        $this->assertSame(0, \DB::table('project_articles')->count());
        $this->assertSame(1, \DB::table('candidate_links')->count());
        $this->assertTrue(
            \DB::table('candidate_links')->where('url', 'like', '%news.google.com%')->exists(),
            'Raw Google discovery URL should remain in candidate history only.'
        );
        $this->assertSame(1, \DB::table('scraping_items')->count());
        $this->assertSame(0, \DB::table('ai_analysis_results')->count());
    }

    public function test_direct_portal_smoke_rejects_google_final_urls_before_persistence(): void
    {
        $project = Project::create([
            'name' => 'Direct Google Invariant Project',
            'topics' => ['seno aji'],
            'is_active' => true,
        ]);

        $googleHtml = <<<HTML
            <html>
              <head>
                <title>Seno Aji di Maratua | Google News</title>
                <link rel="canonical" href="https://news.google.com/articles/abc" />
                <meta property="og:url" content="https://news.google.com/articles/abc" />
                <meta property="article:published_time" content="2026-07-03T00:00:00Z" />
                <meta property="og:title" content="Seno Aji di Maratua | Google News" />
              </head>
              <body>
                <article>
                  <p>Seno Aji menghadiri acara di Maratua.</p>
                  <p>This content is intentionally long to exceed the minimum threshold. </p>
                  <p>Wagub Kaltim, Maratua, and related references appear repeatedly. </p>
                  <p>More repeated content to exceed the final article length requirement. </p>
                  <p>More repeated content to exceed the final article length requirement. </p>
                  <p>More repeated content to exceed the final article length requirement. </p>
                  <p>More repeated content to exceed the final article length requirement. </p>
                  <p>More repeated content to exceed the final article length requirement. </p>
                  <p>More repeated content to exceed the final article length requirement. </p>
                </article>
              </body>
            </html>
        HTML;

        Http::fake(function (Request $request) use ($googleHtml) {
            $url = (string) $request->url();

            if ($url === 'https://news.google.com/articles/abc') {
                return Http::response($googleHtml, 200);
            }

            return Http::response('', 404);
        });

        $exit = Artisan::call('scraping:test-portal-url', [
            '--project-id' => $project->id,
            '--url' => 'https://news.google.com/articles/abc',
            '--no-telegram' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertSame(0, \DB::table('candidate_links')->count());
        $this->assertSame(0, \DB::table('articles')->count());
        $this->assertSame(0, \DB::table('project_articles')->count());
    }
}
