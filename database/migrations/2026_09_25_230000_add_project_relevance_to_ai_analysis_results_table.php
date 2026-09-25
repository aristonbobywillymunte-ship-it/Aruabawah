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
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->boolean('is_project_relevant')->nullable()->default(null)->after('is_noise');
            $table->text('project_relevance_reason')->nullable()->after('is_project_relevant');
            $table->unsignedSmallInteger('project_relevance_score')->nullable()->after('project_relevance_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->dropColumn(['is_project_relevant', 'project_relevance_reason', 'project_relevance_score']);
        });
    }
};
