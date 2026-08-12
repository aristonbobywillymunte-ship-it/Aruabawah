<?php

namespace App\Services\Scraping;

use App\Jobs\ApifyScrapingJob;
use App\Models\ApifyActor;
use App\Models\ApifyDispatchState;
use App\Models\Project;
use App\Models\SocialMediaItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SocialCommentScraperDispatcher
{
    private const MAX_URLS_PER_DISPATCH = 3;
    private const COMMENT_SCRAPER_STALE_MINUTES = 45;
    private const IN_PROGRESS_TTL_MINUTES = 30;

    public function dispatchEligible(Project $project, string $platform, bool $forceDispatch = true): array
    {
        $platform = $this->normalizePlatform($platform);
        if ($platform === null) {
            return $this->result(false, 'invalid_platform');
        }

        $actor = $this->resolveCommentScraperActor($project, $platform);
        if (! $actor) {
            return $this->result(false, 'comment_actor_missing');
        }

        if ($this->hasActiveCommentScraper($project->id, $platform)) {
            return $this->result(false, 'active_comment_scraper');
        }

        $candidates = $this->resolveCandidateUrls($project, $platform);
        if ($candidates->isEmpty()) {
            return $this->result(false, 'no_unprocessed_urls');
        }

        $queuedUrls = [];
        foreach ($candidates as $url) {
            $urlHash = md5($url);
            Cache::put(
                'comments_scraping_in_progress:' . $urlHash,
                true,
                now()->addMinutes(self::IN_PROGRESS_TTL_MINUTES)
            );
            $queuedUrls[] = $url;

            if (count($queuedUrls) >= self::MAX_URLS_PER_DISPATCH) {
                break;
            }
        }

        if ($queuedUrls === []) {
            return $this->result(false, 'no_unprocessed_urls');
        }

        $wasDispatched = ApifyScrapingJob::dispatchSafely([
            'platform' => $platform,
            'keyword' => $queuedUrls[0],
            'keywords' => $queuedUrls,
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'force_dispatch' => $forceDispatch,
            'no_telegram' => true,
        ]);

        if (! $wasDispatched) {
            foreach ($queuedUrls as $url) {
                Cache::forget('comments_scraping_in_progress:' . md5($url));
            }

            return $this->result(false, 'dispatch_blocked', count($queuedUrls));
        }

        return $this->result(true, null, count($queuedUrls), $actor->id);
    }

    public function hasEnabledCommentScraperActor(Project $project, string $platform): bool
    {
        return $this->resolveCommentScraperActor($project, $platform) !== null;
    }

    public function resolveCandidateUrls(Project $project, string $platform): Collection
    {
        $platform = $this->normalizePlatform($platform) ?? $platform;
        $platformLower = strtolower($platform);

        $query = SocialMediaItem::whereHas('projects', function ($q) use ($project) {
            $q->where('projects.id', $project->id);
        })
            ->where('platform', $platform)
            ->whereNotNull('post_url')
            ->where('comments_checked', false)
            ->orderBy('posted_at', 'desc')
            ->orderBy('id', 'desc');

        if ($platformLower === 'tiktok') {
            $query->where('post_url', 'like', '%tiktok.com/@%')
                ->where('post_url', 'like', '%/video/%');
        } elseif ($platformLower === 'instagram') {
            $query->where('post_url', 'like', '%instagram.com/%');
        } elseif ($platformLower === 'facebook') {
            $query->where('post_url', 'like', '%facebook.com/%');
        }

        $doneCount = 0;
        $inProgressCount = 0;
        return $query->get(['post_url'])
            ->filter(function ($item) use (&$doneCount, &$inProgressCount) {
                $url = trim((string) $item->post_url);
                if ($url === '') {
                    return false;
                }

                $urlHash = md5($url);
                $hasDone = Cache::has('comments_scraped_for_post:' . $urlHash);
                $hasInProgress = Cache::has('comments_scraping_in_progress:' . $urlHash);

                if ($hasDone) {
                    $doneCount++;
                }

                if ($hasInProgress) {
                    $inProgressCount++;
                }

                return ! $hasDone && ! $hasInProgress;
            })
            ->values();
    }

    public function resolveCommentScraperActor(Project $project, string $platform): ?ApifyActor
    {
        if (! $project->package_id) {
            return null;
        }

        $project = $project->fresh(['package']);
        if (! $project?->package) {
            return null;
        }

        $packageActorIds = $project->package->enabledActors()
            ->where('function_type', 'Comment Scraper')
            ->where('platform', $platform)
            ->where('status', 'active')
            ->pluck('apify_actors.id')
            ->all();

        if ($packageActorIds === []) {
            return null;
        }

        return ApifyActor::query()
            ->whereIn('id', $packageActorIds)
            ->where('function_type', 'Comment Scraper')
            ->where('platform', $platform)
            ->where('status', 'active')
            ->first();
    }

    public function hasActiveCommentScraper(int $projectId, string $platform): bool
    {
        $actorIds = ApifyActor::query()
            ->where('function_type', 'Comment Scraper')
            ->where('platform', $platform)
            ->where('status', 'active')
            ->pluck('id');

        if ($actorIds->isEmpty()) {
            return false;
        }

        return ApifyDispatchState::query()
            ->where('project_id', $projectId)
            ->whereIn('actor_id', $actorIds)
            ->whereIn('status', ['queued', 'processing'])
            ->where(function ($query) {
                $staleThreshold = now()->subMinutes(self::COMMENT_SCRAPER_STALE_MINUTES);
                $query->where(function ($queued) use ($staleThreshold) {
                    $queued->where('status', 'queued')
                        ->where('queued_at', '>=', $staleThreshold);
                })->orWhere(function ($processing) use ($staleThreshold) {
                    $processing->where('status', 'processing')
                        ->where(function ($active) use ($staleThreshold) {
                            $active->where('started_at', '>=', $staleThreshold)
                                ->orWhere('updated_at', '>=', $staleThreshold);
                        });
                });
            })
            ->exists();
    }

    protected function normalizePlatform(string $platform): ?string
    {
        $platform = trim($platform);
        if ($platform === '') {
            return null;
        }

        return match (strtolower($platform)) {
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            default => null,
        };
    }

    protected function result(bool $dispatched, ?string $reason, int $count = 0, ?int $actorId = null): array
    {
        return [
            'dispatched' => $dispatched,
            'skipped' => ! $dispatched,
            'reason' => $reason,
            'count' => $count,
            'actor_id' => $actorId,
        ];
    }
}
