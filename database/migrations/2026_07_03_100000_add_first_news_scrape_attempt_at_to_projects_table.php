<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'first_news_scrape_attempt_at')) {
                $table->timestamp('first_news_scrape_attempt_at')
                    ->nullable()
                    ->after('topics');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'first_news_scrape_attempt_at')) {
                $table->dropColumn('first_news_scrape_attempt_at');
            }
        });
    }
};
