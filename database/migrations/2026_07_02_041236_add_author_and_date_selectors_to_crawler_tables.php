<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            if (!Schema::hasColumn('news_sources', 'article_author_selector')) {
                $table->string('article_author_selector')->nullable()->after('article_content_selector');
            }
            if (!Schema::hasColumn('news_sources', 'article_date_selector')) {
                $table->string('article_date_selector')->nullable()->after('article_author_selector');
            }
        });

        Schema::table('news_source_suggestions', function (Blueprint $table) {
            if (!Schema::hasColumn('news_source_suggestions', 'article_author_selector')) {
                $table->string('article_author_selector')->nullable()->after('article_content_selector');
            }
            if (!Schema::hasColumn('news_source_suggestions', 'article_date_selector')) {
                $table->string('article_date_selector')->nullable()->after('article_author_selector');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            if (Schema::hasColumn('news_sources', 'article_author_selector')) {
                $table->dropColumn('article_author_selector');
            }
            if (Schema::hasColumn('news_sources', 'article_date_selector')) {
                $table->dropColumn('article_date_selector');
            }
        });

        Schema::table('news_source_suggestions', function (Blueprint $table) {
            if (Schema::hasColumn('news_source_suggestions', 'article_author_selector')) {
                $table->dropColumn('article_author_selector');
            }
            if (Schema::hasColumn('news_source_suggestions', 'article_date_selector')) {
                $table->dropColumn('article_date_selector');
            }
        });
    }
};
