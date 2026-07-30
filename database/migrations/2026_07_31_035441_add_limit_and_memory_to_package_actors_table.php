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
        Schema::table('package_actors', function (Blueprint $table) {
            $table->integer('default_limit')->nullable()->after('is_enabled')->comment('Override limit default; null = pakai default actor');
            $table->integer('memory_limit')->nullable()->after('default_limit')->comment('Override memory limit (MB); null = pakai default actor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('package_actors', function (Blueprint $table) {
            $table->dropColumn(['default_limit', 'memory_limit']);
        });
    }
};
