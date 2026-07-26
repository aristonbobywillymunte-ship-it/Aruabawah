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
            $table->index('article_id');
            $table->index('social_media_item_id');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->index('published_at');
        });

        Schema::table('social_media_items', function (Blueprint $table) {
            $table->index('posted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->dropIndex(['article_id']);
            $table->dropIndex(['social_media_item_id']);
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
        });

        Schema::table('social_media_items', function (Blueprint $table) {
            $table->dropIndex(['posted_at']);
        });
    }
};
