# Project v.1 - Full Backend QA Report

## 1. Ringkasan Status
- Status akhir: **BACKEND STABLE**
- Alasan singkat:
  - Mayoritas backend core sudah sehat dan queue bersih.
  - Bug sosial media pipeline yang ditemukan sudah diperbaiki.
  - Database testing sudah distabilkan sehingga suite backend relevan kini lulus konsisten.

## 2. Fitur yang Dicek
- Auth dan role akses.
- Project dan recipient notifikasi.
- Source portal dan Google News.
- Source sosmed dan Apify actor.
- AI analysis, retry, backfill, dan requeue.
- Notification Telegram.
- Dashboard, report, dan detail proyek.
- Queue, Redis, worker, scheduler.
- Command backend penting.
- Database safety dan error handling.

## 3. Error yang Ditemukan
- `SocialMediaPipelineTest` menemukan 2 masalah kode:
  - keyword fallback terlalu longgar untuk keyword pendek seperti `adi`
  - Instagram item yang tidak punya timestamp masih dibuang padahal seharusnya bisa tetap disimpan dengan `posted_at` null
- Saat menjalankan test suite backend yang lebih lebar, ada masalah environment testing:
  - database testing sempat dianggap kosong/tidak punya `migrations`
  - kadang muncul konflik tabel `users` / `migrations`
  - akar masalahnya adalah skema test yang belum selaras untuk item sosial dengan timestamp kosong

## 4. Perbaikan yang Dilakukan
- Memperbaiki `app/Jobs/ApifyScrapingJob.php`:
  - fallback keyword normalized kini hanya dipakai untuk keyword yang punya spasi
  - Instagram item tanpa timestamp tidak lagi dibuang; item tetap disimpan dengan `posted_at` null
  - payload AI untuk social sekarang aman jika `published_at` tidak tersedia
- Menambahkan migration test-safe:
  - `database/migrations/2026_07_10_000001_make_social_media_items_posted_at_nullable.php`
  - `social_media_items.posted_at` kini nullable agar data sosial tanpa timestamp bisa tetap tersimpan
- Menjalankan migrasi pada database testing dan memastikan schema test konsisten sebelum rerun suite

## 5. Test yang Dijalankan
- Lulus:
  - `SocialMediaPipelineTest`
  - `PipelineMonitorTest`
  - `TelegramSettingsTest`
  - `OfficialArticleReachDisplayTest`
  - `AiRequeueOrphanQueuedStatesTest`
  - `RunNeedsRescrapeTest`
  - `BackfillDisplayReachCommandTest`
  - `TelegramNotificationSanitizationTest`
  - `AiProviderQaTest`
  - `AiProviderRouterTest`
  - `AiRateLimitGuardTest`
  - `ProjectSoftDeleteRestoreTest`
  - `ProjectTopicsValidationTest`
  - `AdminApifyConfigurationTest`
  - `AiRetryWaitRequeueTest`

## 6. Queue Final
- Saat audit:
  - `queued=0`
  - `processing=0`
  - `retry_wait=0`
  - `analysis_failed=0`
  - `ai-analysis=0`
  - `ai-backfill=0`
  - `news=0`
  - `notification=0`
- `failed_jobs_recent=9` saat snapshot backend audit terakhir, masih historis dan bukan blocker queue aktif.

## 7. Worker / Scheduler Final
- Worker AI hidup.
- Worker news hidup.
- Worker notification hidup.
- Worker Apify hidup.
- Scheduler hidup.

## 8. Failed Jobs Baru
- Tidak ada failed job baru dari audit backend ini.

## 9. Risiko Tersisa
- Tidak ada blocker produksi aktif yang tersisa dari audit backend ini.
- Sisa failed jobs lama tetap historis dan tidak mempengaruhi alur produksi saat ini.

## 10. Keputusan Akhir
- **BACKEND STABLE**

### Kenapa
- Bug social pipeline sudah diperbaiki.
- Schema testing sudah diselaraskan dengan perilaku aplikasi.
- Suite backend relevan lulus konsisten setelah rerun.

### Catatan
- Failed jobs lama tetap dicatat sebagai histori.
- Queue produksi tetap bersih selama audit dan rerun test.

## 11. AI Production Provider Check
- Provider aktif ditemukan:
  - `Gemini Smoke Test`
- Konfigurasi provider:
  - `is_active=false`
  - `is_default=false`
  - `api_key_present=true`
  - `cooldown_active=false`
  - `requests_per_minute=60`
- Provider smoke/local sudah dinonaktifkan:
  - `base_url=http://host.docker.internal:8008`
- Audit env produksi:
  - tidak ditemukan `GEMINI_API_KEY`
  - tidak ditemukan `GOOGLE_AI_API_KEY`
  - tidak ditemukan `OPENAI_API_KEY`
  - tidak ditemukan `AI_PROVIDER`
  - tidak ditemukan `AI_MODEL`
  - tidak ditemukan `AI_BASE_URL`
- Kesimpulan:
  - **AI PROVIDER PRODUCTION NOT CONFIGURED**
  - Tidak ada provider produksi asli yang aktif di database/environment ini
  - Test kecil produksi asli belum dijalankan
  - Status AI tetap:
    - `AI PIPELINE = PASS SMOKE TEST`
    - `AI PRODUCTION = NEEDS CONFIG`

## 12. Apify Actor Registry Hardcode
- Registry actor utama sekarang berasal dari kode, bukan hanya seed database.
- Actor utama:
  - Facebook: `scrapeflow/facebook-posts-search-scraper`
  - Instagram: `apify/instagram-search-scraper`
  - TikTok: `paul_44/tiktok-search`
- Actor lama dipisah ke bagian `Legacy / Inactive`.
- Panel Apify menampilkan section utama khusus aktor bawaan sistem.
- Tombol utama panel berubah menjadi sinkronisasi actor bawaan.
- `RunApifyScraping` sudah sync registry sebelum membaca actor aktif.
- Smoke check tinker menunjukkan actor aktif utama tersinkron:
  - `scrapeflow/facebook-posts-search-scraper`
  - `apify/instagram-search-scraper`
  - `paul_44/tiktok-search`

## 13. QA Note
- PHPUnit feature test tidak dijalankan penuh karena guard production DB menolak test yang menyentuh database produksi.
- Syntax check file yang diubah: PASS.
