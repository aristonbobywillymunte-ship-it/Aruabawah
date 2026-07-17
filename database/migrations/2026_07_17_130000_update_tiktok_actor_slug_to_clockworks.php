<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('apify_actors')
            ->where('platform', 'TikTok')
            ->where('actor_slug', 'paul_44/tiktok-search')
            ->update([
                'actor_name' => 'TikTok Hashtag Scraper',
                'actor_slug' => 'clockworks/tiktok-hashtag-scraper',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('apify_actors')
            ->where('platform', 'TikTok')
            ->where('actor_slug', 'clockworks/tiktok-hashtag-scraper')
            ->update([
                'actor_name' => 'TikTok Keyword Search',
                'actor_slug' => 'paul_44/tiktok-search',
                'updated_at' => now(),
            ]);
    }
};
