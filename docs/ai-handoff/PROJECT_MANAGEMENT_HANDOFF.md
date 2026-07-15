# Project Management Handoff

## Aturan Pembuatan Proyek (Terkunci)
1. **Validasi Keywords/Topics:**
   - Input UI harus berupa string yang dipisah koma (contoh: `seno aji, wagub kaltim`).
   - Format wajib berupa **flat JSON array** string 1 dimensi (`["seno aji", "wagub kaltim"]`).
   - Objek JSON bersarang (`{"primary": [...]}`) dan array dalam array **ditolak keras**.
   - Keyword kosong dan duplikat akan otomatis dihapus dan difilter pada backend.
   - Proyek yang tidak memiliki keyword sama sekali akan ditolak dan memunculkan pesan error.
2. **Scraping Trigger (Fase Portal Berita):**
   - Dilarang men-dispatch `ApifyScrapingJob` atau Social Media Job lainnya saat proyek dibuat.
   - Proyek baru tidak mentrigger APIFY. Sebaliknya, ia **menunggu** jadwal dari `media_intelligent_scheduler_container` untuk melakukan loop scraping portal secara otomatis di background.
3. **Data Dummy:**
   - Dilarang keras membuat proyek atau user melalui Tinker, Seeder, Factory, maupun query SQL manual untuk kepentingan testing di database operasional.

Semua aturan ini didukung secara komprehensif oleh test case `ProjectTopicsValidationTest`.

## Update Koreksi: AI Dispatch State
- **Perubahan Operasional**:
  - Dispatch AI tidak lagi bergantung penuh pada cache lock Redis.
  - Status dispatch AI disimpan persisten di PostgreSQL melalui `ai_analysis_dispatch_states`.
  - Tujuannya mencegah duplikasi saat Redis restart/flush dan memberi ruang retry berbasis `next_retry_at`.
- **Aturan Status**:
  - `queued` / `processing` => jangan dispatch ulang.
  - `success` => jangan pernah dispatch ulang.
  - `retry_wait` => tunggu `next_retry_at`.
  - `failed` permanen => tunggu perubahan konfigurasi.
- **Tindakan Aman**:
  - Cache lock boleh tetap dipakai hanya sebagai race lock singkat.
  - Jangan menganggap lock Redis sebagai idempotensi permanen.

## Update: 2026-07-03 15:57 (Dampak Siklus Otomatis 5 Menit)
- Runtime project aktif tetap:
  - `2` `Rudy Masud`
  - `5` `samarinda`
  - `7` `seno aji`
- Setelah interval scraping diubah ke 5 menit, scheduler otomatis menambah artikel baru tanpa membuat duplicate pivot:
  - `Rudy Masud`: `project_articles=5`
  - `samarinda`: `project_articles=6`
  - `seno aji`: `project_articles=13`
- `project_articles` tetap sama dengan jumlah article attachment unik (`24` total, `24` distinct article_id), sehingga tidak ada bukti duplicate pivot dari siklus terbaru.
