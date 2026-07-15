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
            $table->integer('potential_estimated_readers')->nullable()->after('reach_method');
            $table->integer('project_estimated_readers')->nullable()->after('potential_reach_band');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->dropColumn(['potential_estimated_readers', 'project_estimated_readers']);
        });
    }
};
