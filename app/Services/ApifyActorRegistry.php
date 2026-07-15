<?php

namespace App\Services;

use App\Models\ApifyActor;
use Illuminate\Support\Collection;

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
                'interval_minutes' => 720,
                'memory_limit' => 1024,
                'range_mode' => '30d',
                'post_filter_enabled' => true,
                'priority' => 1,
                'cost_reference' => 3.9900,
                'maximum_cost_per_run_usd' => 0.2000,
                'editable_fields' => ['default_keyword', 'default_limit', 'range_mode', 'post_filter_enabled', 'priority'],
                'locked_fields' => ['platform', 'actor_slug', 'function_type'],
            ],
            'instagram' => [
                'platform' => 'Instagram',
                'label' => 'Instagram Keyword Search',
                'menu_label' => 'Instagram Keyword Search',
                'actor_name' => 'Instagram Search Scraper',
                'actor_slug' => 'apify/instagram-search-scraper',
                'function_type' => 'Search Post',
                'default_keyword' => 'pilkada',
                'default_limit' => 50,
                'status' => 'active',
                'keyword_field_mapping' => 'search',
                'output_mapping' => '{"enhanceUserSearchWithFacebookPage":false,"liveSearch":true,"search":"{keyword}","searchLimit":"{limit}","searchType":"popular"}',
                'interval_minutes' => 720,
                'memory_limit' => 1024,
                'range_mode' => '7d',
                'post_filter_enabled' => false,
                'priority' => 2,
                'cost_reference' => 3.0000,
                'maximum_cost_per_run_usd' => 0.1500,
                'editable_fields' => ['default_keyword', 'default_limit', 'range_mode', 'priority'],
                'locked_fields' => ['platform', 'actor_slug', 'function_type'],
            ],
            'tiktok' => [
                'platform' => 'TikTok',
                'label' => 'TikTok Keyword Search',
                'menu_label' => 'TikTok Keyword Search',
                'actor_name' => 'TikTok Keyword Search',
                'actor_slug' => 'paul_44/tiktok-search',
                'function_type' => 'Search Post',
                'default_keyword' => 'walikota samarinda',
                'default_limit' => 50,
                'status' => 'active',
                'keyword_field_mapping' => 'keyword',
                'output_mapping' => '{"dateRange":"7days","includeSearchKeywords":true,"keywords":["{keyword}"],"location":"ID","maxItems":"{limit}","mirrorVideos":true,"proxyConfiguration":{"useApifyProxy":true,"apifyProxyGroups":["RESIDENTIAL"],"apifyProxyCountry":"ID"},"sortType":"RELEVANCE","strictKeywordMatch":false,"useProxy":true,"minPlayCount":0,"mirrorVideoBytes":262144,"minDurationSec":0,"maxConcurrentKeywords":1}',
                'interval_minutes' => 720,
                'memory_limit' => 2048,
                'range_mode' => '7d',
                'post_filter_enabled' => false,
                'priority' => 3,
                'cost_reference' => 0.0000,
                'maximum_cost_per_run_usd' => 0.1500,
                'editable_fields' => ['default_keyword', 'default_limit', 'range_mode', 'priority'],
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
            'instagram-legacy' => [
                'platform' => 'Instagram',
                'label' => 'Legacy / Inactive',
                'menu_label' => 'Legacy / Inactive',
                'actor_name' => 'Instagram Scraper',
                'actor_slug' => 'apify/instagram-scraper',
            ],
            'tiktok-legacy-1' => [
                'platform' => 'TikTok',
                'label' => 'Legacy / Inactive',
                'menu_label' => 'Legacy / Inactive',
                'actor_name' => 'TikTok Search Scraper',
                'actor_slug' => 'epctex/tiktok-search-scraper',
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
        return [
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
            'post_filter_enabled' => $actor['post_filter_enabled'],
            'priority' => $actor['priority'],
            'cost_reference' => $actor['cost_reference'],
            'maximum_cost_per_run_usd' => $actor['maximum_cost_per_run_usd'] ?? 0.0000,
        ];
    }
}
