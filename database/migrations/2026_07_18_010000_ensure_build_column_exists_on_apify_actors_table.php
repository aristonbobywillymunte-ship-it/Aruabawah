<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apify_actors', function (Blueprint $table) {
            if (! Schema::hasColumn('apify_actors', 'build')) {
                $table->string('build')->default('latest')->after('output_mapping');
            }
        });
    }

    public function down(): void
    {
        Schema::table('apify_actors', function (Blueprint $table) {
            if (Schema::hasColumn('apify_actors', 'build')) {
                $table->dropColumn('build');
            }
        });
    }
};
