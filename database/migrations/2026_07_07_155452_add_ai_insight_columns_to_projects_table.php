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
            $table->text('ai_insight_summary')->nullable();
            $table->json('ai_insight_recommendations')->nullable();
            $table->timestamp('ai_insight_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['ai_insight_summary', 'ai_insight_recommendations', 'ai_insight_updated_at']);
        });
    }
};
