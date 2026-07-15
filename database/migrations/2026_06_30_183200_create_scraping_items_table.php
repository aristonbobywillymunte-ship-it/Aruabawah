<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scraping_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_link_id')->constrained('candidate_links')->cascadeOnDelete();
            $table->text('url');
            $table->string('status')->default('pending'); // pending, processing, scraped, failed, duplicate, not_relevant
            $table->integer('retry_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraping_items');
    }
};
