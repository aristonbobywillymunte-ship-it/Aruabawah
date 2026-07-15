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
        Schema::table('articles', function (Blueprint $table) {
            $table->string('canonical_url')->nullable()->after('url');
            $table->unique('canonical_url');
        });

        // Backfill canonical_url from url for existing records
        \Illuminate\Support\Facades\DB::table('articles')
            ->whereNotNull('url')
            ->update([
                'canonical_url' => \Illuminate\Support\Facades\DB::raw('url')
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropUnique(['canonical_url']);
            $table->dropColumn('canonical_url');
        });
    }
};
