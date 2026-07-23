<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_source_suggestions', function (Blueprint $table) {
            if (! Schema::hasColumn('news_source_suggestions', 'crawling_type')) {
                $table->string('crawling_type')->nullable()->after('base_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news_source_suggestions', function (Blueprint $table) {
            if (Schema::hasColumn('news_source_suggestions', 'crawling_type')) {
                $table->dropColumn('crawling_type');
            }
        });
    }
};
