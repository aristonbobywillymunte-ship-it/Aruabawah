<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apify_actors', function (Blueprint $table) {
            if (Schema::hasColumn('apify_actors', 'post_filter_enabled')) {
                $table->dropColumn('post_filter_enabled');
            }

            if (Schema::hasColumn('apify_actors', 'cost_reference')) {
                $table->dropColumn('cost_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('apify_actors', function (Blueprint $table) {
            if (! Schema::hasColumn('apify_actors', 'post_filter_enabled')) {
                $table->boolean('post_filter_enabled')->default(false);
            }

            if (! Schema::hasColumn('apify_actors', 'cost_reference')) {
                $table->decimal('cost_reference', 8, 4)->default(0.0000);
            }
        });
    }
};
