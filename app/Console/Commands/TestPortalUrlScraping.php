<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Project;
use App\Models\ScrapingItem;
use App\Services\ContentMatchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;

class TestPortalUrlScraping extends Command
{
    protected $signature = 'scraping:test-portal-url
                            {--project-id= : Specific project ID}
                            {--url= : Portal article URL to test}
                            {--source-id= : Optional news source ID}
                            {--no-ai : Do not dispatch AI analysis jobs}
                            {--no-telegram : Suppress Telegram notification dispatch}
                            {--dry-run : Do not write production rows}';

    protected $description = 'Test final portal URL extraction without Google News discovery';

    public function handle(): int
    {
        $projectId = (int) $this->option('project-id');
        $url = trim((string) $this->option('url'));
        $sourceId = $this->option('source-id');
        $dryRun = (bool) $this->option('dry-run');

        if ($projectId <= 0 || $url === '') {
            $this->error('project-id dan url wajib diisi.');
            return self::FAILURE;
        }

        $project = Project::find($projectId);
        if (! $project) {
            $this->error("Project {$projectId} tidak ditemukan.");
            return self::FAILURE;
        }

        if (! $project->is_active) {
            $this->warn("Project {$project->name} tidak aktif.");
            return self::FAILURE;
        }

        if (! $this->isLikelyPortalUrl($url)) {
            $this->error('URL tidak lolos validasi portal.');
            return self::FAILURE;
        }

        $source = null;
        if ($sourceId) {
            $source = DB::table('news_sources')->where('id', $sourceId)->first();
        }

        $extractor = app(RunNewsPortalScraping::class);
        $method = new ReflectionClass($extractor);
        $fetch = $method->getMethod('fetchFullContent');
        $fetch->setAccessible(true);
        $result = $fetch->invoke($extractor, $url);

        $content = (string) ($result['content'] ?? '');
        $canonicalUrl = (string) ($result['canonical_url'] ?? $url);
        $title = trim((string) ($result['title'] ?? ''));
        $sourceName = trim((string) ($result['source_name'] ?? ''));
        $publishedAt = $result['published_at'] ?? null;
        $contentLength = mb_strlen(trim($content));
        $passed = $contentLength > 500 && $title !== '' && $canonicalUrl !== '' && $this->isFinalPortalArticleUrl($canonicalUrl);

        $payload = [
            'mode' => 'manual_portal_url',
            'url' => $url,
            'final_url' => $canonicalUrl,
            'title' => $title,
            'source_name' => $sourceName,
            'published_at' => optional($publishedAt)?->toIso8601String(),
            'content_length' => $contentLength,
            'preview' => mb_substr(trim($content), 0, 400),
            'passed' => $passed,
        ];

        if ($dryRun) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $canonicalKey = $canonicalUrl;
        if (! $this->isFinalPortalArticleUrl($canonicalKey)) {
            $this->error('Final URL masih berada di host Google. Kandidat ditolak.');
            return self::FAILURE;
        }

        if ($contentLength < 500) {
            $this->error('Content terlalu pendek untuk disimpan.');
            return self::FAILURE;
        }

        DB::beginTransaction();
        try {
            $existingArticleId = Article::where('canonical_url', $canonicalKey)->value('id');
            $matchingService = app(ContentMatchingService::class);
            $projectMatchesContent = $matchingService->matchesProjectContent($project, $content);

            DB::table('candidate_links')->updateOrInsert(
                ['canonical_url' => $canonicalKey],
                [
                    'url' => $url,
                    'source_type' => 'test_portal_url',
                    'status' => 'approved',
                    'project_id' => $project->id,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $candidate = DB::table('candidate_links')->where('canonical_url', $canonicalKey)->first();
            if (! $candidate) {
                throw new \RuntimeException('Candidate link gagal dibuat.');
            }

            $scrapingItem = ScrapingItem::updateOrCreate(
                ['candidate_link_id' => $candidate->id],
                [
                    'url' => $url,
                    'status' => 'scraped',
                    'retry_count' => 0,
                    'last_attempt_at' => now(),
                    'error_message' => null,
                ]
            );

            $article = Article::updateOrCreate(
                ['canonical_url' => $canonicalKey],
                [
                    'title' => $title,
                    'content' => $content,
                    'url' => $canonicalUrl,
                    'source_name' => $sourceName,
                    'published_at' => $publishedAt,
                    'sentiment' => 'neutral',
                    'category' => 'news',
                ]
            );

            $articleStatus = $existingArticleId ? 'reused' : 'saved';
            $newArticle = $existingArticleId ? false : true;
            $reusedArticle = $existingArticleId ? true : false;
            $reason = $existingArticleId ? 'Existing article reused' : 'New article saved';
            $projectReason = $projectMatchesContent ? 'Project keyword matched on content' : 'Project keyword did not match content';

            DB::commit();

            $this->line(json_encode([
                'mode' => 'manual_portal_url',
                'project_id' => $project->id,
                'candidate_link_id' => $candidate->id,
                'scraping_item_id' => $scrapingItem->id,
                'article_id' => $article->id,
                'status' => $articleStatus,
                'reason' => $reason,
                'new_article' => $newArticle,
                'reused_article' => $reusedArticle,
                'project_reason' => $projectReason,
                'project_match' => $projectMatchesContent,
                'title' => $title,
                'canonical_url' => $canonicalUrl,
                'source_name' => $sourceName,
                'published_at' => optional($publishedAt)?->toIso8601String(),
                'content_length' => $contentLength,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function isLikelyPortalUrl(string $url): bool
    {
        if (Str::startsWith($url, ['javascript:', '#'])) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if ($host === '' || $this->isGoogleUrl($url)) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        foreach (['/login', '/auth', '/account', '/search', '/tag', '/static', '/asset', '/ads'] as $needle) {
            if (str_contains(strtolower($path), $needle)) {
                return false;
            }
        }

        return true;
    }

    private function isGoogleUrl(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        return $host === 'google.com'
            || str_ends_with($host, '.google.com')
            || $host === 'news.google.com';
    }

    private function isFinalPortalArticleUrl(string $url): bool
    {
        if ($url === '' || $this->isGoogleUrl($url)) {
            return false;
        }

        return true;
    }
}
