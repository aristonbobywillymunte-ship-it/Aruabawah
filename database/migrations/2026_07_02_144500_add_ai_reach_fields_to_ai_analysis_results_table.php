<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->integer('local_relevance_score')->nullable()->after('reach_level');
            $table->string('estimated_reach_band')->nullable()->after('local_relevance_score');
            $table->integer('confidence_score')->nullable()->after('estimated_reach_band');
            $table->string('confidence_level')->nullable()->after('confidence_score');
            $table->text('signals_used')->nullable()->after('confidence_level');
            $table->text('reasoning_summary')->nullable()->after('signals_used');
            $table->text('limitations')->nullable()->after('reasoning_summary');
            $table->boolean('is_exact_reach')->default(false)->after('limitations');
            $table->string('reach_method')->nullable()->after('is_exact_reach');
        });
    }

    public function down(): void
    {
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->dropColumn([
                'local_relevance_score',
                'estimated_reach_band',
                'confidence_score',
                'confidence_level',
                'signals_used',
                'reasoning_summary',
                'limitations',
                'is_exact_reach',
                'reach_method',
            ]);
        });
    }
};
