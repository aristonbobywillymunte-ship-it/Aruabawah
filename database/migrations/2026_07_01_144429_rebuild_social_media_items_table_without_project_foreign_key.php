<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            Schema::create('social_media_items_rebuilt', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id')->nullable();
                $table->string('platform');
                $table->text('post_url');
                $table->string('author_name');
                $table->text('author_url')->nullable();
                $table->text('content');
                $table->timestamp('posted_at');
                $table->integer('like_count')->default(0);
                $table->integer('comment_count')->default(0);
                $table->integer('share_count')->default(0);
                $table->integer('view_count')->default(0);
                $table->integer('follower_count')->default(0);
                $table->text('raw_json')->nullable();
                $table->timestamps();
            });

            DB::statement('
                INSERT INTO social_media_items_rebuilt (
                    id, project_id, platform, post_url, author_name, author_url, content,
                    posted_at, like_count, comment_count, share_count, view_count,
                    follower_count, raw_json, created_at, updated_at
                )
                SELECT
                    id, project_id, platform, post_url, author_name, author_url, content,
                    posted_at, like_count, comment_count, share_count, view_count,
                    follower_count, raw_json, created_at, updated_at
                FROM social_media_items
            ');

            Schema::drop('social_media_items');
            Schema::rename('social_media_items_rebuilt', 'social_media_items');
            DB::statement('PRAGMA foreign_keys = ON');

            return;
        }

        DB::statement('ALTER TABLE social_media_items DROP CONSTRAINT IF EXISTS social_media_items_project_id_foreign');
        DB::statement('ALTER TABLE social_media_items ALTER COLUMN project_id DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            Schema::create('social_media_items_rebuilt', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->string('platform');
                $table->text('post_url');
                $table->string('author_name');
                $table->text('author_url')->nullable();
                $table->text('content');
                $table->timestamp('posted_at');
                $table->integer('like_count')->default(0);
                $table->integer('comment_count')->default(0);
                $table->integer('share_count')->default(0);
                $table->integer('view_count')->default(0);
                $table->integer('follower_count')->default(0);
                $table->text('raw_json')->nullable();
                $table->timestamps();
            });

            DB::statement('
                INSERT INTO social_media_items_rebuilt (
                    id, project_id, platform, post_url, author_name, author_url, content,
                    posted_at, like_count, comment_count, share_count, view_count,
                    follower_count, raw_json, created_at, updated_at
                )
                SELECT
                    id, project_id, platform, post_url, author_name, author_url, content,
                    posted_at, like_count, comment_count, share_count, view_count,
                    follower_count, raw_json, created_at, updated_at
                FROM social_media_items
            ');

            Schema::drop('social_media_items');
            Schema::rename('social_media_items_rebuilt', 'social_media_items');
            DB::statement('PRAGMA foreign_keys = ON');

            return;
        }

        DB::statement('ALTER TABLE social_media_items ALTER COLUMN project_id SET NOT NULL');
        DB::statement('ALTER TABLE social_media_items ADD CONSTRAINT social_media_items_project_id_foreign FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE');
    }
};
