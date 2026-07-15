<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('project_social_media_items')) {
            return;
        }

        $rows = DB::table('social_media_items')
            ->select('id', 'project_id')
            ->whereNotNull('project_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('project_social_media_items')->updateOrInsert(
                [
                    'project_id' => $row->project_id,
                    'social_media_item_id' => $row->id,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('project_social_media_items')) {
            return;
        }

        $rows = DB::table('social_media_items')
            ->select('id', 'project_id')
            ->whereNotNull('project_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('project_social_media_items')
                ->where('project_id', $row->project_id)
                ->where('social_media_item_id', $row->id)
                ->delete();
        }
    }
};
