<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\SocialMediaItem;
use Illuminate\Console\Command;

class InspectGlobalKeywordCounts extends Command
{
    protected $signature = 'inspect:global-keyword
                            {keyword : Keyword atau frasa yang ingin dicek}
                            {--source=all : all|portal|social|news|facebook|instagram|tiktok}
                            {--limit=10 : Tampilkan sampel maksimal}
                            {--no-excerpt : Jangan tampilkan excerpt singkat}';

    protected $description = 'Inspect global article/social counts for a keyword without project filters.';

    public function handle(): int
    {
        $keyword = trim((string) $this->argument('keyword'));
        $source = strtolower(trim((string) $this->option('source') ?: 'all'));
        $limit = max(1, (int) $this->option('limit'));
        $showExcerpt = ! (bool) $this->option('no-excerpt');

        if ($keyword === '') {
            $this->error('Keyword wajib diisi.');
            return self::FAILURE;
        }

        $needle = mb_strtolower($keyword, 'UTF-8');
        $like = '%' . $needle . '%';
        $socialSources = ['facebook', 'instagram', 'tiktok', 'twitter', 'twitter/x', 'x.com', 'threads', 'youtube'];

        $articleQuery = Article::query()
            ->where(function ($q) use ($like) {
                $q->whereRaw('lower(coalesce(title, \'\')) like ?', [$like])
                  ->orWhereRaw('lower(coalesce(content, \'\')) like ?', [$like])
                  ->orWhereRaw('lower(coalesce(excerpt, \'\')) like ?', [$like])
                  ->orWhereRaw('lower(coalesce(summary, \'\')) like ?', [$like]);
            });

        $socialQuery = SocialMediaItem::query()
            ->where(function ($q) use ($like) {
                $q->whereRaw('lower(coalesce(content, \'\')) like ?', [$like])
                  ->orWhereRaw('lower(coalesce(raw_json, \'\')) like ?', [$like])
                  ->orWhereRaw('lower(coalesce(author_name, \'\')) like ?', [$like]);
            });

        if ($source === 'portal' || $source === 'news') {
            $articleQuery->where(function ($q) use ($socialSources) {
                $q->whereNull('source_name')
                  ->orWhereRaw(
                      'lower(coalesce(source_name, \'\')) not in (' . implode(',', array_fill(0, count($socialSources), '?')) . ')',
                      $socialSources
                  );
            });
            $socialQuery->whereRaw('1 = 0');
        } elseif (in_array($source, ['social', 'facebook', 'instagram', 'tiktok'], true)) {
            $articleQuery->where(function ($q) use ($socialSources) {
                $q->whereRaw(
                    'lower(coalesce(source_name, \'\')) in (' . implode(',', array_fill(0, count($socialSources), '?')) . ')',
                    $socialSources
                );
            });

            if (in_array($source, ['facebook', 'instagram', 'tiktok'], true)) {
                $articleQuery->whereRaw('lower(coalesce(source_name, \'\')) = ?', [$source]);
                $socialQuery->whereRaw('lower(coalesce(platform, \'\')) = ?', [$source]);
            }
        } elseif ($source !== 'all') {
            $articleQuery->whereRaw('lower(coalesce(source_name, \'\')) = ?', [$source]);
            $socialQuery->whereRaw('lower(coalesce(platform, \'\')) = ?', [$source]);
        }

        $articleCount = (clone $articleQuery)->count();
        $socialCount = (clone $socialQuery)->count();

        $this->line(json_encode([
            'keyword' => $keyword,
            'source' => $source,
            'global_article_count' => $articleCount,
            'global_social_count' => $socialCount,
            'samples' => [
                'articles' => $this->sampleArticles(clone $articleQuery, $limit, $showExcerpt),
                'social' => $this->sampleSocial(clone $socialQuery, $limit, $showExcerpt),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    private function sampleArticles($query, int $limit, bool $showExcerpt): array
    {
        return $query->orderByDesc('published_at')
            ->limit($limit)
            ->get(['id', 'title', 'source_name', 'published_at', 'url', 'content', 'excerpt'])
            ->map(function ($row) use ($showExcerpt) {
                return [
                    'id' => (int) $row->id,
                    'source_name' => $row->source_name,
                    'published_at' => $row->published_at,
                    'title' => $row->title,
                    'url' => $row->url,
                    'excerpt' => $showExcerpt ? \Illuminate\Support\Str::limit((string) ($row->excerpt ?: $row->content), 180) : null,
                ];
            })
            ->all();
    }

    private function sampleSocial($query, int $limit, bool $showExcerpt): array
    {
        return $query->orderByDesc('posted_at')
            ->limit($limit)
            ->get(['id', 'platform', 'author_name', 'posted_at', 'post_url', 'content', 'raw_json'])
            ->map(function ($row) use ($showExcerpt) {
                return [
                    'id' => (int) $row->id,
                    'platform' => $row->platform,
                    'author_name' => $row->author_name,
                    'posted_at' => $row->posted_at,
                    'post_url' => $row->post_url,
                    'excerpt' => $showExcerpt ? \Illuminate\Support\Str::limit((string) ($row->content ?? ''), 180) : null,
                ];
            })
            ->all();
    }
}
