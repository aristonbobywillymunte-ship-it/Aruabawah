<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('apify_actors')
            ->where('platform', 'Instagram')
            ->update([
                'default_keyword' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('apify_actors')
            ->where('platform', 'Instagram')
            ->update([
                'default_keyword' => 'pilkada',
                'updated_at' => now(),
            ]);
    }
};
