<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'context_keywords')) {
                $table->json('context_keywords')->nullable()->after('topics');
            }

            if (! Schema::hasColumn('projects', 'exclude_keywords')) {
                $table->json('exclude_keywords')->nullable()->after('context_keywords');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'exclude_keywords')) {
                $table->dropColumn('exclude_keywords');
            }

            if (Schema::hasColumn('projects', 'context_keywords')) {
                $table->dropColumn('context_keywords');
            }
        });
    }
};
