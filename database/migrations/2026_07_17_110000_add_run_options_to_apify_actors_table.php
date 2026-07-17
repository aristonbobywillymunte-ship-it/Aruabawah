<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apify_actors', function (Blueprint $table) {
            $table->string('build')->default('latest')->after('output_mapping');
            $table->unsignedInteger('timeout_seconds')->default(10000)->after('build');
            $table->boolean('no_timeout')->default(false)->after('timeout_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('apify_actors', function (Blueprint $table) {
            $table->dropColumn(['build', 'timeout_seconds', 'no_timeout']);
        });
    }
};
