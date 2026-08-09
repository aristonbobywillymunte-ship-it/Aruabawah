<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->integer('news_interval_minutes')->default(5)->after('use_portal');
            $table->integer('social_interval_minutes')->default(10)->after('news_interval_minutes');
            $table->unsignedTinyInteger('news_runs_per_day')->nullable()->after('social_interval_minutes');
            $table->json('news_run_times')->nullable()->after('news_runs_per_day');
            $table->unsignedTinyInteger('social_runs_per_day')->nullable()->after('news_run_times');
            $table->json('social_run_times')->nullable()->after('social_runs_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn([
                'news_interval_minutes',
                'social_interval_minutes',
                'news_runs_per_day',
                'news_run_times',
                'social_runs_per_day',
                'social_run_times',
            ]);
        });
    }
};
