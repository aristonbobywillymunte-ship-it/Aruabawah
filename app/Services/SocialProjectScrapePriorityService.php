<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SocialProjectScrapePriorityService
{
    public function prioritize(Collection $projects): Collection
    {
        return $this->filterEligible($projects)
            ->sortBy(fn (Project $project) => $this->prioritySortKey($project), SORT_REGULAR, false)
            ->values();
    }

    private function filterEligible(Collection $projects): Collection
    {
        return $projects
            ->filter(fn (Project $project) => (bool) $project->is_active && $project->hasScrapeKeywords())
            ->values();
    }

    private function prioritySortKey(Project $project): array
    {
        $createdAt = $project->created_at?->timestamp ?? 0;
        $lastSocialAttemptAt = $this->lastSocialAttemptTimestamp($project);
        $lastSocialDataAt = $this->lastSocialDataTimestamp($project);
        $lastSocialAt = $lastSocialDataAt ?? $lastSocialAttemptAt;

        return $lastSocialAttemptAt === null
            ? [0, $createdAt, $project->id]
            : [1, $lastSocialAt, $createdAt, $project->id];
    }

    private function lastSocialAttemptTimestamp(Project $project): ?int
    {
        $value = DB::table('apify_dispatch_states')
            ->where('project_id', $project->id)
            ->whereIn(DB::raw('lower(platform)'), ['facebook', 'instagram', 'tiktok'])
            ->max(DB::raw('coalesce(completed_at, started_at, queued_at)'));

        if (! $value) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->timestamp;
        } catch (\Throwable) {
            return null;
        }
    }

    private function lastSocialDataTimestamp(Project $project): ?int
    {
        $value = DB::table('project_social_media_items')
            ->where('project_id', $project->id)
            ->max('created_at');

        if (! $value) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->timestamp;
        } catch (\Throwable) {
            return null;
        }
    }
}
