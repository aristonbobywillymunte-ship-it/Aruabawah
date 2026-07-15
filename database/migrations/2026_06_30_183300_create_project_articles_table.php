<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->timestamps();

            // Unique constraint to prevent duplicate links
            $table->unique(['project_id', 'article_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_articles');
    }
};
