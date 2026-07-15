<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apify_actors', function (Blueprint $table) {
            $table->decimal('maximum_cost_per_run_usd', 8, 4)
                ->default(0.0000)
                ->after('cost_reference');
        });

        DB::table('apify_actors')->where('platform', 'Facebook')->update([
            'maximum_cost_per_run_usd' => 0.2500,
        ]);

        DB::table('apify_actors')->where('platform', 'Instagram')->update([
            'maximum_cost_per_run_usd' => 0.2500,
        ]);

        DB::table('apify_actors')->where('platform', 'TikTok')->update([
            'maximum_cost_per_run_usd' => 0.5000,
        ]);
    }

    public function down(): void
    {
        Schema::table('apify_actors', function (Blueprint $table) {
            $table->dropColumn('maximum_cost_per_run_usd');
        });
    }
};
