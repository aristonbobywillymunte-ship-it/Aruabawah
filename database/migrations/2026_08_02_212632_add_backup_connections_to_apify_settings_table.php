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
            $table->string('connection_status_backup_1')->default('belum_dicek');
            $table->string('connection_status_backup_2')->default('belum_dicek');
            $table->string('connection_status_backup_3')->default('belum_dicek');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apify_settings', function (Blueprint $table) {
            $table->dropColumn([
                'connection_status_backup_1',
                'connection_status_backup_2',
                'connection_status_backup_3'
            ]);
        });
    }
};
