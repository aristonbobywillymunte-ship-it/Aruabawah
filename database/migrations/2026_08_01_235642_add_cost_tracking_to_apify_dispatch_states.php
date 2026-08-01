<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambah 3 kolom nullable untuk tracking biaya Apify per run.
     * Tidak mengubah kolom yang sudah ada — aman untuk data lama.
     */
    public function up(): void
    {
        Schema::table('apify_dispatch_states', function (Blueprint $table) {
            $table->decimal('actual_cost_usd', 10, 6)->nullable()->after('last_error_message')->comment('Biaya aktual run dari Apify API (usageTotalCostUsd)');
            $table->integer('items_collected')->nullable()->after('actual_cost_usd')->comment('Jumlah item yang berhasil dikumpulkan dari dataset');
            $table->integer('run_duration_secs')->nullable()->after('items_collected')->comment('Durasi run dalam detik dari Apify API stats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apify_dispatch_states', function (Blueprint $table) {
            $table->dropColumn(['actual_cost_usd', 'items_collected', 'run_duration_secs']);
        });
    }
};
