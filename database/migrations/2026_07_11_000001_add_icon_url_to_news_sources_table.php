<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('news_sources', 'icon_url')) {
                $table->string('icon_url', 512)->nullable()->after('base_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            if (Schema::hasColumn('news_sources', 'icon_url')) {
                $table->dropColumn('icon_url');
            }
        });
    }
};
