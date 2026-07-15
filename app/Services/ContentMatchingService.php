<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Project;
use App\Models\SocialMediaItem;
use Illuminate\Support\Facades\Log;

class ContentMatchingService
{
    /**
     * Match a global article or social media item against all active projects
     * and link it via project_articles or project_social_media_items pivot.
     *
     * @param Article|SocialMediaItem $item The item to match
     * @param int|null $discoveryProjectId The ID of the project that discovered the item, if any
     * @return array Array of matched project IDs
     */
    public function crossLinkToActiveProjects($item, ?int $discoveryProjectId = null): array
    {
        /*
         * Articles are global records. A discovered article must be linked to every 
         * active project it matches through project_articles. 
         * The discovery project is not the exclusive owner.
         */
        
        $isArticle = $item instanceof Article;
        
        // Prepare text content for regex matching
        if ($isArticle) {
            $contentToMatch = ($item->title ?? '') . "\n" . ($item->content ?? '');
        } else {
            $contentToMatch = ($item->author_name ?? '') . "\n" . ($item->content ?? '');
        }
        
        $matchedProjectIds = [];
        
        if ($discoveryProjectId) {
            $matchedProjectIds[] = $discoveryProjectId;
        }

        $allProjects = Project::where('is_active', true)->get();
        
        foreach ($allProjects as $project) {
            if (in_array($project->id, $matchedProjectIds, true)) {
                continue;
            }
            
            $keywords = $project->scrapeKeywords();
            
            foreach ($keywords as $kw) {
                if ($this->isStrictMatch($kw, $contentToMatch)) {
                    $matchedProjectIds[] = $project->id;
                    break;
                }
            }
        }
        
        $uniqueMatchedIds = array_unique($matchedProjectIds);
        
        foreach ($uniqueMatchedIds as $pid) {
            $proj = Project::find($pid);
            if ($proj) {
                if ($isArticle) {
                    $proj->articles()->syncWithoutDetaching([$item->id]);
                } else {
                    $proj->socialMediaItems()->syncWithoutDetaching([$item->id]);
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
     * Link existing global content to a newly created or updated active project.
     * This is intentionally not scraping: it only connects existing database
     * articles/social posts that already match the project's keywords.
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

        $keywords = $project->scrapeKeywords();
        if ($keywords === []) {
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
            ->chunkById(250, function ($articles) use ($project, $keywords, &$articlesLinked) {
                foreach ($articles as $article) {
                    if ($project->articles()->where('articles.id', $article->id)->exists()) {
                        continue;
                    }

                    $content = ($article->title ?? '') . "\n" . ($article->content ?? '');
                    foreach ($keywords as $keyword) {
                        if ($this->isStrictMatch($keyword, $content)) {
                            $project->articles()->syncWithoutDetaching([$article->id]);
                            $articlesLinked++;
                            break;
                        }
                    }
                }
            });

        $socialLinked = 0;
        SocialMediaItem::query()
            ->select(['id', 'author_name', 'content'])
            ->chunkById(250, function ($items) use ($project, $keywords, &$socialLinked) {
                foreach ($items as $item) {
                    if ($project->socialMediaItems()->where('social_media_items.id', $item->id)->exists()) {
                        continue;
                    }

                    $content = ($item->author_name ?? '') . "\n" . ($item->content ?? '');
                    foreach ($keywords as $keyword) {
                        if ($this->isStrictMatch($keyword, $content)) {
                            $project->socialMediaItems()->syncWithoutDetaching([$item->id]);
                            $socialLinked++;
                            break;
                        }
                    }
                }
            });

        Log::info('[Project Matching] Existing content linked to project.', [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'keywords' => $keywords,
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
}
