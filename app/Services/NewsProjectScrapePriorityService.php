<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Menentukan urutan prioritas scraping portal berita antar proyek.
 *
 * Prioritas:
 *  1. Proyek yang belum pernah discrape sama sekali (first_news_scrape_attempt_at IS NULL)
 *     → diurutkan berdasarkan created_at ASC (yang lebih lama pertama)
 *  2. Proyek yang sudah pernah discrape
 *     → diurutkan berdasarkan news_last_scraped_at ASC (yang paling lama tidak discrape, paling dulu)
 *     → jika news_last_scraped_at NULL, fallback ke first_news_scrape_attempt_at ASC
 *
 * Dengan ini semua proyek aktif mendapat giliran secara merata (round-robin berbasis waktu).
 */
class NewsProjectScrapePriorityService
{
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

    /**
     * Catat waktu terakhir scraping portal berhasil untuk proyek ini.
     * Dipanggil setelah satu siklus scraping selesai per proyek.
     */
    public function recordLastScraped(Project $project): void
    {
        $now = now();
        Project::query()
            ->whereKey($project->id)
            ->update(['news_last_scraped_at' => $now]);

        $project->forceFill(['news_last_scraped_at' => $now]);
        $project->syncOriginalAttribute('news_last_scraped_at');
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

    /**
     * Sort key untuk mengurutkan proyek:
     * [0, created_at, id]   → proyek baru (belum pernah discrape) → prioritas tertinggi
     * [1, last_scraped, id] → proyek lama → yang paling lama tidak discrape → diutamakan
     */
    private function prioritySortKey(Project $project): array
    {
        $pending = $this->isPendingFirstAttempt($project);
        $createdAt = $project->created_at?->timestamp ?? 0;

        if ($pending) {
            return [0, $createdAt, $project->id];
        }

        // Ambil news_last_scraped_at dari model (sudah di-load dari DB)
        $lastScrapedAt = $project->news_last_scraped_at;
        if ($lastScrapedAt instanceof \DateTimeInterface) {
            $lastScrapedTimestamp = $lastScrapedAt->getTimestamp();
        } elseif (is_string($lastScrapedAt) && $lastScrapedAt !== '') {
            try {
                $lastScrapedTimestamp = \Carbon\Carbon::parse($lastScrapedAt)->timestamp;
            } catch (\Throwable) {
                $lastScrapedTimestamp = null;
            }
        } else {
            $lastScrapedTimestamp = null;
        }

        // Fallback ke first_news_scrape_attempt_at jika news_last_scraped_at belum ada
        if ($lastScrapedTimestamp === null) {
            $lastScrapedTimestamp = $this->attemptTimestamp($project) ?? $createdAt;
        }

        return [1, $lastScrapedTimestamp, $project->id];
    }
}
