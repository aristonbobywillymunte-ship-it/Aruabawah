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
            $table->boolean('is_noise')->nullable()->default(null)->after('overall_sentiment');
            $table->text('noise_reason')->nullable()->after('is_noise');
            $table->json('subjects')->nullable()->after('noise_reason');
            $table->unsignedSmallInteger('quality_confidence')->nullable()->after('subjects');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->dropColumn(['is_noise', 'noise_reason', 'subjects', 'quality_confidence']);
        });
    }
};
