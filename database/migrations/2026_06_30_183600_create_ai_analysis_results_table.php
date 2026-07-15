<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->nullable()->constrained('articles')->cascadeOnDelete();
            $table->foreignId('social_media_item_id')->nullable()->constrained('social_media_items')->cascadeOnDelete();
            $table->text('summary');
            $table->string('sentiment'); // positive, neutral, negative
            $table->float('sentiment_score');
            $table->string('main_issue');
            $table->text('entities')->nullable(); // JSON string
            $table->string('risk_level'); // low, medium, high, critical
            $table->text('risk_reason')->nullable();
            $table->integer('reach_estimate')->default(0);
            $table->integer('reach_score_10')->default(1);
            $table->integer('reach_score_max')->default(10);
            $table->string('reach_level'); // low, medium, high
            $table->string('reach_trend'); // up, down, stable
            $table->string('reach_source');
            $table->string('reach_confidence'); // low, medium, high
            $table->text('reach_reason');
            $table->text('recommendation');
            $table->text('raw_response');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analysis_results');
    }
};
