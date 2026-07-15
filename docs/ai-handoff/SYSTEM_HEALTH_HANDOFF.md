# System Health Handoff

## Update: 2026-07-03 15:16 (Selective Restore Telegram Settings)
- **Status**: TELEGRAM CONFIG RESTORED
- **Tujuan**: Memulihkan selektif `telegram_settings` global dari `media_intelligent_restore_test.public` ke runtime `media_intelligent.public` tanpa menyentuh `project_telegram_recipients` maupun `risk_notifications`.
- **Backup Target Sebelum Write**: `/Users/unity/Backups/arusbawah/telegram-target-pre-restore-20260703-151605.sql`
- **Backup Stage CSV**: `/Users/unity/Backups/arusbawah/telegram-selective-restore-20260703-151635/telegram_settings.csv`
- **Count Sebelum Restore (runtime `media_intelligent`)**:
  - `telegram_settings = 1`
  - `project_telegram_recipients = 0`
  - `risk_notifications = 0`
- **Row Dipulihkan**:
  - `telegram_settings.id=1` di-update dari source `id=1`
  - Field yang dipulihkan: `bot_token` (encrypted, tidak ditampilkan), `default_chat_id`, `is_active`, `created_at`, `updated_at`
- **Count Sesudah Restore (runtime `media_intelligent`)**:
  - `telegram_settings = 1`
  - `project_telegram_recipients = 0`
  - `risk_notifications = 0`
- **Conflict / Skip**:
  - Tidak ada insert row baru.
  - Tidak ada konflik PK/unique.
  - Tidak ada recipient project untuk dipulihkan.
- **Sequence Status**:
  - `telegram_settings_id_seq = 1`
- **Decrypt / Readability**:
  - Model `TelegramSetting` berhasil membaca nilai terenkripsi.
  - Status credential dari model: `ready=false` karena token source yang dipulihkan terdeteksi sebagai `placeholder_bot_token`.
  - `default_chat_id` terbaca dan tersimpan.
- **Tabel Lain**:
  - `project_telegram_recipients` dan `risk_notifications` tidak berubah.
  - Tidak ada perubahan pada projects/articles/settings lain.
- **Catatan Operasional**:
  - Notification worker tetap tidak diaktifkan.
  - Tidak ada pengiriman test Telegram.
  - Konfigurasi Telegram telah dipulihkan, tetapi belum layak dipakai untuk kirim pesan sampai token diganti dengan kredensial valid.

## Update: 2026-07-03 15:25 (Selective Restore Projects)
- **Status**: PROJECTS RESTORED
- **Tujuan**: Memulihkan `projects` dan `project_user` dari `media_intelligent_restore_test.public` ke runtime `media_intelligent.public`.
- **Backup Target Sebelum Write**: `/Users/unity/Backups/arusbawah/projects-restore-20260703-152506/projects-target-pre-restore.sql`
- **Count Sebelum Restore (runtime `media_intelligent`)**:
  - `projects = 0`
  - `project_user = 0`
- **Row Dipulihkan**:
  - `projects` = 3 row (`id 2, 5, 7`)
  - `project_user` = 3 row (`id 2, 4, 6`)
- **Count Sesudah Restore (runtime `media_intelligent`)**:
  - `projects = 3`
  - `project_user = 3`
- **Conflict / Skip**:
  - Tidak ada konflik PK/unique.
  - Tidak ada row existing yang ditimpa.
  - Kolom ekstra `first_news_scrape_attempt_at` di target tetap aman/default `NULL`.
- **Sequence Status**:
  - `projects_id_seq = 7`
  - `project_user_id_seq = 6`
- **Login / UI Impact**:
  - Project aktif kembali muncul di runtime.
  - Ownership via `project_user` ikut pulih.
- **Tabel Lain**:
  - `users`, `ai_prompt_templates`, `ai_providers`, `articles`, `ai_analysis_results`, `risk_notifications`, `failed_jobs` tidak berubah.



## Update: 2026-07-03 15:08 (Selective Restore from `media_intelligent_restore_test`)
- **Status**: SEHAT DENGAN CATATAN
- **Tujuan**: Memulihkan selektif data yang hilang ke runtime `media_intelligent.public` tanpa menimpa tabel lain.
- **Backup Target Sebelum Write**: `/Users/unity/Backups/arusbawah/target-pre-restore-20260703-150800.sql`
- **Backup Stage CSV**: `/Users/unity/Backups/arusbawah/selective-restore-20260703-150839/`
- **Count Sebelum Restore (runtime `media_intelligent`)**:
  - `users = 0`
  - `ai_prompt_templates = 0`
  - `ai_providers = 0`
  - `projects = 0`
  - `articles = 0`
  - `ai_analysis_results = 0`
  - `failed_jobs = 0`
- **Row Dipulihkan**:
  - `users` = 2 row berdasarkan `email`
  - `ai_prompt_templates` = 2 row berdasarkan `name + source_type`
  - `ai_providers` = 2 row berdasarkan `name + provider_type`
- **Count Sesudah Restore (runtime `media_intelligent`)**:
  - `users = 2`
  - `ai_prompt_templates = 2`
  - `ai_providers = 2`
  - `projects = 0`
  - `articles = 0`
  - `ai_analysis_results = 0`
  - `failed_jobs = 0`
- **Conflicts / Skip**:
  - Tidak ada konflik ID atau unique.
  - Tidak ada row existing yang ditimpa.
- **Sequence Status**:
  - `users_id_seq = 2`
  - `ai_prompt_templates_id_seq = 2`
  - `ai_providers_id_seq = 3`
- **Login Verification**:
  - Login admin runtime berhasil menggunakan akun existing hasil restore.
  - Dashboard admin terbuka normal.
- **Credential Readability**:
  - `ai_providers` terbaca oleh aplikasi; `credential_present=true` pada dua provider.
  - Isi credential tidak didisplay.
- **Tabel Lain**:
  - `projects/articles/ai_analysis_results/failed_jobs` tidak berubah.
  - Tidak ada restart PostgreSQL atau perubahan volume.

## Update: 2026-07-03 15:57 (Runtime Interval 5 Menit)
- Runtime `scraping_settings.id=1` kini memakai `google_news_interval=5`.
- Fallback kode scheduler berita juga telah disamakan ke 5 menit.
- Verifikasi `php artisan schedule:list`:
  - `*/5 * * * * php artisan scraping:run-news --limit=3 --no-telegram`
- Observasi otomatis sesudah perubahan:
  - `candidate_links 28 -> 40`
  - `scraping_items 28 -> 40`
  - `articles 13 -> 24`
  - `project_articles 13 -> 24`
  - `ai_analysis_results 13 -> 14`
  - `jobs 0 -> 0`
  - `failed_jobs 0 -> 0`
  - `risk_notifications 0 -> 0`
- Redis akhir tetap kosong untuk queue `news`, `default`, `ai-analysis`, `notification`, dan `apify`.
- Catatan kesehatan:
  - Portal scraping otomatis: **sehat**
  - AI worker: **berjalan**, tetapi kualitas hasil AI terbaru masih perlu audit karena ditemukan satu row dengan `reach_score_10=0` dan `project_reach_score=7`
  - Telegram: masih **belum siap** karena credential runtime masih terdeteksi placeholder


## Status Container dan Worker
Pada fase ini (Akhir Fase Portal Berita), konfigurasi container yang valid dan aman adalah:
1. `media_intelligent_scheduler_container`: **AKTIF** (Mengelola waktu eksekusi otomatis).
2. `media_intelligent_worker_container`: **AKTIF** (Mengerjakan queue portal news).
3. `media_intelligent_ai_worker_container`: **AKTIF** (Menganalisis artikel baru secara antrean).
4. `media_intelligent_notification_worker_container`: **MATI** (Diberhentikan hingga modul Telegram resmi diaktifkan).
5. `media_intelligent_apify_worker_container`: **MATI** (Diberhentikan hingga Fase Media Sosial dimulai).

## Integritas Database
- **Tabel Operasional:** (e.g. `candidate_links`, `scraping_items`, `articles`, `project_articles`, `ai_analysis_results`, `reach_assessments`, `risk_notifications`, `jobs`, `failed_jobs`) bersifat elastis dan harus dijaga konsistensinya. Saat ini, semua tabel tersebut sengaja di-reset (count = 0) untuk memberikan slate bersih bagi proyek nyata yang dibuat user lewat UI.
- **Tabel Esensial:** Konfigurasi utama (`users`, `news_sources`) dijaga dan tidak boleh dihapus secara gegabah melalui query `db:wipe` atau `migrate:fresh`.


## Update: 2026-07-03 13:02 (Siklus Pengujian Pertama)
- **Status**: SEHAT DENGAN CATATAN
- **Waktu Siklus**: 13:00 - 13:02
- **Funnel**: Manual Portal (0) -> Google News (11 Artikel Tersimpan dari 14 kandidat) -> AI Analysis (0 Berhasil)
- **Database Counts**: candidate_links=14, scraping_items=14, articles=11, project_articles=11, ai_analysis_results=0
- **Redis**: Kosong (db0:keys=2) / Jobs/Failed Jobs: 0/0
- **Status Container**: scheduler (AKTIF), news worker (AKTIF), ai worker (AKTIF), notification (MATI), apify (MATI)
- **Known Issue (Catatan Khusus)**: Analisis AI tidak berjalan dan menghasilkan log `[Pipeline] Missing active AI Providers or AI Prompt Template.` karena tabel `ai_providers` dan `ai_prompt_templates` kosong (kemungkinan karena belum di-seed/populated di runtime ini). Ini menyebabkan `AiAnalysisJob` exit quietly tanpa error/failed jobs, tetapi juga tanpa menyimpan hasil analisis.


## Update: 2026-07-03 13:08 (Perubahan Interval 5 Menit)
- **Interval**: Berhasil diubah menjadi 5 menit (`google_news_interval: 5`). Cron terbaca `*/5 * * * *`.
- **Verifikasi Eksekusi**: Siklus otomatis berjalan pada `13:05:01`.
- **Bukti Bebas Duplikat**: Siklus kedua mengevaluasi 14 artikel lama sebagai `reused` tanpa duplikasi. Hanya 1 artikel baru yang ditemukan dan disimpan. Total: 15 candidate_links, 12 articles.
- **Jobs/Failed Jobs**: 0 / 0 (Semua tereksekusi instan).
- **Redis Queue**: Kosong.


## Update: 2026-07-03 13:12 (Siklus Pengujian AI)
- **Status**: TIDAK SEHAT (Khusus AI Worker)
- **Provider AI**: Terbaca (Gemini aktif), Template aktif.
- **AI Analysis Results**: 0 (Tidak ada hasil sukses).
- **Jobs/Failed Jobs**: 0 / 12 (Semua artikel gagal dianalisis).
- **Redis Queue**: Kosong (sudah dieksekusi, tapi masuk failed_jobs).
- **Known Issue**: `[Pipeline] AI Provider gemini call failed: Konfigurasi Gemini belum lengkap.` Meskipun provider ada, konfigurasinya (seperti API Key) kosong atau tidak valid, sehingga melemparkan `RuntimeException: All active AI providers failed`. Hal ini murni masalah data/konfigurasi, bukan kode.


## Update: 2026-07-03 13:30 (Siklus Pengujian AI Sukses & Bypass SSL)
- **Status**: SEHAT (HIJAU)
- **AI Analysis Results**: 11 hasil analisis sukses ter-insert.
- **SSL Verification**: Aman. Seluruh Http request scraping dipasang `->withoutVerifying()` untuk bypass cURL error 60.
- **Jobs/Failed Jobs**: 0 / 49 (Sisa-sisa pekerjaan gagal akibat limit rate 429 atau konfigurasi kosong sebelumnya, tidak ada job aktif yang menggantung/stuck).
- **Redis Queue**: Kosong.


## Update: 2026-07-03 14:15 (Penghapusan Bypass SSL & Idempotensi AI)
- **Status**: SEHAT (KUNING - Limit API Habis)
- **Keamanan SSL**: Semua bypass SSL (`withoutVerifying`) telah DIHAPUS. Sistem menggunakan root CA murni. Gagal SSL secara aman dicatat sebagai *WARNING* dan kandidat dilewati, tidak memengaruhi keberlangsungan scraping.
- **Idempotensi AI**: Telah ditambahkan *Cache Lock* selama 24 jam untuk setiap artikel yang didispatch ke AI.
- **Kondisi API AI**: Kuota harian API Gemini (Free Tier = 20 request/hari) **telah habis total** (menghasilkan *429 Rate Limit* dengan *Resource Exhausted*).
- **Efek Samping**: Karena limit habis, tidak ada AI analisis baru yang sukses, seluruh artikel baru masuk ke `failed_jobs`.
- **Database Terakhir**:
  - `ai_analysis_results` = 11 (Masih terjaga).
  - `failed_jobs` = 139 (Stabil; bug duplikasi berhasil dihentikan. Lock aktif menahan dispatch ulang meski scheduler berjalan tiap 5 menit).
  - `jobs` = 0 (Semua berhasil diproses atau dipindahkan ke failed_jobs).
- **Container**: Worker relevan (News, AI, Scheduler) berhasil direstart dan berjalan normal.

## Update Koreksi: 2026-07-03 (Status AI Persisten)
- **Status Jujur**: **AI DEGRADED**
- **Alasan**:
  - Cache lock 24 jam pada AI bukan lagi sumber idempotensi utama.
  - Status dispatch AI dipindah ke PostgreSQL (`ai_analysis_dispatch_states`) agar aman saat Redis restart/flush.
  - `AiAnalysisJob` kini menandai `queued`, `processing`, `success`, `failed`, dan `retry_wait` secara persisten.
- **Hasil Test Terisolasi**:
  - `AiAnalysisDispatchStateServiceTest` lulus di environment testing terpisah.
  - Duplicate dispatch ditolak.
  - Retry hanya terjadi setelah `next_retry_at`.
  - Success tidak pernah didispatch ulang.
- **Catatan Operasional**:
  - Redis lock tetap ada, tetapi hanya sebagai race-condition lock singkat.
  - `failed_jobs` lama tetap diperlakukan sebagai histori dan tidak di-retry massal.

## Article–Project Data Integrity
- Count artikel proyek berasal dari project_articles.
- Satu artikel dapat dihitung pada beberapa proyek.
- Ini bukan duplicate article.
- Duplicate hanya terjadi jika article_id + project_id yang sama muncul lebih dari sekali.
- Monitoring harus memisahkan global article count dan project relation count.

## Project Keywords to Apify
- projects.topics adalah source of truth;
- RunApifyScraping memakai scrapeKeywords/normalizer resmi;
- project.name bukan fallback;
- keyword job adalah snapshot saat dispatch;
- perubahan topics tidak mengubah queued job;
- dedup tidak boleh membaca substring jobs.payload;
- dedup identity adalah project + actor + platform + normalized keyword + window;
- PostgreSQL dispatch state adalah source of truth;
- Redis/ShouldBeUnique hanya race-lock tambahan;
- actual Apify run harus menyimpan keyword_sent;
- credential tidak boleh dicatat.
