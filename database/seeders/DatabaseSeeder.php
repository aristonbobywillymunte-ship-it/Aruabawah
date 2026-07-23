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
                'source_type' => 'local_media',
                'media_scope' => 'local_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 8.5,
                'scrape_priority' => 10,
                'reach_notes' => 'Portal berita lokal yang diproses sebagai sumber artikel HTML/manual.',
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

        \App\Models\AiPromptTemplate::updateOrCreate(
            ['name' => 'Saran Portal Manual'],
            [
                'source_type' => 'article',
                'system_prompt' => 'Anda adalah sistem ahli Reverse Engineering & HTML Anatomy Analysis untuk Web Scraping. Tugas Anda adalah membedah arsitektur DOM portal berita dan menghasilkan konfigurasi ekstraksi data (scraping JSON configuration) yang akurat.',
                'user_prompt_template' => "INFO PORTAL TARGET:\n- Nama Portal: {name}\n- Domain: {domain}\n- HTML Mentah: {html}\n\nATURAN MUTLAK:\n1. Nama portal dan domain WAJIB dipakai sebagai identitas utama portal.\n2. Input utama WAJIB adalah HTML mentah yang diberikan user.\n3. AI WAJIB membaca dan membedah HTML mentah tersebut terlebih dahulu.\n4. Prioritas pertama adalah menemukan Search URL internal yang benar dengan membaca anatomi website dari HTML itu.\n5. Setelah search URL dan selector konten utama ditemukan, lanjutkan ke selector penulis, lalu selector tanggal.\n6. Variabel pencarian WAJIB menggunakan placeholder exact: {query} (contoh: /search?key={query} atau /?s={query}).\n7. DILARANG mengasumsikan parameter bawaan WordPress (/?s=) jika situs menggunakan route custom seperti /search?key={query}.\n8. Jika domain adalah \"arusbawah.co\", search_url WAJIB: \"https://arusbawah.co/search?key={query}\".\n9. Tipe crawling WAJIB ditentukan otomatis oleh AI dan harus dipilih dari: html, rss, api.\n10. Jangan meminta user mengirim HTML atau URL lain. Gunakan HTML yang sudah ada di input.\n11. Output harus JSON murni. Jangan tambahkan salam, penjelasan, markdown, atau code fence.\n\nMETODOLOGI:\n- Cari form pencarian di HTML.\n- Ekstrak action dan name dari input.\n- Bentuk template search URL dengan {query}.\n- Tentukan crawling_type berdasarkan struktur halaman: html, rss, atau api.\n- Ambil selector artikel, link, konten, noise, penulis, dan tanggal.\n\nKELUARAN:\n- Balas hanya JSON valid sesuai schema.\n",\n+                'output_schema' => '{"type":"object","properties":{"base_url":{"type":"string"},"crawling_type":{"type":"string"},"search_url":{"type":"string"},"feed_url":{"type":"string"},"sitemap_url":{"type":"string"},"search_result_selector":{"type":"string"},"article_link_selector":{"type":"string"},"article_content_selector":{"type":"string"},"article_noise_selector":{"type":"string"},"article_author_selector":{"type":"string"},"article_date_selector":{"type":"string"},"ai_reason":{"type":"string"},"confidence":{"type":"number"}},"required":["base_url","crawling_type","search_url","search_result_selector","article_link_selector","article_content_selector","ai_reason","confidence"]}',
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
                'build' => 'latest',
                'timeout_seconds' => 10000,
                'no_timeout' => false,
                'interval_minutes' => 240,
                'memory_limit' => 512,
                'range_mode' => '30d',
                'priority' => 1,
            ],
            [
                'platform' => 'Instagram',
                'actor_name' => 'Instagram Hashtag Scraper',
                'actor_slug' => 'apify/instagram-hashtag-scraper',
                'function_type' => 'Search Post',
                'default_keyword' => null,
                'default_limit' => 50,
                'status' => 'active',
                'keyword_field_mapping' => 'hashtags',
                'output_mapping' => '{"hashtags":["{keyword}"],"resultsType":"posts","resultsLimit":"{limit}"}',
                'build' => 'latest',
                'timeout_seconds' => 10000,
                'no_timeout' => false,
                'interval_minutes' => 240,
                'memory_limit' => 1024,
                'range_mode' => '7d',
                'priority' => 1,
            ],
            [
                'platform' => 'TikTok',
                'actor_name' => 'TikTok Hashtag Scraper',
                'actor_slug' => 'clockworks/tiktok-hashtag-scraper',
                'function_type' => 'Search Post',
                'default_keyword' => null,
                'default_limit' => 50,
                'status' => 'active',
                'keyword_field_mapping' => 'hashtags',
                'output_mapping' => '{"customMapFunction":"(object) => { return {...object} }","endPage":1,"extendOutputFunction":"($) => { return {} }","maxItems":"{limit}","hashtags":["{keyword}"],"proxyConfiguration":{"useApifyProxy":true}}',
                'build' => 'latest',
                'timeout_seconds' => 10000,
                'no_timeout' => false,
                'interval_minutes' => 240,
                'memory_limit' => 2048,
                'range_mode' => '7d',
                'priority' => 1,
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
                'build' => 'latest',
                'timeout_seconds' => 10000,
                'no_timeout' => false,
                'interval_minutes' => 240,
                'memory_limit' => 1024,
                'range_mode' => '7d',
                'priority' => 1,
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
                'build' => 'latest',
                'timeout_seconds' => 10000,
                'no_timeout' => false,
                'interval_minutes' => 240,
                'memory_limit' => 1024,
                'range_mode' => '7d',
                'priority' => 1,
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
                'build' => 'latest',
                'timeout_seconds' => 10000,
                'no_timeout' => false,
                'interval_minutes' => 120,
                'memory_limit' => 1024,
                'range_mode' => '7d',
                'priority' => 1,
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
