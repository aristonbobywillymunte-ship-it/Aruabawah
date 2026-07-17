<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('project_social_media_items')
            ->whereIn('social_media_item_id', function ($query) {
                $query->select('id')
                    ->from('social_media_items')
                    ->whereRaw('lower(platform) = ?', ['tiktok']);
            })
            ->delete();
    }

    public function down(): void
    {
        // No-op: legacy social pivot rows are intentionally not restored automatically.
    }
};
