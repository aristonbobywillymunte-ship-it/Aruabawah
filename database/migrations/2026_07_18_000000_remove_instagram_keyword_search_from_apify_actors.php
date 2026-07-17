<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $actors = DB::table('apify_actors')
            ->where('platform', 'Instagram')
            ->select('id', 'output_mapping')
            ->get();

        foreach ($actors as $actor) {
            $mapping = json_decode((string) $actor->output_mapping, true);
            if (! is_array($mapping) || ! array_key_exists('keywordSearch', $mapping)) {
                continue;
            }

            unset($mapping['keywordSearch']);

            DB::table('apify_actors')
                ->where('id', $actor->id)
                ->update([
                    'output_mapping' => json_encode($mapping, JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // No-op: legacy `keywordSearch` can be restored manually if ever needed.
    }
};
