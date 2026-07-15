<?php

namespace App\Console\Commands;

use App\Jobs\AiAnalysisJob;
use App\Models\Article;
use App\Models\Project;
use App\Models\ScrapingItem;
use App\Services\AiAnalysisDispatchStateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TestSmallScrapingPipeline extends Command
{
    protected $signature = 'scraping:test-small
                            {--project-id= : Specific project ID}
                            {--keyword= : Keyword to test}
                            {--limit=3 : Max RSS items to stage}
                            {--no-ai : Skip AI analysis}';

    protected $description = 'Deterministic small scraping test through candidate_links -> scraping_items -> articles';

    public function handle(): int
    {
        $projectId = (int) $this->option('project-id');
        $keyword = trim((string) $this->option('keyword'));
        $limit = max(1, min(5, (int) $this->option('limit')));
        $runAi = ! (bool) $this->option('no-ai');

        abort_if($projectId <= 0 || $keyword === '', 1, 'project-id dan keyword wajib diisi.');

        $project = Project::findOrFail($projectId);
        if (! $project->is_active) {
            $this->warn("Project {$project->name} tidak aktif, dibatalkan.");
            return self::FAILURE;
        }

        $rssUrl = 'https://news.google.com/rss/search?' . http_build_query([
            'q' => $keyword,
            'hl' => 'id',
            'gl' => 'ID',
            'ceid' => 'ID:id',
            'num' => 30,
        ]);

        $response = Http::timeout(20)->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($rssUrl);
        if (! $response->successful()) {
            $this->error('RSS Google News gagal diambil.');
            return self::FAILURE;
        }

        $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
        if (! $xml || ! isset($xml->channel->item)) {
            $this->error('RSS kosong.');
            return self::FAILURE;
        }

        $items = collect($xml->channel->item)
            ->take($limit)
            ->values();

        $countsBefore = $this->counts();
        $staged = 0;
        $scraped = 0;
        $analyzed = 0;

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $title = trim((string) $item->title);
                $articleUrl = trim((string) $item->link);
                $sourceName = trim((string) ($item->source ?? 'Google News'));
                $description = trim(strip_tags((string) ($item->description ?? '')));
                $publishedAt = null;
                try {
                    $publishedAt = \Carbon\Carbon::parse((string) $item->pubDate);
                } catch (\Throwable) {
                    $publishedAt = now();
                }

                if ($title === '' || $articleUrl === '') {
                    continue;
                }

                $canonicalUrl = $articleUrl;

                DB::table('candidate_links')->updateOrInsert(
                    ['canonical_url' => $canonicalUrl],
                    [
                        'url' => $articleUrl,
                        'source_type' => 'google_news',
                        'status' => 'approved',
                        'project_id' => $project->id,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
                $candidate = DB::table('candidate_links')->where('canonical_url', $canonicalUrl)->first();
                $staged++;

                $scrapingItem = ScrapingItem::updateOrCreate(
                    ['candidate_link_id' => $candidate?->id],
                    [
                        'url' => $articleUrl,
                        'status' => 'approved',
                        'retry_count' => 0,
                        'last_attempt_at' => now(),
                    ]
                );
                $scraped++;

                $content = $description !== '' ? $description : $title;
                $article = Article::updateOrCreate(
                    ['canonical_url' => $canonicalUrl],
                    [
                        'title' => $title,
                        'content' => $content,
                        'url' => $articleUrl,
                        'source_name' => $sourceName,
                        'published_at' => $publishedAt,
                        'sentiment' => 'neutral',
                        'category' => 'news',
                    ]
                );

                $project->articles()->syncWithoutDetaching([$article->id]);

                if ($runAi) {
                    $dispatchStateService = app(AiAnalysisDispatchStateService::class);
                    $promptTemplateId = $dispatchStateService->resolvePromptTemplateId('article');
                    $providerContextHash = $dispatchStateService->resolveProviderContextHash();
                    $decision = $dispatchStateService->reserveQueuedStateAndDispatch([
                        'type' => 'article',
                        'id' => $article->id,
                        'project_id' => $project->id,
                        'title' => $title,
                        'url' => $articleUrl,
                        'content' => $content,
                        'source_name' => $sourceName,
                        'published_at' => optional($publishedAt)?->toIso8601String(),
                    ], $promptTemplateId, $providerContextHash);

                    if ($decision['should_dispatch'] ?? false) {
                        $analyzed++;
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $countsAfter = $this->counts();

        $this->line(json_encode([
            'project_id' => $project->id,
            'keyword' => $keyword,
            'staged' => $staged,
            'scraped' => $scraped,
            'analyzed' => $analyzed,
            'before' => $countsBefore,
            'after' => $countsAfter,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    protected function counts(): array
    {
        return [
            'candidate_links' => DB::table('candidate_links')->count(),
            'scraping_items' => DB::table('scraping_items')->count(),
            'articles' => DB::table('articles')->count(),
            'project_articles' => DB::table('project_articles')->count(),
            'ai_analysis_results' => DB::table('ai_analysis_results')->count(),
            'risk_notifications' => DB::table('risk_notifications')->count(),
        ];
    }
}
