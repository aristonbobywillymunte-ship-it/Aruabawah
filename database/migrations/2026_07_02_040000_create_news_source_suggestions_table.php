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
        Schema::create('news_source_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_source_id')
                ->nullable()
                ->constrained('news_sources')
                ->nullOnDelete();
            $table->string('suggested_by')->default('ai');
            $table->string('source_name')->nullable();
            $table->string('domain')->nullable();
            $table->string('base_url')->nullable();
            $table->string('search_url')->nullable();
            $table->string('feed_url')->nullable();
            $table->string('sitemap_url')->nullable();
            $table->string('search_result_selector')->nullable();
            $table->string('article_link_selector')->nullable();
            $table->string('article_content_selector')->nullable();
            $table->float('confidence')->nullable();
            $table->text('ai_reason')->nullable();
            $table->string('status')->default('draft_ai'); // draft_ai, testing, verified, failed, needs_review, approved
            $table->json('test_result_json')->nullable();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_source_suggestions');
    }
};
