<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('apify_dispatch_states', 'is_scheduled_execution')) {
            Schema::table('apify_dispatch_states', function (Blueprint $table) {
                $table->boolean('is_scheduled_execution')->default(false)->after('completed_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('apify_dispatch_states', 'is_scheduled_execution')) {
            Schema::table('apify_dispatch_states', function (Blueprint $table) {
                $table->dropColumn('is_scheduled_execution');
            });
        }
    }
};
