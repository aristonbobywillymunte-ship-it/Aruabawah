<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'news_run_times_override')) {
                $table->json('news_run_times_override')->nullable()->after('package_id');
            }

            if (! Schema::hasColumn('projects', 'social_run_times_override')) {
                $table->json('social_run_times_override')->nullable()->after('news_run_times_override');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'social_run_times_override')) {
                $table->dropColumn('social_run_times_override');
            }

            if (Schema::hasColumn('projects', 'news_run_times_override')) {
                $table->dropColumn('news_run_times_override');
            }
        });
    }
};
