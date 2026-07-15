<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scraping_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('google_news_interval')->default(60); // minutes
            $table->integer('portal_crawling_interval')->default(120); // minutes
            $table->integer('limit_per_run')->default(50);
            $table->string('date_range')->default('7d'); // 24h, 7d, 30d, 90d
            $table->integer('timeout_seconds')->default(30);
            $table->integer('retry_limit')->default(3);
            $table->integer('retry_delay_minutes')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraping_settings');
    }
};
