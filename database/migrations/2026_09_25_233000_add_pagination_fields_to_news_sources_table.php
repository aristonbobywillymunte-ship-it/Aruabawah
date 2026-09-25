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
        Schema::table('news_sources', function (Blueprint $table) {
            $table->string('single_page_param', 50)->nullable()->after('article_noise_selector');
            $table->string('article_pagination_selector', 255)->nullable()->after('single_page_param');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            $table->dropColumn(['single_page_param', 'article_pagination_selector']);
        });
    }
};
