<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('apify_actors')
            ->where('platform', 'TikTok')
            ->where('actor_slug', 'clockworks/tiktok-hashtag-scraper')
            ->update([
                'actor_name' => 'TikTok Hashtag Scraper',
                'default_keyword' => null,
                'keyword_field_mapping' => 'hashtags',
                'output_mapping' => '{"hashtags":["{keyword}"],"resultsPerPage":"{limit}","shouldDownloadCovers":false,"shouldDownloadSlideshowImages":false,"shouldDownloadVideos":false,"downloadSubtitlesOptions":"NEVER_DOWNLOAD_SUBTITLES","proxyConfiguration":{"useApifyProxy":true}}',
                'default_limit' => 50,
                'interval_minutes' => 720,
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
            ->where('platform', 'TikTok')
            ->where('actor_slug', 'clockworks/tiktok-hashtag-scraper')
            ->update([
                'actor_name' => 'TikTok Hashtag Scraper',
                'default_keyword' => null,
                'keyword_field_mapping' => 'hashtags',
                'output_mapping' => '{"hashtags":["{keyword}"],"resultsPerPage":"{limit}","shouldDownloadCovers":false,"shouldDownloadSlideshowImages":false,"shouldDownloadVideos":false,"downloadSubtitlesOptions":"NEVER_DOWNLOAD_SUBTITLES","proxyConfiguration":{"useApifyProxy":true}}',
                'default_limit' => 50,
                'interval_minutes' => 720,
                'memory_limit' => 2048,
                'range_mode' => '7d',
                'priority' => 3,
                'maximum_cost_per_run_usd' => 0.1500,
                'updated_at' => now(),
            ]);
    }
};
