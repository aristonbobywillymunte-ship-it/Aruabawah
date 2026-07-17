<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('apify_actors')
            ->where('platform', 'TikTok')
            ->whereIn('actor_slug', [
                'epctex/tiktok-search-scraper',
                'paul_44/tiktok-search',
            ])
            ->delete();

        DB::table('apify_actors')
            ->where('platform', 'TikTok')
            ->where('actor_slug', 'clockworks/tiktok-hashtag-scraper')
            ->update([
                'actor_name' => 'TikTok Hashtag Scraper',
                'default_keyword' => null,
                'keyword_field_mapping' => 'hashtags',
                'output_mapping' => '{"hashtags":["{keyword}"]}',
                'default_limit' => 50,
                'interval_minutes' => 240,
                'memory_limit' => 2048,
                'range_mode' => '7d',
                'priority' => 3,
                'maximum_cost_per_run_usd' => 0.1500,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('apify_actors')
            ->updateOrInsert(
                [
                    'platform' => 'TikTok',
                    'actor_slug' => 'epctex/tiktok-search-scraper',
                ],
                [
                    'actor_name' => 'TikTok Search Scraper',
                    'function_type' => 'Search Post',
                    'default_keyword' => null,
                    'default_limit' => 50,
                    'status' => 'inactive',
                    'keyword_field_mapping' => 'hashtags',
                    'output_mapping' => '{"hashtags":["{keyword}"],"resultsPerPage":"{limit}"}',
                    'build' => 'latest',
                    'timeout_seconds' => 10000,
                    'no_timeout' => false,
                    'interval_minutes' => 240,
                    'memory_limit' => 2048,
                    'range_mode' => '7d',
                    'priority' => 3,
                    'maximum_cost_per_run_usd' => 0.1500,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

        DB::table('apify_actors')
            ->updateOrInsert(
                [
                    'platform' => 'TikTok',
                    'actor_slug' => 'paul_44/tiktok-search',
                ],
                [
                    'actor_name' => 'TikTok Keyword Search',
                    'function_type' => 'Search Post',
                    'default_keyword' => null,
                    'default_limit' => 50,
                    'status' => 'inactive',
                    'keyword_field_mapping' => 'hashtags',
                    'output_mapping' => '{"hashtags":["{keyword}"],"resultsPerPage":"{limit}"}',
                    'build' => 'latest',
                    'timeout_seconds' => 10000,
                    'no_timeout' => false,
                    'interval_minutes' => 240,
                    'memory_limit' => 2048,
                    'range_mode' => '7d',
                    'priority' => 3,
                    'maximum_cost_per_run_usd' => 0.1500,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
    }
};
