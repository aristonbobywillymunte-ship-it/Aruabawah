# Project v.1 - Production Readiness QA Report

## 1. Ringkasan Status
- Status akhir: **SIAP PRODUCTION**
- Alasan singkat:
- Queue utama sudah bersih.
- Sistem worker dan scheduler hidup.
- Daftar artikel proyek sudah disaring agar hanya menampilkan portal dan Google News, bukan sosial.
- Ada `failed_jobs_recent=29` sebagai backlog historis, tetapi tidak menjadi blocker produksi saat ini.
- Telegram test nyata sempat gagal `401 Unauthorized`, lalu berhasil setelah `default_chat_id` diganti ke `840203231`.

## 2. Apa yang Dites
- Input proyek aktif untuk Rudy Masud dan Seno Aji.
- Worker scraping, AI, notification, apify, dan scheduler.
- Status queue `ai-analysis`, `ai-backfill`, `scraping`, dan `notification`.
- Status Telegram setting dan recipient.
- Test suite terkait:
  - `AiRequeueOrphanQueuedStatesTest`
  - `PipelineMonitorTest`
  - `RunNeedsRescrapeTest`

## 3. Error yang Ditemukan
- Tidak ada error aktif pada queue:
  - `queued=0`
  - `processing=0`
  - `retry_wait=0`
  - `analysis_failed=0`
  - `ai-analysis=0`
  - `ai-backfill=0`
  - `scraping=0`
  - `notification=0`
- Yang tersisa hanya riwayat kegagalan `failed_jobs`.
- 29 `failed_jobs` terbaru berasal dari `App\Jobs\AiAnalysisJob` dengan `MaxAttemptsExceededException`.
- Ini historis, tetapi tetap menjadi sinyal bahwa sistem sempat masuk kondisi backlog AI yang berat.
- Telegram sempat gagal karena chat target lama tidak valid, tetapi test ulang berhasil `200 OK`.

## 4. Apa yang Diperbaiki
- Tidak ada patch baru pada audit terakhir ini.
- Perbaikan yang sudah ada dan tetap valid:
  - label report untuk `invalid_ai_reach` sudah jelas
  - queue orphan requeue sudah aman
  - kartu proyek user tidak menampilkan `Perlu Scrape Ulang`
  - dashboard pending/rescrape tetap terkendali
  - daftar artikel proyek di detail/report sekarang hanya mengambil portal dan Google News, bukan social media

## 5. Hasil Test Portal
- Worker scraping hidup.
- Scheduler hidup.
- `scraping_queue=0`.
- Tidak ada error portal aktif pada audit ini.
- Filter artikel proyek aktif menyisihkan sumber sosial.
- Count hasil filter saat audit:
  - Project 2 Rudy Masud: `filtered=143`, `total=282`
  - Project 7 Seno Aji: `filtered=54`, `total=125`

## 6. Hasil Test Google News
- Jalur Google News tetap berada dalam alur portal/news.
- Tidak ada job Google News yang macet saat audit.
- Data artikel tetap tersimpan di tabel artikel proyek.
- Google News tetap ikut hitungan portal/news karena bukan sumber sosial.

## 7. Hasil Test Sosmed
- Worker sosmed/apify hidup.
- TikTok actor aktif tetap `paul_44/tiktok-search`.
- Telegram recipient tersedia.
- Tidak ada queue sosial yang macet saat audit.

## 8. Hasil Test AI
- `ai-analysis=0`
- `ai-backfill=0`
- `queued=0`
- `processing=0`
- `retry_wait=0`
- `analysis_failed=0`
- AI worker hidup dan tidak menumpuk job saat audit selesai.

## 9. Hasil Test Dashboard
- Dashboard user tetap bersih dari `Perlu Scrape Ulang`.
- `Belum Siap Tampil` tetap aman.
- `High Risk` dan `Failed` tetap terpisah.

## 10. Hasil Test Telegram
- Telegram settings aktif dan recipient ada.
- Tidak ada pengiriman massal.
- 1 pesan TEST nyata sudah dicoba dengan prefix:
  - `[TEST PRODUCTION QA]`
- Hasil awal: gagal `401 Unauthorized` saat memakai chat target lama.
- Setelah `default_chat_id` diganti ke `840203231`, test kirim berhasil `200 OK`.
- Artinya kredensial bot valid dan chat target yang benar sudah ditemukan.

## 11. Hasil Test Scheduler / Worker
- Scheduler container hidup.
- Worker AI hidup.
- Worker news hidup.
- Worker notification hidup.
- Worker apify hidup.
- Semua antrean utama tidak macet di akhir audit.

## 12. Sisa Risiko
- `failed_jobs_recent=29` masih ada sebagai backlog historis.
- Telegram test awal gagal, tetapi test ulang berhasil setelah koreksi `default_chat_id`.
- Jumlah failed jobs historis perlu dipantau agar tidak kembali menumpuk.

## 13. Keputusan Akhir
- **SIAP PRODUCTION**

### Penyebab
- Queue utama bersih.
- Worker dan scheduler hidup.
- Telegram test sudah berhasil.
- Daftar artikel proyek sudah bersih dari social media.

### Langkah Perbaikan Berikutnya
- Pantau queue dan failed jobs historis secara berkala.
- Lanjutkan monitoring Telegram recipient dan project activity seperti biasa.
