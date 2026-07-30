<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_media_items', function (Blueprint $table) {
            // Menandai apakah komentar sudah diperiksa oleh comment scraper.
            // - Facebook: selalu true (tidak ada comment scraper Facebook)
            // - IG/TikTok dengan comment scraper aktif: default false, set true setelah dicek
            // - IG/TikTok tanpa comment scraper aktif: set true otomatis
            $table->boolean('comments_checked')->default(false)->after('raw_json');
        });

        // Semua postingan Facebook langsung dianggap sudah dicek
        DB::table('social_media_items')
            ->where('platform', 'Facebook')
            ->update(['comments_checked' => true]);

        // IG/TikTok yang sudah punya komentar di tabel social_media_comments: set true
        DB::table('social_media_items')
            ->whereIn('platform', ['Instagram', 'TikTok'])
            ->whereIn('id', function ($query) {
                $query->select('social_media_item_id')
                    ->from('social_media_comments')
                    ->distinct();
            })
            ->update(['comments_checked' => true]);

        // IG/TikTok tanpa comment scraper aktif sama sekali: set true otomatis
        // Cek platform mana yang tidak punya actor Comment Scraper aktif
        $commentScraperPlatforms = DB::table('apify_actors')
            ->where('function_type', 'Comment Scraper')
            ->where('status', 'active')
            ->pluck('platform')
            ->map(fn ($p) => strtolower($p))
            ->toArray();

        foreach (['Instagram', 'TikTok'] as $platform) {
            if (! in_array(strtolower($platform), $commentScraperPlatforms, true)) {
                // Platform ini tidak punya comment scraper aktif → tandai semua sebagai sudah dicek
                DB::table('social_media_items')
                    ->where('platform', $platform)
                    ->update(['comments_checked' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('social_media_items', function (Blueprint $table) {
            $table->dropColumn('comments_checked');
        });
    }
};
