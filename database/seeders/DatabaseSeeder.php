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
                'base_url' => 'https://prokal.co',
                'search_url' => 'https://www.prokal.co/search?q={query}',
                'search_result_selector' => 'article, article.post, .post-item, .search-result',
                'article_link_selector' => 'article a[href], h2.entry-title a[href], .post-title a[href]',
                'article_content_selector' => 'article .entry-content, .entry-content, .post-content, .read__content',
                'article_noise_selector' => '.sidebar, .related-posts, .wp-block-columns, .sharedaddy, .baca-juga, script, style, iframe',
                'article_author_selector' => '.author-name, .entry-author, .posted-by, .author, .byline',
                'article_date_selector' => 'time.entry-date, .post-date, .entry-date, .published, time',
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
                'base_url' => 'https://kaltimtoday.co',
                'search_url' => 'https://kaltimtoday.co/search?q={query}',
                'search_result_selector' => 'article, article.post, .post-item, .search-result',
                'article_link_selector' => 'article a[href], h2.entry-title a[href], .post-title a[href]',
                'article_content_selector' => 'article .entry-content, .entry-content, .post-content',
                'article_noise_selector' => '.sidebar, .related-posts, .wp-block-columns, .sharedaddy, .baca-juga, script, style, iframe',
                'article_author_selector' => '.author-name, .entry-author, .posted-by, .author',
                'article_date_selector' => 'time.entry-date, .post-date, .entry-date, .published',
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
                'base_url' => 'https://sapos.co.id',
                'search_url' => 'https://www.sapos.co.id/search?key={query}',
                'search_result_selector' => 'article, article.post, .post-item, .search-result',
                'article_link_selector' => 'article a[href], h2.entry-title a[href], .post-title a[href]',
                'article_content_selector' => 'article .entry-content, .entry-content, .post-content',
                'article_noise_selector' => '.sidebar, .related-posts, .wp-block-columns, .sharedaddy, .baca-juga, script, style, iframe',
                'article_author_selector' => '.author-name, .entry-author, .posted-by, .author',
                'article_date_selector' => 'time.entry-date, .post-date, .entry-date, .published',
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
                'base_url' => 'https://kaltimkece.id',
                'search_url' => 'https://kaltimkece.id/search?terms={query}',
                'search_result_selector' => 'div.search-results-container article, div.search-results-container article.post, div.search-results-container .post-item, article.post, .post-item',
                'article_link_selector' => 'article a[href], .entry-title a[href], .post-title a[href], a.article-link[href]',
                'article_content_selector' => 'div.kandela-html, div.entry-content, article',
                'article_noise_selector' => 'div[wire\\:id], script, style, svg, .ads, .sidebar, .related-posts, .wp-block-columns, .sharedaddy',
                'article_author_selector' => '.author-name, .entry-author, .posted-by, span.text-article-title',
                'article_date_selector' => 'time.entry-date, .post-date, .entry-date, div.text-article-meta > div:nth-child(2)',
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
                'base_url' => 'https://mediakaltim.com',
                'search_url' => 'https://mediakaltim.com/search?q={query}',
                'search_result_selector' => 'article, article.post, .post-item, .search-result',
                'article_link_selector' => 'article a[href], h2.entry-title a[href], .post-title a[href]',
                'article_content_selector' => 'article .entry-content, .entry-content, .post-content',
                'article_noise_selector' => '.sidebar, .related-posts, .wp-block-columns, .sharedaddy, .baca-juga, script, style, iframe',
                'article_author_selector' => '.author-name, .entry-author, .posted-by, .author',
                'article_date_selector' => 'time.entry-date, .post-date, .entry-date, .published',
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
                'base_url' => 'https://korankaltim.com',
                'search_url' => 'https://korankaltim.com/search?q={query}',
                'search_result_selector' => 'article, article.post, .post-item, .search-result',
                'article_link_selector' => 'article a[href], h2.entry-title a[href], .post-title a[href]',
                'article_content_selector' => 'article .entry-content, .entry-content, .post-content',
                'article_noise_selector' => '.sidebar, .related-posts, .wp-block-columns, .sharedaddy, .baca-juga, script, style, iframe',
                'article_author_selector' => '.author-name, .entry-author, .posted-by, .author',
                'article_date_selector' => 'time.entry-date, .post-date, .entry-date, .published',
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
                'base_url' => 'https://niaga.asia',
                'search_url' => 'https://niaga.asia/search?q={query}',
                'search_result_selector' => 'article, article.post, .post-item, .search-result',
                'article_link_selector' => 'article a[href], h2.entry-title a[href], .post-title a[href]',
                'article_content_selector' => 'article .entry-content, .entry-content, .post-content',
                'article_noise_selector' => '.sidebar, .related-posts, .wp-block-columns, .sharedaddy, .baca-juga, script, style, iframe',
                'article_author_selector' => '.author-name, .entry-author, .posted-by, .author',
                'article_date_selector' => 'time.entry-date, .post-date, .entry-date, .published',
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
                'base_url' => 'https://nomorsatukaltim.disway.id',
                'search_url' => 'https://nomorsatukaltim.disway.id/search?q={query}',
                'search_result_selector' => 'article, article.post, .post-item, .search-result',
                'article_link_selector' => 'article a[href], h2.entry-title a[href], .post-title a[href]',
                'article_content_selector' => 'article .entry-content, .entry-content, .post-content',
                'article_noise_selector' => '.sidebar, .related-posts, .wp-block-columns, .sharedaddy, .baca-juga, script, style, iframe',
                'article_author_selector' => '.author-name, .entry-author, .posted-by, .author',
                'article_date_selector' => 'time.entry-date, .post-date, .entry-date, .published',
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
                'base_url' => 'https://editorialkaltim.com',
                'search_url' => 'https://editorialkaltim.com/search?q={query}',
                'search_result_selector' => 'article, article.post, .post-item, .search-result',
                'article_link_selector' => 'article a[href], h2.entry-title a[href], .post-title a[href]',
                'article_content_selector' => 'article .entry-content, .entry-content, .post-content',
                'article_noise_selector' => '.sidebar, .related-posts, .wp-block-columns, .sharedaddy, .baca-juga, script, style, iframe',
                'article_author_selector' => '.author-name, .entry-author, .posted-by, .author, .byline',
                'article_date_selector' => 'time.entry-date, .post-date, .entry-date, .published, time',
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
                'system_prompt' => 'Anda adalah sistem ahli Reverse Engineering & HTML Anatomy Analysis untuk Web Scraping. Tugas Anda adalah membedah arsitektur DOM portal berita dan menghasilkan konfigurasi ekstraksi data (scraping JSON configuration) yang akurat, lengkap, dan konsisten. Jangan mengosongkan field hanya karena ragu jika masih ada petunjuk HTML yang masuk akal; isi kandidat terbaik yang paling mungkin dan turunkan confidence bila bukti lemah.',
                'user_prompt_template' => <<<'PROMPT'
INFO PORTAL TARGET:
- Nama Portal: {name}
- Domain: {domain}
- HTML Mentah: {html}

ATURAN MUTLAK:
1. Nama portal dan domain WAJIB dipakai sebagai identitas utama portal.
2. Input utama WAJIB adalah HTML mentah yang diberikan user.
3. AI WAJIB membaca dan membedah HTML mentah tersebut terlebih dahulu.
4. Fokus utama adalah selector artikel, isi artikel, penulis, tanggal, noise, dan juga search URL.
5. Search URL dan Selector Hasil Pencarian TIDAK boleh dibiarkan kosong hanya karena halaman yang dianalisis adalah artikel. Jika tidak ditemukan di HTML, isi kandidat terbaik berdasarkan struktur situs dan beri confidence rendah.
6. Jika HTML berisi halaman search/result, ambil search URL dan Selector Hasil Pencarian secara eksplisit dari struktur tersebut.
7. Variabel pencarian WAJIB menggunakan placeholder exact: {query} (contoh: /search?key={query} atau /?s={query}).
8. DILARANG mengasumsikan parameter bawaan WordPress (/?s=) jika situs menggunakan route custom seperti /search?key={query}. Jika situs custom, prioritaskan pola custom.
9. Jika domain adalah "arusbawah.co" dan search page ditemukan, search_url yang benar adalah "https://arusbawah.co/search?key={query}".
10. Tipe crawling WAJIB ditentukan otomatis oleh AI dan harus dipilih dari: html, rss, api.
11. Jangan meminta user mengirim HTML atau URL lain. Gunakan HTML yang sudah ada di input.
12. Output harus JSON murni. Jangan tambahkan salam, penjelasan, markdown, atau code fence.
13. Jika satu field tidak punya bukti kuat, tetap isi dengan kandidat terbaik yang paling masuk akal dan jelaskan keraguannya di ai_reason.
14. Jika HTML yang diberikan adalah HTML artikel, tetap upayakan mengisi search_url, search_result_selector, article_link_selector, article_author_selector, dan article_date_selector dari pola situs, sitemap, feed, breadcrumb, atau link internal yang paling dominan.

METODOLOGI:
- Bedah struktur HTML yang diberikan.
- Jika HTML search/result: ambil search URL dan selector hasil pencarian secara eksplisit.
- Jika HTML artikel: fokus pada selector isi artikel, link artikel, penulis, tanggal, noise, dan tetap cari pola search URL serta selector daftar artikel dari struktur situs yang paling mungkin.
- Tentukan crawling_type berdasarkan struktur halaman: html, rss, atau api.
- Jangan mengembalikan field kosong bila masih ada pola yang masuk akal untuk diisi.

KELUARAN:
- Balas hanya JSON valid sesuai schema.
PROMPT,
                'output_schema' => '{"type":"object","properties":{"base_url":{"type":"string"},"crawling_type":{"type":"string"},"search_url":{"type":"string"},"feed_url":{"type":"string"},"sitemap_url":{"type":"string"},"search_result_selector":{"type":"string"},"article_link_selector":{"type":"string"},"article_content_selector":{"type":"string"},"article_noise_selector":{"type":"string"},"article_author_selector":{"type":"string"},"article_date_selector":{"type":"string"},"ai_reason":{"type":"string"},"confidence":{"type":"number"}},"required":["base_url","crawling_type","search_url","feed_url","sitemap_url","search_result_selector","article_link_selector","article_content_selector","article_noise_selector","article_author_selector","article_date_selector","ai_reason","confidence"]}',
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
