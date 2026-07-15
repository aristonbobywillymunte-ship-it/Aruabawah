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
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->integer('priority')->default(10)->after('is_active');
            $table->timestamp('cooldown_until')->nullable()->after('requests_per_minute');
            $table->string('last_failure_code')->nullable()->after('cooldown_until');
            $table->json('capabilities')->nullable()->after('last_failure_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn(['priority', 'cooldown_until', 'last_failure_code', 'capabilities']);
        });
    }
};
