<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            if (!Schema::hasColumn('news_sources', 'base_url')) {
                $table->string('base_url')->nullable()->after('domain');
            }

            if (!Schema::hasColumn('news_sources', 'feed_url')) {
                $table->string('feed_url')->nullable()->after('base_url');
            }

            if (!Schema::hasColumn('news_sources', 'search_url')) {
                $table->string('search_url')->nullable()->after('feed_url');
            }

            if (!Schema::hasColumn('news_sources', 'sitemap_url')) {
                $table->string('sitemap_url')->nullable()->after('search_url');
            }

            if (!Schema::hasColumn('news_sources', 'search_result_selector')) {
                $table->string('search_result_selector')->nullable()->after('sitemap_url');
            }

            if (!Schema::hasColumn('news_sources', 'article_link_selector')) {
                $table->string('article_link_selector')->nullable()->after('search_result_selector');
            }

            if (!Schema::hasColumn('news_sources', 'article_content_selector')) {
                $table->string('article_content_selector')->nullable()->after('article_link_selector');
            }

            if (!Schema::hasColumn('news_sources', 'is_search_enabled')) {
                $table->boolean('is_search_enabled')->default(false)->after('article_content_selector');
            }

            if (!Schema::hasColumn('news_sources', 'is_feed_enabled')) {
                $table->boolean('is_feed_enabled')->default(false)->after('is_search_enabled');
            }

            if (!Schema::hasColumn('news_sources', 'is_sitemap_enabled')) {
                $table->boolean('is_sitemap_enabled')->default(false)->after('is_feed_enabled');
            }
        });

        DB::table('news_sources')->where('domain', 'detik.com')->update([
            'base_url' => 'https://www.detik.com',
            'search_url' => 'https://www.detik.com/search/searchall?query={keyword}',
            'search_result_selector' => 'article a[href]',
            'article_link_selector' => 'article a[href]',
            'article_content_selector' => 'article .detail__body-text',
            'is_search_enabled' => true,
            'is_feed_enabled' => false,
            'is_sitemap_enabled' => false,
        ]);

        DB::table('news_sources')->where('domain', 'kompas.com')->update([
            'base_url' => 'https://www.kompas.com',
            'search_url' => 'https://www.kompas.com/search?q={keyword}',
            'search_result_selector' => 'a[href]',
            'article_link_selector' => 'a[href]',
            'article_content_selector' => '.read__content',
            'is_search_enabled' => true,
            'is_feed_enabled' => false,
            'is_sitemap_enabled' => false,
        ]);

        DB::table('news_sources')->where('domain', 'arusbawah.co')->update([
            'base_url' => 'https://arusbawah.co',
            'is_search_enabled' => false,
            'is_feed_enabled' => false,
            'is_sitemap_enabled' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            foreach ([
                'base_url',
                'feed_url',
                'search_url',
                'sitemap_url',
                'search_result_selector',
                'article_link_selector',
                'article_content_selector',
                'is_search_enabled',
                'is_feed_enabled',
                'is_sitemap_enabled',
            ] as $column) {
                if (Schema::hasColumn('news_sources', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
