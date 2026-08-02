<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->integer('news_interval_minutes')->default(5)->after('use_portal');
            $table->integer('social_interval_minutes')->default(10)->after('news_interval_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['news_interval_minutes', 'social_interval_minutes']);
        });
    }
};
