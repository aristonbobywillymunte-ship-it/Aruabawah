<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_analysis_dispatch_states', function (Blueprint $table) {
            $table->string('failure_category', 64)->nullable()->after('status');
            $table->timestamp('last_failed_at')->nullable()->after('last_attempt_at');

            $table->index(['failure_category', 'status'], 'ai_dispatch_state_failure_category_idx');
            $table->index(['status', 'last_failed_at'], 'ai_dispatch_state_last_failed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_analysis_dispatch_states', function (Blueprint $table) {
            $table->dropIndex('ai_dispatch_state_failure_category_idx');
            $table->dropIndex('ai_dispatch_state_last_failed_idx');
            $table->dropColumn(['failure_category', 'last_failed_at']);
        });
    }
};
