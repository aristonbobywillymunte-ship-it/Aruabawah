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
        Schema::table('apify_settings', function (Blueprint $table) {
            $table->text('api_token_backup_1')->nullable();
            $table->text('api_token_backup_2')->nullable();
            $table->text('api_token_backup_3')->nullable();
            
            // Simpan status/keterangan token mana yang sedang aktif (0 = Utama, 1 = Backup 1, 2 = Backup 2, 3 = Backup 3)
            $table->integer('active_token_index')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apify_settings', function (Blueprint $table) {
            $table->dropColumn(['api_token_backup_1', 'api_token_backup_2', 'api_token_backup_3', 'active_token_index']);
        });
    }
};
