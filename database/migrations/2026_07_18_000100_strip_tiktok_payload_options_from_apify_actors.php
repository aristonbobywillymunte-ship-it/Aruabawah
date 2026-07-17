<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $actors = DB::table('apify_actors')
            ->where('platform', 'TikTok')
            ->select('id', 'output_mapping')
            ->get();

        foreach ($actors as $actor) {
            $mapping = json_decode((string) $actor->output_mapping, true);
            if (! is_array($mapping)) {
                continue;
            }

            $changed = false;

            foreach (['resultsPerPage', 'shouldDownloadCovers', 'shouldDownloadSlideshowImages', 'shouldDownloadVideos', 'downloadSubtitlesOptions'] as $key) {
                if (array_key_exists($key, $mapping)) {
                    unset($mapping[$key]);
                    $changed = true;
                }
            }

            if (isset($mapping['proxyConfiguration']) && is_array($mapping['proxyConfiguration']) && array_key_exists('useApifyProxy', $mapping['proxyConfiguration'])) {
                unset($mapping['proxyConfiguration']['useApifyProxy']);
                if ($mapping['proxyConfiguration'] === []) {
                    unset($mapping['proxyConfiguration']);
                }
                $changed = true;
            }

            if (! $changed) {
                continue;
            }

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
        // No-op: removed TikTok payload options are intentionally not restored automatically.
    }
};
