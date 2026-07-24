<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            if (!Schema::hasColumn('news_sources', 'path_blocklist')) {
                $table->text('path_blocklist')->nullable()->after('article_noise_selector');
            }

            if (!Schema::hasColumn('news_sources', 'selector_blocklist')) {
                $table->text('selector_blocklist')->nullable()->after('path_blocklist');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            if (Schema::hasColumn('news_sources', 'selector_blocklist')) {
                $table->dropColumn('selector_blocklist');
            }

            if (Schema::hasColumn('news_sources', 'path_blocklist')) {
                $table->dropColumn('path_blocklist');
            }
        });
    }
};
