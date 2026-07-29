<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_media_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_media_item_id')
                ->constrained('social_media_items')
                ->cascadeOnDelete();
            $table->string('platform', 50);
            $table->string('comment_id', 255);
            $table->string('parent_comment_id', 255)->nullable();
            $table->string('author_name')->nullable();
            $table->text('author_url')->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('content')->nullable();
            $table->integer('like_count')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->text('raw_json')->nullable();
            $table->timestamps();

            $table->unique(['social_media_item_id', 'comment_id'], 'social_media_comments_item_comment_unique');
            $table->index(['platform', 'posted_at'], 'social_media_comments_platform_posted_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_comments');
    }
};
