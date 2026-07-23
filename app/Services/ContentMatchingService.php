<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Project;
use App\Models\SocialMediaItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ContentMatchingService
{
    /**
     * Count global content that matches the current project filters.
     *
     * @return array{articles:int, social:int}
     */
    public function countMatchingContentForProject(Project $project): array
    {
        $project = $project->fresh();

        if (! $project || ! $project->is_active || $project->trashed()) {
            return ['articles' => 0, 'social' => 0];
        }

        $cacheKey = 'content_match_counts:' . $project->id . ':' . md5(json_encode([
            'updated_at' => optional($project->updated_at)->timestamp,
            'topics' => $project->topics ?? [],
            'context' => $project->context_keywords ?? [],
            'exclude' => $project->exclude_keywords ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return Cache::remember($cacheKey, 300, function () use ($project) {
            $primaryKeywords = $project->scrapeKeywordVariants();
            $contextKeywords = $project->scrapeContextKeywordVariants();
            $matchKeywords = array_values(array_unique(array_filter(array_merge($primaryKeywords, $contextKeywords))));
            $excludeKeywords = $project->scrapeExcludeKeywords();

            if ($matchKeywords === []) {
                return ['articles' => 0, 'social' => 0];
            }

            $articleCount = 0;
            Article::query()
                ->select(['id', 'title', 'content'])
                ->chunkById(250, function ($articles) use ($matchKeywords, $excludeKeywords, &$articleCount) {
                    foreach ($articles as $article) {
                        $content = ($article->title ?? '') . "\n" . ($article->content ?? '');

                        if ($this->matchesExcludeKeywords($excludeKeywords, $content)) {
                            continue;
                        }

                        if ($this->matchesAnyKeyword($matchKeywords, $content)) {
                            $articleCount++;
                        }
                    }
                });

            $socialCount = 0;
            SocialMediaItem::query()
                ->select(['id', 'author_name', 'content', 'raw_json'])
                ->chunkById(250, function ($items) use ($matchKeywords, $excludeKeywords, &$socialCount) {
                    foreach ($items as $item) {
                        $content = $this->buildSocialMatchText(
                            $item->content ?? null,
                            $item->raw_json ?? null,
                        );

                        if ($this->matchesExcludeKeywords($excludeKeywords, $content)) {
                            continue;
                        }

                        if ($this->matchesAnyKeyword($matchKeywords, $content)) {
                            $socialCount++;
                        }
                    }
                });

            return [
                'articles' => $articleCount,
                'social' => $socialCount,
            ];
        });
    }

    /**
     * Resync project content using the current project filters.
     *
     * @return array{match: array, social_sync: array}
     */
    public function resyncProjectContent(Project $project): array
    {
        return [
            'match' => $this->matchExistingContentForProject($project),
            'social_sync' => $this->syncProjectSocialContent($project),
        ];
    }

    /**
     * Match a global article or social media item against all active projects
     * using project filters.
     *
     * The runtime now treats the global tables as the source of truth, so this
     * method only resolves matching project IDs for audit/logging purposes.
     *
     * @param Article|SocialMediaItem $item The item to match
     * @param int|null $discoveryProjectId The ID of the project that discovered the item, if any
     * @return array Array of matched project IDs
     */
    public function crossLinkToActiveProjects($item, ?int $discoveryProjectId = null): array
    {
        $isArticle = $item instanceof Article;
        
        // Prepare text content for regex matching
        if ($isArticle) {
            $contentToMatch = ($item->title ?? '') . "\n" . ($item->content ?? '');
        } else {
            $contentToMatch = $this->buildSocialMatchText(
                $item->content ?? null,
                $item->raw_json ?? null,
            );
        }
        
        $matchedProjectIds = [];

        $allProjects = Project::where('is_active', true)->get();
        $projectKeywordMap = [];

        foreach ($allProjects as $project) {
            $projectKeywordMap[$project->id] = [
                'primary' => $project->scrapeKeywordVariants(),
                'context' => $project->scrapeContextKeywordVariants(),
                'exclude' => $project->scrapeExcludeKeywords(),
            ];
        }

        foreach ($projectKeywordMap as $projectId => $keywordSets) {
            $matchKeywords = array_values(array_unique(array_filter(array_merge($keywordSets['primary'], $keywordSets['context']))));

            if ($this->matchesExcludeKeywords($keywordSets['exclude'], $contentToMatch)) {
                continue;
            }

            if (! $this->matchesAnyKeyword($matchKeywords, $contentToMatch)) {
                continue;
            }

            foreach ($matchKeywords as $kw) {
                if ($this->isStrictMatch($kw, $contentToMatch)) {
                    $matchedProjectIds[] = $projectId;
                    break;
                }
            }
        }

        if ($discoveryProjectId && $isArticle && ! in_array($discoveryProjectId, $matchedProjectIds, true)) {
            $matchedProjectIds[] = $discoveryProjectId;
        }
        
        $uniqueMatchedIds = array_unique($matchedProjectIds);
        
        if (count($uniqueMatchedIds) > 1) {
            Log::info("[Cross-Project Matching] Item {$item->id} matched multiple projects", [
                'type' => $isArticle ? 'Article' : 'SocialMediaItem',
                'matched_projects' => $uniqueMatchedIds,
                'discovery_project' => $discoveryProjectId
            ]);
        }
        
        return $uniqueMatchedIds;
    }

    /**
     * Re-evaluate existing global content against a project filter.
     * This intentionally does not write relationship records; the dashboard
     * reads directly from the global tables using the active project filter.
     */
    public function matchExistingContentForProject(Project $project): array
    {
        $project = $project->fresh();

        if (! $project || ! $project->is_active || $project->trashed()) {
            return [
                'articles_linked' => 0,
                'social_linked' => 0,
                'skipped' => true,
                'reason' => 'project_inactive_or_deleted',
            ];
        }

        $primaryKeywords = $project->scrapeKeywordVariants();
        $contextKeywords = $project->scrapeContextKeywordVariants();
        $matchKeywords = array_values(array_unique(array_filter(array_merge($primaryKeywords, $contextKeywords))));
        $excludeKeywords = $project->scrapeExcludeKeywords();

        if ($matchKeywords === []) {
            return [
                'articles_linked' => 0,
                'social_linked' => 0,
                'skipped' => true,
                'reason' => 'no_keywords',
            ];
        }

        $articlesLinked = 0;
        Article::query()
            ->select(['id', 'title', 'content'])
            ->chunkById(250, function ($articles) use ($project, $matchKeywords, $excludeKeywords, &$articlesLinked) {
                foreach ($articles as $article) {
                    $content = ($article->title ?? '') . "\n" . ($article->content ?? '');
                    if ($this->shouldSkipGovernorArticleMatch($project, $content)) {
                        continue;
                    }

                    if ($this->matchesExcludeKeywords($excludeKeywords, $content)) {
                        continue;
                    }

                    if ($this->matchesAnyKeyword($matchKeywords, $content)) {
                        $articlesLinked++;
                    }
                }
            });

        $socialLinked = 0;
        SocialMediaItem::query()
            ->select(['id', 'author_name', 'content', 'raw_json'])
            ->chunkById(250, function ($items) use ($project, $matchKeywords, $excludeKeywords, &$socialLinked) {
                foreach ($items as $item) {
                    $content = $this->buildSocialMatchText(
                        $item->content ?? null,
                        $item->raw_json ?? null,
                    );
                    if ($this->matchesExcludeKeywords($excludeKeywords, $content)) {
                        continue;
                    }

                    if ($this->matchesAnyKeyword($matchKeywords, $content)) {
                        $socialLinked++;
                    }
                }
            });

        Log::info('[Project Matching] Existing content evaluated for project filter.', [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'primary_keywords' => $primaryKeywords,
            'exclude_keywords' => $excludeKeywords,
            'articles_linked' => $articlesLinked,
            'social_linked' => $socialLinked,
        ]);

        return [
            'articles_linked' => $articlesLinked,
            'social_linked' => $socialLinked,
            'skipped' => false,
            'reason' => null,
        ];
    }

    /**
     * Re-evaluate social-media matches for a project filter without writing
     * relationship records.
     */
    public function syncProjectSocialContent(Project $project): array
    {
        $project = $project->fresh();

        if (! $project || ! $project->is_active || $project->trashed()) {
            return [
                'detached' => 0,
                'attached' => 0,
                'skipped' => true,
                'reason' => 'project_inactive_or_deleted',
            ];
        }

        $primaryKeywords = $project->scrapeKeywordVariants();
        $contextKeywords = $project->scrapeContextKeywordVariants();
        $excludeKeywords = $project->scrapeExcludeKeywords();
        $matchKeywords = array_values(array_unique(array_filter(array_merge($primaryKeywords, $contextKeywords))));

        if ($primaryKeywords === []) {
            return [
                'detached' => 0,
                'attached' => 0,
                'skipped' => false,
                'reason' => 'no_keywords',
            ];
        }

        $matchedIds = [];
        SocialMediaItem::query()
            ->select(['id', 'author_name', 'content', 'raw_json'])
            ->chunkById(250, function ($items) use ($matchKeywords, $excludeKeywords, &$matchedIds) {
                foreach ($items as $item) {
                    $content = $this->buildSocialMatchText(
                        $item->content ?? null,
                        $item->raw_json ?? null,
                    );

                    if ($this->matchesExcludeKeywords($excludeKeywords, $content)) {
                        continue;
                    }

                    if ($this->matchesAnyKeyword($matchKeywords, $content)) {
                        $matchedIds[] = $item->id;
                    }
                }
        });

        $matchedIds = array_values(array_unique($matchedIds));
        return [
            'detached' => 0,
            'attached' => count($matchedIds),
            'skipped' => false,
            'reason' => null,
        ];
    }
    
    /**
     * Performs a strict regex-based word boundary match.
     * Prevents false positives like "Jurgen Klopp" matching "Seno Aji"
     * or short keywords like "aji" matching "wajib".
     * Handles Unicode and typographical variants (e.g., apostrophes).
     *
     * @param string $keyword
     * @param string $text
     * @return bool
     */
    public function isStrictMatch(string $keyword, string $text): bool
    {
        $keyword = trim($keyword);
        
        // Safety: Reject extremely short keywords to prevent catastrophic false positives,
        // unless it's a specific known acronym. Generally < 3 chars is unsafe for global search.
        if (mb_strlen($keyword) <= 2) {
            return false;
        }
        
        // Normalize apostrophes in both keyword and text to ASCII standard
        $keyword = preg_replace('/[’‘`´]/u', "'", $keyword);
        $text = preg_replace('/[’‘`´]/u', "'", $text);
        
        // Normalize whitespaces
        $keyword = preg_replace('/\s+/u', ' ', $keyword);
        
        // Escape keyword for regex
        $escapedKeyword = preg_quote($keyword, '/');
        
        // Build regex with word boundaries (\b doesn't always work perfectly for all unicode,
        // but with 'u' flag it's much better. We also use (?<![\p{L}\p{N}]) and (?![\p{L}\p{N}])
        // which means "not preceded or followed by a letter or number" to be extremely precise
        // across non-ascii boundaries).
        $pattern = '/(?<![\p{L}\p{N}])' . $escapedKeyword . '(?![\p{L}\p{N}])/iu';

        return preg_match($pattern, $text) === 1;
    }

    /**
     * Build a conservative matching text for social items.
     * Use the post/caption text as the primary source, then add explicit
     * keyword-like fields from the payload so the project matcher follows the
     * same rule as the dashboard: social sources match from caption/post text,
     * while hashtags remain a supporting signal.
     */
    protected function buildSocialMatchText(?string $content, mixed $rawJson): string
    {
        $parts = [];

        if (is_string($content)) {
            $caption = trim($content);
            if ($caption !== '') {
                $parts[] = $caption;
            }
        }

        $decoded = null;
        if (is_string($rawJson)) {
            $decoded = json_decode($rawJson, true);
        } elseif (is_array($rawJson)) {
            $decoded = $rawJson;
        }

        foreach (['hashtags', 'tags'] as $key) {
            $value = is_array($decoded) ? ($decoded[$key] ?? null) : null;
            if (is_array($value)) {
                foreach ($value as $entry) {
                    if (is_array($entry)) {
                        $entry = $entry['name'] ?? $entry['tag'] ?? $entry['text'] ?? $entry['value'] ?? null;
                    }

                    if (is_scalar($entry) || $entry === null) {
                        $trimmed = trim((string) $entry);
                        if ($trimmed !== '') {
                            $parts[] = $trimmed;
                        }
                    }
                }
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $trimmed = trim((string) $value);
                if ($trimmed !== '') {
                    $parts[] = $trimmed;
                }
            }
        }

        if (is_string($content) && preg_match_all('/(?<!\w)#([^\s#]+)/u', $content, $matches)) {
            foreach ($matches[1] as $tag) {
                $parts[] = $tag;
            }
        }

        return implode("\n", array_values(array_unique($parts)));
    }

    protected function matchesAllKeywords(array $keywords, string $text): bool
    {
        $keywords = array_values(array_filter(array_map('trim', $keywords)));
        if ($keywords === []) {
            return true;
        }

        foreach ($keywords as $keyword) {
            if (! $this->isStrictMatch($keyword, $text)) {
                return false;
            }
        }

        return true;
    }

    protected function matchesAnyKeyword(array $keywords, string $text): bool
    {
        $keywords = array_values(array_filter(array_map('trim', $keywords)));
        if ($keywords === []) {
            return false;
        }

        foreach ($keywords as $keyword) {
            if ($this->isStrictMatch($keyword, $text)) {
                return true;
            }
        }

        return false;
    }

    protected function matchesExcludeKeywords(array $keywords, string $text): bool
    {
        $keywords = array_values(array_filter(array_map('trim', $keywords)));
        if ($keywords === []) {
            return false;
        }

        foreach ($keywords as $keyword) {
            if ($this->isStrictMatch($keyword, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prevent governor projects from absorbing wagub-only articles just because
     * they share a broad regional keyword such as "Kalimantan Timur".
     */
    protected function shouldSkipGovernorArticleMatch(Project $project, string $content): bool
    {
        $projectName = Str::lower($project->name ?? '');
        $contentLower = Str::lower($content);

        if (! Str::contains($projectName, 'gubernur')) {
            return false;
        }

        $hasWagubSignal = Str::contains($contentLower, [
            'wakil gubernur',
            'wagub',
            'seno aji',
        ]);

        if (! $hasWagubSignal) {
            return false;
        }

        $hasStrongGovernorSignal = preg_match('/(?<!wakil\s)gubernur\s+kaltim/iu', $contentLower) === 1
            || preg_match('/(?<!wakil\s)gubernur\s+kalimantan\s+timur/iu', $contentLower) === 1
            || Str::contains($contentLower, [
                'rudy mas',
                'rudy mas\'ud',
                'rudy mas’ud',
            ]);

        // If the article is clearly about Wagub but only has broad governor-region
        // wording, keep it out of the governor project.
        return ! $hasStrongGovernorSignal;
    }
}
