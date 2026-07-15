<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_articles', function (Blueprint $table) {
            $table->string('rescrape_status')->nullable()->after('updated_at');
            $table->text('rescrape_reason')->nullable()->after('rescrape_status');
            $table->timestamp('rescrape_requested_at')->nullable()->after('rescrape_reason');
            $table->string('rescrape_source')->nullable()->after('rescrape_requested_at');
            $table->json('rescrape_meta')->nullable()->after('rescrape_source');

            $table->index(['project_id', 'rescrape_status'], 'project_articles_rescrape_status_idx');
            $table->index(['article_id', 'rescrape_status'], 'project_articles_article_rescrape_idx');
        });
    }

    public function down(): void
    {
        Schema::table('project_articles', function (Blueprint $table) {
            $table->dropIndex('project_articles_rescrape_status_idx');
            $table->dropIndex('project_articles_article_rescrape_idx');
            $table->dropColumn([
                'rescrape_status',
                'rescrape_reason',
                'rescrape_requested_at',
                'rescrape_source',
                'rescrape_meta',
            ]);
        });
    }
};
