<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('apify_dispatch_states')
            ->whereRaw('lower(platform) = ?', ['tiktok'])
            ->delete();

        DB::table('social_media_items')
            ->whereRaw('lower(platform) = ?', ['tiktok'])
            ->delete();
    }

    public function down(): void
    {
        // No-op: legacy social runtime data is intentionally removed permanently.
    }
};
