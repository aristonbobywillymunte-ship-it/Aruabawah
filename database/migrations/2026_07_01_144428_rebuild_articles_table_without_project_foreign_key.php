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

            Schema::create('articles_rebuilt', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id')->nullable();
                $table->string('title');
                $table->text('content');
                $table->string('url')->nullable();
                $table->string('source_name');
                $table->string('sentiment')->default('neutral');
                $table->float('sentiment_score')->default(0.0);
                $table->string('category')->default('General');
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });

            DB::statement('
                INSERT INTO articles_rebuilt (
                    id, project_id, title, content, url, source_name,
                    sentiment, sentiment_score, category, published_at, created_at, updated_at
                )
                SELECT
                    id, project_id, title, content, url, source_name,
                    sentiment, sentiment_score, category, published_at, created_at, updated_at
                FROM articles
            ');

            Schema::drop('articles');
            Schema::rename('articles_rebuilt', 'articles');
            DB::statement('PRAGMA foreign_keys = ON');

            return;
        }

        DB::statement('ALTER TABLE articles DROP CONSTRAINT IF EXISTS articles_project_id_foreign');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            Schema::create('articles_rebuilt', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->nullable()->constrained()->onDelete('cascade');
                $table->string('title');
                $table->text('content');
                $table->string('url')->nullable();
                $table->string('source_name');
                $table->string('sentiment')->default('neutral');
                $table->float('sentiment_score')->default(0.0);
                $table->string('category')->default('General');
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });

            DB::statement('
                INSERT INTO articles_rebuilt (
                    id, project_id, title, content, url, source_name,
                    sentiment, sentiment_score, category, published_at, created_at, updated_at
                )
                SELECT
                    id, project_id, title, content, url, source_name,
                    sentiment, sentiment_score, category, published_at, created_at, updated_at
                FROM articles
            ');

            Schema::drop('articles');
            Schema::rename('articles_rebuilt', 'articles');
            DB::statement('PRAGMA foreign_keys = ON');

            return;
        }

        DB::statement('ALTER TABLE articles ADD CONSTRAINT articles_project_id_foreign FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE');
    }
};
