<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Admin Arusbawah',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]
        );

        \App\Models\ScrapingSetting::updateOrCreate(
            ['id' => 1],
            [
                'google_news_interval' => 60,
                'portal_crawling_interval' => 120,
                'limit_per_run' => 50,
                'date_range' => '7d',
                'timeout_seconds' => 30,
                'retry_limit' => 3,
                'retry_delay_minutes' => 10,
                'is_active' => true,
            ]
        );

        $newsSources = [
            [
                'domain' => 'detik.com',
                'name' => 'Detikcom',
                'crawling_type' => 'html',
                'selector' => 'article .detail__body-text',
                'timeout_seconds' => 30,
                'is_active' => true,
                'notes' => 'Portal berita Detikcom',
                'source_type' => 'national_local_channel',
                'media_scope' => 'national',
                'dewan_pers_status' => 'terverifikasi_faktual',
                'local_reach_weight' => 10.0,
                'scrape_priority' => 20,
                'reach_notes' => 'Media nasional dengan kanal lokal Kaltim/Kaltara.',
            ],
            [
                'domain' => 'kompas.com',
                'name' => 'Kompascom',
                'crawling_type' => 'html',
                'selector' => '.read__content',
                'timeout_seconds' => 30,
                'is_active' => true,
                'notes' => 'Portal berita Kompascom',
                'source_type' => 'national_local_channel',
                'media_scope' => 'national',
                'dewan_pers_status' => 'terverifikasi_faktual',
                'local_reach_weight' => 10.0,
                'scrape_priority' => 21,
                'reach_notes' => 'Media nasional dengan jangkauan besar di Kaltim.',
            ],
            [
                'domain' => 'kaltim.tribunnews.com',
                'name' => 'Tribun Kaltim',
                'source_type' => 'national_local_channel',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 10.0,
                'scrape_priority' => 2,
                'reach_notes' => 'Channel lokal Tribun untuk Kaltim; prioritas tinggi untuk berita regional.',
            ],
            [
                'domain' => 'prokal.co',
                'name' => 'Kaltim Post / Prokal',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 10.0,
                'scrape_priority' => 1,
                'reach_notes' => 'Prioritas utama media lokal/regional Kaltim.',
            ],
            [
                'domain' => 'kaltimtoday.co',
                'name' => 'Kaltimtoday.co',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 9.0,
                'scrape_priority' => 3,
                'reach_notes' => 'Media lokal Kaltim dengan fokus regional.',
            ],
            [
                'domain' => 'sapos.co.id',
                'name' => 'Samarinda Pos / Sapos',
                'source_type' => 'local_media',
                'media_scope' => 'local_samarinda',
                'dewan_pers_status' => null,
                'local_reach_weight' => 8.5,
                'scrape_priority' => 4,
                'reach_notes' => 'Prioritas kota Samarinda.',
            ],
            [
                'domain' => 'kaltimkece.id',
                'name' => 'Kaltimkece.id',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 8.5,
                'scrape_priority' => 5,
                'reach_notes' => 'Media regional Kaltim dengan bobot tinggi.',
            ],
            [
                'domain' => 'mediakaltim.com',
                'name' => 'Media Kaltim',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 8.0,
                'scrape_priority' => 6,
                'reach_notes' => 'Media lokal/regional Kaltim.',
            ],
            [
                'domain' => 'korankaltim.com',
                'name' => 'Koran Kaltim',
                'source_type' => 'local_media',
                'media_scope' => 'local_kabupaten',
                'dewan_pers_status' => null,
                'local_reach_weight' => 7.5,
                'scrape_priority' => 7,
                'reach_notes' => 'Media daerah/kabupaten di Kaltim.',
            ],
            [
                'domain' => 'swarakaltim.com',
                'name' => 'Swara Kaltim',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 7.0,
                'scrape_priority' => 8,
                'reach_notes' => 'Media regional Kaltim.',
            ],
            [
                'domain' => 'niaga.asia',
                'name' => 'Niaga.Asia',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 7.0,
                'scrape_priority' => 9,
                'reach_notes' => 'Media regional dan ekonomi untuk Kaltim.',
            ],
            [
                'domain' => 'nomorsatukaltim.disway.id',
                'name' => 'Nomor Satu Kaltim',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 7.0,
                'scrape_priority' => 10,
                'reach_notes' => 'Portal regional Kaltim pada network Disway.',
            ],
            [
                'domain' => 'editorialkaltim.com',
                'name' => 'Editorial Kaltim',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 6.5,
                'scrape_priority' => 11,
                'reach_notes' => 'Media regional Kaltim dengan bobot menengah.',
            ],
            [
                'domain' => 'arusbawah.co',
                'name' => 'Arusbawah.co',
                'source_type' => 'local_media',
                'media_scope' => 'local_samarinda',
                'dewan_pers_status' => null,
                'local_reach_weight' => 5.5,
                'scrape_priority' => 12,
                'reach_notes' => 'Media internal/brand lokal Samarinda.',
            ],
            [
                'domain' => 'busam.id',
                'name' => 'Busam.id',
                'source_type' => 'social_video',
                'media_scope' => 'niche_community',
                'dewan_pers_status' => null,
                'local_reach_weight' => 9.0,
                'scrape_priority' => null,
                'reach_notes' => 'Sumber social/video; jangan diprioritaskan untuk scraping artikel tahap awal.',
            ],
            [
                'domain' => 'samarindatv.com',
                'name' => 'Samarinda TV',
                'source_type' => 'social_video',
                'media_scope' => 'local_samarinda',
                'dewan_pers_status' => null,
                'local_reach_weight' => 8.0,
                'scrape_priority' => null,
                'reach_notes' => 'Sumber social/video; jangan diprioritaskan untuk scraping artikel tahap awal.',
            ],
        ];

        foreach ($newsSources as $source) {
            DB::table('news_sources')->updateOrInsert(
                ['domain' => $source['domain']],
                array_merge($source, [
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }

        \App\Models\AiPromptTemplate::updateOrCreate(
            ['name' => 'Analisis Berita Utama'],
            [
                'source_type' => 'article',
                'system_prompt' => 'Anda adalah AI analis berita senior. Analisis berita yang diberikan dan berikan respon dalam format JSON yang valid. Gunakan estimasi pembaca yang natural, spesifik, dan tidak dipaksa ke angka bulat generik tanpa alasan kuat.',
                'user_prompt_template' => 'Analisis berita berikut:\nJudul: {title}\nKonten: {content}\nPastikan estimasi pembaca bersifat natural dan realistis, bukan pembulatan mekanis.',
                'output_schema' => '{"type": "object", "properties": {"summary": {"type": "string"}, "sentiment": {"type": "string"}, "sentiment_score": {"type": "number"}, "main_issue": {"type": "string"}, "entities": {"type": "array"}, "risk_level": {"type": "string"}, "risk_reason": {"type": "string"}, "reach_estimate": {"type": "integer"}, "reach_score_10": {"type": "integer"}, "reach_level": {"type": "string"}, "reach_trend": {"type": "string"}, "reach_source": {"type": "string"}, "reach_confidence": {"type": "string"}, "reach_reason": {"type": "string"}, "recommendation": {"type": "string"}}}',
                'is_active' => true,
                'is_default' => true,
            ]
        );

        \App\Models\AiPromptTemplate::updateOrCreate(
            ['name' => 'Analisis Medsos Utama'],
            [
                'source_type' => 'social',
                'system_prompt' => 'Anda adalah AI analis media sosial. Analisis postingan medsos yang diberikan dan berikan respon dalam format JSON yang valid. Prioritaskan link, jenis media, caption, dan engagement untuk menentukan nilai konten.',
                'user_prompt_template' => 'Analisis postingan medsos berikut:\nPlatform: {platform}\nURL: {url}\nMedia Type: {media_type}\nMedia URL: {media_url}\nThumbnail URL: {thumbnail_url}\nAuthor: {author_name}\nKonten: {content}\nEngagement: {engagement_context}\nMedia Context: {media_context}\nKonteks Project: {project_context}',
                'output_schema' => '{"type": "object", "properties": {"summary": {"type": "string"}, "sentiment": {"type": "string"}, "sentiment_score": {"type": "number"}, "main_issue": {"type": "string"}, "entities": {"type": "array"}, "risk_level": {"type": "string"}, "risk_reason": {"type": "string"}, "reach_estimate": {"type": "integer"}, "reach_score_10": {"type": "integer"}, "reach_level": {"type": "string"}, "reach_trend": {"type": "string"}, "reach_source": {"type": "string"}, "reach_confidence": {"type": "string"}, "reach_reason": {"type": "string"}, "content_type": {"type": "string"}, "media_type": {"type": "string"}, "media_link_used": {"type": "string"}, "media_signal": {"type": "string"}, "local_relevance_score": {"type": "integer"}, "confidence_score": {"type": "integer"}, "confidence_level": {"type": "string"}, "signals_used": {"type": "array"}, "reasoning_summary": {"type": "string"}, "limitations": {"type": "string"}, "recommendation": {"type": "string"}}}',
                'is_active' => true,
                'is_default' => true,
            ]
        );

        \App\Models\TelegramSetting::updateOrCreate(
            ['id' => 1],
            [
                'bot_token' => '1234567890:ABCdefGhIJKlmNoPQRsTUVwxyZ',
                'default_chat_id' => '-100123456789',
                'is_active' => true,
            ]
        );

        // Seed default Apify Actors for Facebook, Instagram, Threads, YouTube, and X
        $defaultActors = [
            [
                'platform' => 'Facebook',
                'actor_name' => 'Facebook Posts Search Scraper',
                'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
                'function_type' => 'Search Post',
                'default_keyword' => 'politik',
                'default_limit' => 50,
                'status' => 'active',
                'keyword_field_mapping' => 'searchQueries',
                'output_mapping' => '{"postTimeRange":"{time_filter}","proxyConfiguration":{"useApifyProxy":true},"searchQueries":["{keyword}"],"maxPosts":"{limit}"}',
                'interval_minutes' => 240,
                'memory_limit' => 512,
                'range_mode' => '30d',
                'post_filter_enabled' => true,
                'priority' => 1,
                'cost_reference' => 3.9900,
            ],
            [
                'platform' => 'Instagram',
                'actor_name' => 'Instagram Scraper',
                'actor_slug' => 'apify/instagram-scraper',
                'function_type' => 'Search Post',
                'default_keyword' => 'pilkada',
                'default_limit' => 20,
                'status' => 'active',
                'keyword_field_mapping' => 'searchLimit',
                'interval_minutes' => 240,
                'memory_limit' => 1024,
                'range_mode' => '7d',
                'post_filter_enabled' => false,
                'priority' => 1,
                'cost_reference' => 3.0000,
            ],
            [
                'platform' => 'Threads',
                'actor_name' => 'Threads Search Scraper',
                'actor_slug' => 'apify/threads-scraper',
                'function_type' => 'Search Post',
                'default_keyword' => 'pemilu',
                'default_limit' => 20,
                'status' => 'active',
                'keyword_field_mapping' => 'searchQuery',
                'interval_minutes' => 240,
                'memory_limit' => 1024,
                'range_mode' => '7d',
                'post_filter_enabled' => false,
                'priority' => 1,
                'cost_reference' => 5.0000,
            ],
            [
                'platform' => 'YouTube',
                'actor_name' => 'YouTube Video Scraper',
                'actor_slug' => 'apify/youtube-scraper',
                'function_type' => 'Search Post',
                'default_keyword' => 'indonesia',
                'default_limit' => 20,
                'status' => 'active',
                'keyword_field_mapping' => 'searchQueries',
                'interval_minutes' => 240,
                'memory_limit' => 1024,
                'range_mode' => '7d',
                'post_filter_enabled' => false,
                'priority' => 1,
                'cost_reference' => 5.0000,
            ],
            [
                'platform' => 'X',
                'actor_name' => 'Twitter Search Scraper',
                'actor_slug' => 'apify/twitter-scraper',
                'function_type' => 'Search Post',
                'default_keyword' => 'berita',
                'default_limit' => 20,
                'status' => 'active',
                'keyword_field_mapping' => 'searchTerms',
                'interval_minutes' => 120,
                'memory_limit' => 1024,
                'range_mode' => '7d',
                'post_filter_enabled' => true,
                'priority' => 1,
                'cost_reference' => 0.4000,
            ]
        ];

        foreach ($defaultActors as $act) {
            \App\Models\ApifyActor::updateOrCreate(
                ['platform' => $act['platform'], 'actor_slug' => $act['actor_slug']],
                $act
            );
        }

        // No dummy article seeding.
    }
}
