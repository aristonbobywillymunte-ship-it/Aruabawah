<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_media_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('platform'); // Facebook, Instagram, Threads, YouTube, X
            $table->text('post_url')->unique();
            $table->string('author_name');
            $table->text('author_url')->nullable();
            $table->text('content');
            $table->timestamp('posted_at');
            $table->integer('like_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->integer('share_count')->default(0);
            $table->integer('view_count')->default(0);
            $table->integer('follower_count')->default(0);
            $table->text('raw_json')->nullable(); // stored as JSON string (or JSONB in pgsql)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_items');
    }
};
