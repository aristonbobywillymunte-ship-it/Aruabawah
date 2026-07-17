<?php

namespace App\Services;

use App\Models\ApifyActor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ApifyActorRegistry
{
    public function primaryActors(): array
    {
        return [
            'facebook' => [
                'platform' => 'Facebook',
                'label' => 'Facebook Post Search',
                'menu_label' => 'Facebook Post Search',
                'actor_name' => 'Facebook Posts Search Scraper',
                'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
                'function_type' => 'Search Post',
                'default_keyword' => 'politik',
                'default_limit' => 50,
                'status' => 'active',
                'keyword_field_mapping' => 'searchQueries',
                'output_mapping' => '{"maxPosts":"{limit}","postTimeRange":"24h","proxyConfiguration":{"useApifyProxy":true},"searchQueries":["{keyword}"]}',
                'build' => 'latest',
                'timeout_seconds' => 10000,
                'no_timeout' => false,
                'interval_minutes' => 720,
                'memory_limit' => 1024,
                'range_mode' => '30d',
                'priority' => 1,
                'maximum_cost_per_run_usd' => 0.2000,
                'editable_fields' => ['default_keyword', 'default_limit', 'range_mode', 'priority'],
                'locked_fields' => ['platform', 'actor_slug', 'function_type'],
            ],
            'instagram' => [
                'platform' => 'Instagram',
                'label' => 'Instagram Keyword Search',
                'menu_label' => 'Instagram Keyword Search',
                'actor_name' => 'Instagram Hashtag Scraper',
                'actor_slug' => 'apify/instagram-hashtag-scraper',
                'function_type' => 'Search Post',
                'default_keyword' => null,
                'default_limit' => 50,
                'status' => 'active',
                'keyword_field_mapping' => 'hashtags',
                'output_mapping' => '{"hashtags":["{keyword}"],"resultsType":"posts","resultsLimit":"{limit}"}',
                'build' => 'latest',
                'timeout_seconds' => 10000,
                'no_timeout' => false,
                'interval_minutes' => 720,
                'memory_limit' => 1024,
                'range_mode' => '7d',
                'priority' => 2,
                'maximum_cost_per_run_usd' => 0.1500,
                'editable_fields' => ['default_keyword', 'default_limit', 'range_mode', 'priority'],
                'locked_fields' => ['platform', 'actor_slug', 'function_type'],
            ],
            'tiktok' => [
                'platform' => 'TikTok',
                'label' => 'TikTok Hashtag Scraper',
                'menu_label' => 'TikTok Hashtag Scraper',
                'actor_name' => 'TikTok Hashtag Scraper',
                'actor_slug' => 'clockworks/tiktok-hashtag-scraper',
                'function_type' => 'Search Post',
                'default_keyword' => null,
                'default_limit' => 50,
                'status' => 'active',
                'keyword_field_mapping' => 'hashtags',
                'output_mapping' => '{"hashtags":["{keyword}"],"resultsPerPage":"{limit}","shouldDownloadCovers":false,"shouldDownloadSlideshowImages":false,"shouldDownloadVideos":false,"downloadSubtitlesOptions":"NEVER_DOWNLOAD_SUBTITLES","proxyConfiguration":{"useApifyProxy":true}}',
                'build' => 'latest',
                'timeout_seconds' => 10000,
                'no_timeout' => false,
                'interval_minutes' => 720,
                'memory_limit' => 2048,
                'range_mode' => '7d',
                'priority' => 3,
                'maximum_cost_per_run_usd' => 0.1500,
                'editable_fields' => ['default_limit', 'range_mode', 'priority'],
                'locked_fields' => ['platform', 'actor_slug', 'function_type'],
            ],
        ];
    }

    public function legacyActors(): array
    {
        return [
            'facebook-legacy' => [
                'platform' => 'Facebook',
                'label' => 'Legacy / Inactive',
                'menu_label' => 'Legacy / Inactive',
                'actor_name' => 'Facebook Search Posts',
                'actor_slug' => 'scrapeflow/facebook-search-posts',
            ],
            'legacy-no-longer-used' => [
                'platform' => 'Threads',
                'label' => 'Legacy / Inactive',
                'menu_label' => 'Legacy / Inactive',
                'actor_name' => 'Threads Search Scraper',
                'actor_slug' => 'apify/threads-scraper',
            ],
        ];
    }

    public function managedSlugs(): array
    {
        return array_map(
            static fn (array $actor): string => $actor['actor_slug'],
            $this->primaryActors()
        );
    }

    public function legacySlugs(): array
    {
        return array_map(
            static fn (array $actor): string => $actor['actor_slug'],
            $this->legacyActors()
        );
    }

    public function allManagedSlugs(): array
    {
        return array_values(array_unique(array_merge($this->managedSlugs(), $this->legacySlugs())));
    }

    public function syncManagedActors(): Collection
    {
        $synced = collect();

        foreach ($this->primaryActors() as $key => $actor) {
            $existing = ApifyActor::where('platform', $actor['platform'])
                ->where('actor_slug', $actor['actor_slug'])
                ->first();

            if ($existing) {
                // Existing primary actors are user-editable. Preserve stored values so edits do not get overwritten on every render.
                $synced->push($existing);
            } else {
                $synced->push(ApifyActor::create(
                    array_merge(
                        ['platform' => $actor['platform'], 'actor_slug' => $actor['actor_slug']],
                        $this->databasePayload($actor, true)
                    )
                ));
            }
        }

        foreach ($this->primaryActors() as $actor) {
            ApifyActor::query()
                ->where('platform', $actor['platform'])
                ->where('actor_slug', '!=', $actor['actor_slug'])
                ->update([
                    'status' => 'inactive',
                    'updated_at' => now(),
                ]);
        }

        foreach ($this->legacyActors() as $actor) {
            $existing = ApifyActor::where('actor_slug', $actor['actor_slug'])->first();
            if ($existing) {
                $existing->update([
                    'status' => 'inactive',
                    'updated_at' => now(),
                ]);
                $synced->push($existing->fresh());
            }
        }

        return $synced;
    }

    public function isPrimarySlug(string $slug): bool
    {
        return in_array($slug, $this->managedSlugs(), true);
    }

    public function isLegacySlug(string $slug): bool
    {
        return in_array($slug, $this->legacySlugs(), true);
    }

    public function syncPrimaryActor(string $slug): ?ApifyActor
    {
        foreach ($this->primaryActors() as $actor) {
            if ($actor['actor_slug'] === $slug) {
                return ApifyActor::firstOrCreate(
                    ['platform' => $actor['platform'], 'actor_slug' => $actor['actor_slug']],
                    $this->databasePayload($actor, true)
                );
            }
        }

        return null;
    }

    protected function databasePayload(array $actor, bool $active = true): array
    {
        $payload = [
            'actor_name' => $actor['actor_name'],
            'function_type' => $actor['function_type'],
            'default_keyword' => $actor['default_keyword'],
            'default_limit' => min(50, (int) $actor['default_limit']),
            'status' => $active ? 'active' : 'inactive',
            'keyword_field_mapping' => $actor['keyword_field_mapping'],
            'output_mapping' => $actor['output_mapping'],
            'interval_minutes' => $actor['interval_minutes'],
            'memory_limit' => $actor['memory_limit'],
            'range_mode' => $actor['range_mode'],
            'priority' => $actor['priority'],
            'maximum_cost_per_run_usd' => $actor['maximum_cost_per_run_usd'] ?? 0.0000,
        ];

        if (Schema::hasColumn('apify_actors', 'build')) {
            $payload['build'] = $actor['build'] ?? 'latest';
        }
        if (Schema::hasColumn('apify_actors', 'timeout_seconds')) {
            $payload['timeout_seconds'] = $actor['timeout_seconds'] ?? 10000;
        }
        if (Schema::hasColumn('apify_actors', 'no_timeout')) {
            $payload['no_timeout'] = $actor['no_timeout'] ?? false;
        }

        return $payload;
    }
}
