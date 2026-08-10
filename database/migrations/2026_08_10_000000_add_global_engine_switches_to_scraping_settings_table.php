<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scraping_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('scraping_settings', 'google_news_enabled')) {
                $table->boolean('google_news_enabled')->default(true)->after('is_active');
            }

            if (! Schema::hasColumn('scraping_settings', 'manual_portal_enabled')) {
                $table->boolean('manual_portal_enabled')->default(true)->after('google_news_enabled');
            }

            if (! Schema::hasColumn('scraping_settings', 'apify_enabled')) {
                $table->boolean('apify_enabled')->default(true)->after('manual_portal_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scraping_settings', function (Blueprint $table) {
            if (Schema::hasColumn('scraping_settings', 'apify_enabled')) {
                $table->dropColumn('apify_enabled');
            }

            if (Schema::hasColumn('scraping_settings', 'manual_portal_enabled')) {
                $table->dropColumn('manual_portal_enabled');
            }

            if (Schema::hasColumn('scraping_settings', 'google_news_enabled')) {
                $table->dropColumn('google_news_enabled');
            }
        });
    }
};
