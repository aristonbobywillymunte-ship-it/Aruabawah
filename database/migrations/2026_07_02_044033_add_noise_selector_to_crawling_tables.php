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
            $table->string('article_noise_selector')->nullable()->after('article_date_selector');
        });

        Schema::table('news_source_suggestions', function (Blueprint $table) {
            $table->string('article_noise_selector')->nullable()->after('article_date_selector');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            $table->dropColumn('article_noise_selector');
        });

        Schema::table('news_source_suggestions', function (Blueprint $table) {
            $table->dropColumn('article_noise_selector');
        });
    }
};
