<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_links', function (Blueprint $table) {
            $table->id();
            $table->text('url');
            $table->text('canonical_url')->unique();
            $table->string('source_type'); // google_news, portal_crawling, manual_url
            $table->string('status')->default('candidate'); // candidate, approved, rejected, duplicate
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_links');
    }
};
