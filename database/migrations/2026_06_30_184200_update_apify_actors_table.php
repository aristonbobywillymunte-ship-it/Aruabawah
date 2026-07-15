<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apify_actors', function (Blueprint $table) {
            $table->string('keyword_field_mapping')->default('search');
            $table->text('output_mapping')->nullable();
            $table->integer('interval_minutes')->default(240); // 4 hours
            $table->integer('memory_limit')->default(1024); // 128, 256, 512, 1024, 2048, 4096
            $table->string('range_mode')->default('7d'); // 24h, 7d, 30d, 90d
            $table->boolean('post_filter_enabled')->default(false);
            $table->integer('priority')->default(1);
            $table->decimal('cost_reference', 8, 4)->default(0.0000);
        });
    }

    public function down(): void
    {
        Schema::table('apify_actors', function (Blueprint $table) {
            $table->dropColumn([
                'keyword_field_mapping',
                'output_mapping',
                'interval_minutes',
                'memory_limit',
                'range_mode',
                'post_filter_enabled',
                'priority',
                'cost_reference',
            ]);
        });
    }
};
