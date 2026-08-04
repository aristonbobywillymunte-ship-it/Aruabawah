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
        // 1. Ambil semua artikel duplikat sosial (Facebook, Instagram, TikTok)
        $socialArticles = \Illuminate\Support\Facades\DB::table('articles')
            ->whereIn('source_name', ['Facebook', 'Instagram', 'TikTok'])
            ->get();

        foreach ($socialArticles as $art) {
            // Cari apakah sudah ada item di social_media_items yang memiliki URL/Canonical URL yang sama
            $social = \Illuminate\Support\Facades\DB::table('social_media_items')
                ->where('post_url', $art->url)
                ->orWhere('post_url', $art->canonical_url)
                ->first();

            // Jika belum ada (orphan/yatim), pindahkan/buat barunya di social_media_items
            if (!$social) {
                $platform = $art->source_name;
                $author = 'Pengguna';
                if (preg_match('/oleh\s+([^\n]+)/i', $art->title, $matches)) {
                    $author = trim($matches[1]);
                }

                $socialId = \Illuminate\Support\Facades\DB::table('social_media_items')->insertGetId([
                    'platform' => $platform,
                    'author_name' => $author,
                    'content' => $art->content,
                    'posted_at' => $art->published_at,
                    'post_url' => $art->url ?: $art->canonical_url,
                    'like_count' => 0,
                    'comment_count' => 0,
                    'share_count' => 0,
                    'view_count' => 0,
                    'follower_count' => 0,
                    'raw_json' => json_encode(['title' => $art->title, 'content' => $art->content]),
                    'comments_checked' => true,
                    'created_at' => $art->created_at ?: now(),
                    'updated_at' => $art->updated_at ?: now(),
                ]);

                $social = \Illuminate\Support\Facades\DB::table('social_media_items')->find($socialId);
            }

            // 2. Hubungkan ke Proyek (Tabel pivot project_social_media_items)
            $projectArticles = \Illuminate\Support\Facades\DB::table('project_articles')
                ->where('article_id', $art->id)
                ->get();

            foreach ($projectArticles as $pa) {
                $exists = \Illuminate\Support\Facades\DB::table('project_social_media_items')
                    ->where('project_id', $pa->project_id)
                    ->where('social_media_item_id', $social->id)
                    ->exists();

                if (!$exists) {
                    \Illuminate\Support\Facades\DB::table('project_social_media_items')->insert([
                        'project_id' => $pa->project_id,
                        'social_media_item_id' => $social->id,
                    ]);
                }
            }

            // 3. Pindahkan Relasi AI (ai_analysis_results)
            $aiResults = \Illuminate\Support\Facades\DB::table('ai_analysis_results')
                ->where('article_id', $art->id)
                ->get();

            foreach ($aiResults as $ai) {
                // Pastikan tidak ada data ganda hasil AI di social_media_item_id tersebut
                $socialAiExists = \Illuminate\Support\Facades\DB::table('ai_analysis_results')
                    ->where('social_media_item_id', $social->id)
                    ->exists();

                if (!$socialAiExists) {
                    // Update: lepaskan article_id dan ikat ke social_media_item_id
                    \Illuminate\Support\Facades\DB::table('ai_analysis_results')
                        ->where('id', $ai->id)
                        ->update([
                            'article_id' => null,
                            'social_media_item_id' => $social->id,
                        ]);
                } else {
                    // Jika data AI di social_media_item_id sudah ada, hapus relasi ganda yang di articles agar bersih
                    \Illuminate\Support\Facades\DB::table('ai_analysis_results')
                        ->where('id', $ai->id)
                        ->delete();
                }
            }

            // 4. Hapus data pivot project_articles untuk artikel duplikat tersebut
            \Illuminate\Support\Facades\DB::table('project_articles')
                ->where('article_id', $art->id)
                ->delete();
        }

        // 5. Hapus seluruh data duplikat sosial dari tabel articles global secara permanen
        \Illuminate\Support\Facades\DB::table('articles')
            ->whereIn('source_name', ['Facebook', 'Instagram', 'TikTok'])
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Migrasi satu arah (pembersihan data duplikat tidak bisa direverse secara otomatis)
    }
};
