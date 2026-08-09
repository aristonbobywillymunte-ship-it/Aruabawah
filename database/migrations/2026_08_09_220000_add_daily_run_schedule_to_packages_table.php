<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'news_runs_per_day')) {
                $table->unsignedTinyInteger('news_runs_per_day')->nullable()->after('social_interval_minutes');
            }

            if (! Schema::hasColumn('packages', 'news_run_times')) {
                $table->json('news_run_times')->nullable()->after('news_runs_per_day');
            }

            if (! Schema::hasColumn('packages', 'social_runs_per_day')) {
                $table->unsignedTinyInteger('social_runs_per_day')->nullable()->after('news_run_times');
            }

            if (! Schema::hasColumn('packages', 'social_run_times')) {
                $table->json('social_run_times')->nullable()->after('social_runs_per_day');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (Schema::hasColumn('packages', 'social_run_times')) {
                $table->dropColumn('social_run_times');
            }
            if (Schema::hasColumn('packages', 'social_runs_per_day')) {
                $table->dropColumn('social_runs_per_day');
            }
            if (Schema::hasColumn('packages', 'news_run_times')) {
                $table->dropColumn('news_run_times');
            }
            if (Schema::hasColumn('packages', 'news_runs_per_day')) {
                $table->dropColumn('news_runs_per_day');
            }
        });
    }
};
