<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Collection;

class NewsProjectScrapePriorityService
{
    private ?array $lastScanTimestamps = null;

    public function filterEligible(Collection $projects): Collection
    {
        return $projects
            ->filter(fn (Project $project) => (bool) $project->is_active && $project->hasScrapeKeywords())
            ->values();
    }

    public function prioritize(Collection $projects): Collection
    {
        return $this->filterEligible($projects)
            ->sortBy(fn (Project $project) => $this->prioritySortKey($project), SORT_REGULAR, false)
            ->values();
    }

    public function recordAttempt(Project $project): bool
    {
        if (! $this->isPendingFirstAttempt($project)) {
            return false;
        }

        $timestamp = now();
        $updated = Project::query()
            ->whereKey($project->id)
            ->whereNull('first_news_scrape_attempt_at')
            ->update(['first_news_scrape_attempt_at' => $timestamp]);

        if ($updated > 0) {
            $project->forceFill(['first_news_scrape_attempt_at' => $timestamp]);
            $project->syncOriginalAttribute('first_news_scrape_attempt_at');
        }

        return $updated > 0;
    }

    public function hasAttemptRecord(Project $project): bool
    {
        return $this->attemptTimestamp($project) !== null;
    }

    public function attemptTimestamp(Project $project): ?int
    {
        $value = $project->first_news_scrape_attempt_at
            ?? Project::query()->whereKey($project->id)->value('first_news_scrape_attempt_at');

        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value) && $value !== '') {
            try {
                return \Carbon\Carbon::parse($value)->timestamp;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    public function isPendingFirstAttempt(Project $project): bool
    {
        return (bool) $project->is_active
            && $project->hasScrapeKeywords()
            && $this->attemptTimestamp($project) === null;
    }

    private function prioritySortKey(Project $project): array
    {
        $lastScanAt = $this->lastScanTimestamp($project);
        $pending = $this->isPendingFirstAttempt($project) && $lastScanAt === null;
        $createdAt = $project->created_at?->timestamp ?? 0;
        $attemptAt = $lastScanAt ?? $this->attemptTimestamp($project) ?? $createdAt;

        return $pending
            ? [0, $createdAt, $project->id]
            : [1, $attemptAt, $createdAt, $project->id];
    }

    private function lastScanTimestamp(Project $project): ?int
    {
        return $this->lastScanTimestamps()[$project->id] ?? null;
    }

    private function lastScanTimestamps(): array
    {
        if ($this->lastScanTimestamps !== null) {
            return $this->lastScanTimestamps;
        }

        $this->lastScanTimestamps = [];
        $logPath = storage_path('logs/portal-manual.log');

        if (! is_readable($logPath)) {
            return $this->lastScanTimestamps;
        }

        $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (! is_array($lines)) {
            return $this->lastScanTimestamps;
        }

        foreach ($lines as $line) {
            if (! str_contains($line, '[Portal] Project keyword processed.')
                && ! str_contains($line, '[Portal] Scraping candidate article details.')) {
                continue;
            }

            if (! preg_match('/^\[(?<time>[^\]]+)\].*"project_id":(?<project_id>\d+)/', $line, $match)) {
                continue;
            }

            try {
                $this->lastScanTimestamps[(int) $match['project_id']] = \Carbon\Carbon::parse($match['time'])->timestamp;
            } catch (\Throwable) {
                continue;
            }
        }

        return $this->lastScanTimestamps;
    }
}
