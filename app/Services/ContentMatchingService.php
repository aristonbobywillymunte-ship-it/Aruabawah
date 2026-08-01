<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Project;
use App\Models\SocialMediaItem;
use Illuminate\Support\Facades\Log;
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

        return [
            'articles' => (int) $project->articles()->count(),
            'social' => (int) $project->socialMediaItems()->count(),
        ];
    }

    /**
     * Resync project content using the current project filters.
     *
     * @return array{match: array, social_sync: array}
     */
    public function resyncProjectContent(Project $project): array
    {
        $matchResult = $this->matchExistingContentForProject($project);
        $portalArticleIds = $matchResult['matched_ids'] ?? [];

        $socialResult = $this->syncProjectSocialContent($project);
        $socialArticleIds = $socialResult['mirrored_article_ids'] ?? [];

        $combinedArticleIds = array_values(array_unique(array_merge($portalArticleIds, $socialArticleIds)));
        $project->articles()->sync($combinedArticleIds);

        Log::info('[Project Resync] Combined portal and social mirrored articles synced to project_articles.', [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'total_articles' => count($combinedArticleIds),
            'portal_articles' => count($portalArticleIds),
            'social_articles' => count($socialArticleIds),
        ]);

        return [
            'match' => $matchResult,
            'social_sync' => $socialResult,
            'total_synced' => count($combinedArticleIds),
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
                $item->author_name ?? null,
                $item->content ?? null,
                $item->raw_json ?? null,
                $item->post_url ?? null,
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
            if ($this->matchesExcludeKeywords($keywordSets['exclude'], $contentToMatch)) {
                continue;
            }

            // Must match primary keywords
            if (!$this->matchesAnyKeyword($keywordSets['primary'], $contentToMatch)) {
                continue;
            }

            // If context keywords exist, must match at least one context keyword as well
            if (!empty($keywordSets['context']) && !$this->matchesAnyKeyword($keywordSets['context'], $contentToMatch)) {
                continue;
            }

            $matchedProjectIds[] = $projectId;
        }

        // Do not force-attach the discovery project ID. 
        // Even the project that initiated the scrape must strictly match the keywords.
        
        $uniqueMatchedIds = array_unique($matchedProjectIds);

        if ($isArticle && $uniqueMatchedIds !== []) {
            foreach ($uniqueMatchedIds as $projectId) {
                $project = Project::find($projectId);
                if ($project) {
                    $project->articles()->syncWithoutDetaching([$item->id]);
                }
            }
        } elseif (!$isArticle && $uniqueMatchedIds !== []) {
            foreach ($uniqueMatchedIds as $projectId) {
                $project = Project::find($projectId);
                if ($project) {
                    $project->socialMediaItems()->syncWithoutDetaching([$item->id]);
                }
            }
        }
        
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
                'matched_ids' => [],
                'skipped' => true,
                'reason' => 'project_inactive_or_deleted',
            ];
        }

        $primaryKeywords = $project->scrapeKeywordVariants();
        $contextKeywords = $project->scrapeContextKeywordVariants();
        $excludeKeywords = $project->scrapeExcludeKeywords();

        if ($primaryKeywords === []) {
            return [
                'articles_linked' => 0,
                'matched_ids' => [],
                'skipped' => true,
                'reason' => 'no_keywords',
            ];
        }

        $matchedIds = [];
        Article::query()
            ->select(['id', 'title', 'content', 'source_name', 'category'])
            ->chunkById(250, function ($articles) use ($project, $primaryKeywords, $contextKeywords, $excludeKeywords, &$matchedIds) {
                foreach ($articles as $article) {
                    if ($this->isSocialMirroredArticle($article)) {
                        continue;
                    }

                    $content = ($article->title ?? '') . "\n" . ($article->content ?? '');
                    if ($this->shouldSkipGovernorArticleMatch($project, $content)) {
                        continue;
                    }

                    if ($this->matchesExcludeKeywords($excludeKeywords, $content)) {
                        continue;
                    }

                    // Must match primary keywords
                    if (!$this->matchesAnyKeyword($primaryKeywords, $content)) {
                        continue;
                    }

                    // Must match context keywords if they are configured
                    if (!empty($contextKeywords) && !$this->matchesAnyKeyword($contextKeywords, $content)) {
                        continue;
                    }

                    $matchedIds[] = $article->id;
                }
            });

        $matchedIds = array_values(array_unique($matchedIds));

        Log::info('[Project Matching] Existing portal articles matched for project.', [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'articles_linked' => count($matchedIds),
        ]);

        return [
            'articles_linked' => count($matchedIds),
            'matched_ids' => $matchedIds,
            'skipped' => false,
            'reason' => null,
        ];
    }

    /**
     * Keep approved portal discoveries attached to the project even when the
     * strict keyword matcher would otherwise prune them during a resync.
     *
     * @return array<int>
     */
    protected function approvedCandidateArticleIds(Project $project): array
    {
        $candidateRows = \Illuminate\Support\Facades\DB::table('candidate_links')
            ->where('project_id', $project->id)
            ->where('status', 'approved')
            ->where(function ($query) {
                $query->whereNotNull('canonical_url')
                    ->orWhereNotNull('url');
            })
            ->get(['url', 'canonical_url']);

        if ($candidateRows->isEmpty()) {
            return [];
        }

        $urls = $candidateRows
            ->flatMap(function ($row) {
                return array_filter([
                    $row->canonical_url ?? null,
                    $row->url ?? null,
                ]);
            })
            ->map(fn ($url) => trim((string) $url))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($urls === []) {
            return [];
        }

        return Article::query()
            ->where(function ($query) use ($urls) {
                $query->whereIn('canonical_url', $urls)
                    ->orWhereIn('url', $urls);
            })
            ->get(['id', 'source_name', 'category'])
            ->reject(fn (Article $article) => $this->isSocialMirroredArticle($article))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Re-evaluate social-media matches for a project filter and sync
     * relationship records to the project_social_media_items pivot table.
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
                'matched_social_ids' => [],
                'mirrored_article_ids' => [],
            ];
        }

        $primaryKeywords = $project->scrapeKeywordVariants();
        $contextKeywords = $project->scrapeContextKeywordVariants();
        $excludeKeywords = $project->scrapeExcludeKeywords();

        if ($primaryKeywords === []) {
            $project->socialMediaItems()->sync([]);
            return [
                'detached' => 0,
                'attached' => 0,
                'skipped' => false,
                'reason' => 'no_keywords',
                'matched_social_ids' => [],
                'mirrored_article_ids' => [],
            ];
        }

        $matchedIds = [];
        SocialMediaItem::query()
            ->select(['id', 'author_name', 'content', 'raw_json', 'post_url'])
            ->chunkById(250, function ($items) use ($primaryKeywords, $contextKeywords, $excludeKeywords, &$matchedIds) {
                foreach ($items as $item) {
                    $content = $this->buildSocialMatchText(
                        $item->author_name ?? null,
                        $item->content ?? null,
                        $item->raw_json ?? null,
                        $item->post_url ?? null,
                    );

                    if ($this->matchesExcludeKeywords($excludeKeywords, $content)) {
                        continue;
                    }

                    // Must match primary keywords
                    if (!$this->matchesAnyKeyword($primaryKeywords, $content)) {
                        continue;
                    }

                    // Must match context keywords if configured
                    if (!empty($contextKeywords) && !$this->matchesAnyKeyword($contextKeywords, $content)) {
                        continue;
                    }

                    $matchedIds[] = $item->id;
                }
        });

        $matchedIds = array_values(array_unique($matchedIds));
        $project->socialMediaItems()->sync($matchedIds);

        // Fetch mirrored article IDs for project_articles syncing
        $postUrls = SocialMediaItem::whereIn('id', $matchedIds)->pluck('post_url')->filter()->toArray();
        $mirroredArticleIds = [];
        if (!empty($postUrls)) {
            $mirroredArticleIds = Article::whereIn('url', $postUrls)
                ->orWhereIn('canonical_url', $postUrls)
                ->pluck('id')
                ->toArray();
        }

        Log::info('[Project Matching] Existing social media items matched and synced to pivot table.', [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'social_linked' => count($matchedIds),
            'mirrored_articles_linked' => count($mirroredArticleIds),
        ]);

        return [
            'detached' => 0,
            'attached' => count($matchedIds),
            'skipped' => false,
            'reason' => null,
            'matched_social_ids' => $matchedIds,
            'mirrored_article_ids' => $mirroredArticleIds,
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

        if (preg_match($pattern, $text) === 1) {
            return true;
        }

        // Allow compound-word variants such as "walikota" <-> "wali kota"
        // without loosening the matcher for unrelated short fragments.
        $keywordCompact = preg_replace('/\s+/u', '', Str::lower($keyword));
        $textCompact = preg_replace('/\s+/u', '', Str::lower($text));

        if ($keywordCompact === '' || $textCompact === '') {
            return false;
        }

        return str_contains($textCompact, $keywordCompact);
    }

    /**
     * Build a conservative matching text for social items.
     * Use the post/caption text as the primary source, then add explicit
     * keyword-like fields from the payload so the project matcher follows the
     * same rule as the dashboard: social sources match from caption/post text,
     * while hashtags remain a supporting signal.
     */
    protected function buildSocialMatchText(?string $authorName, ?string $content, mixed $rawJson, ?string $postUrl = null): string
    {
        $parts = [];

        if (is_string($authorName)) {
            $author = trim($authorName);
            if ($author !== '') {
                $parts[] = $author;
            }
        }

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

        if (is_string($postUrl)) {
            $trimmedUrl = trim($postUrl);
            if ($trimmedUrl !== '') {
                $parts[] = $trimmedUrl;
            }
        }

        return implode("\n", array_values(array_unique($parts)));
    }

    protected function isSocialMirroredArticle(Article $article): bool
    {
        $source = strtolower(trim((string) ($article->source_name ?? '')));
        $category = strtolower(trim((string) ($article->category ?? '')));

        return in_array($source, ['facebook', 'instagram', 'tiktok'], true)
            || $category === 'social';
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
