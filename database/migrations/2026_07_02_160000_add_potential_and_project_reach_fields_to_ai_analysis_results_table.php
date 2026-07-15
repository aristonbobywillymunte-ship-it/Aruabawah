<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->integer('potential_reach_score')->nullable()->after('reach_method');
            $table->string('potential_reach_level')->nullable()->after('potential_reach_score');
            $table->string('potential_reach_band')->nullable()->after('potential_reach_level');
            $table->integer('project_reach_score')->nullable()->after('potential_reach_band');
            $table->string('project_reach_level')->nullable()->after('project_reach_score');
            $table->string('project_reach_band')->nullable()->after('project_reach_level');
        });
    }

    public function down(): void
    {
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->dropColumn([
                'potential_reach_score',
                'potential_reach_level',
                'potential_reach_band',
                'project_reach_score',
                'project_reach_level',
                'project_reach_band',
            ]);
        });
    }
};
