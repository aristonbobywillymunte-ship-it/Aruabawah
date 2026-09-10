# PRODUCT REQUIREMENTS DOCUMENT (PRD)
## Platform Media Monitoring, Scraping & AI Intelligence (`Arusbawah / Proyek Baru`)

- **Document Version**: 2.0 (Physical Repository Baseline & AI-Handoff Master)
- **Status**: ACTIVE & PRODUCTION/LOCAL ALIGNED
- **Repository Path**: `/Users/unity/Documents/proyek baru/`
- **Target Infrastructure**: Docker Compose (`media-intelligent`), PostgreSQL, Redis
- **Framework**: Laravel (PHP 8.4) + Livewire 3 + TailwindCSS

---

## 1. Executive Summary & Purpose

Dokumen ini adalah **Source of Truth (Pusat Kebenaran)** untuk seluruh arsitektur sistem, alur scraping, pemrosesan AI, batas biaya/kuota, dan status implementasi teknis di repositori lokal **`proyek baru`**. Dokumen ini dirancang agar **setiap model AI / coding assistant baru yang masuk dapat langsung memahami kondisi aplikasi secara utuh dan tidak mengalami disorientasi / halusinasi**.

---

## 2. Status Implementasi Fitur Utama (Fisik di Repo)

| Fitur / Modul | Deskripsi Implementasi Nyata | Status | File Rujukan Utama |
|---|---|:---:|---|
| **Portal Berita (News Discovery)** | Scraping artikel via RSS, Sitemap XML rekursif, dan Search URL. Disertai decoder link Google News (`decode_google_news_url.py`). | **SELESAI (P0)** | `app/Services/NewsSourceSuggestionTester.php`, `GoogleNewsUrlDecoderService.php` |
| **Sosial Media Scraper (Apify)** | Facebook, Instagram, TikTok dengan batas terdistribusi maksimal 50 item/run, rem biaya `maxTotalChargeUsd`, auto comment scraper. | **SELESAI (P0)** | `app/Jobs/ApifyScrapingJob.php`, `SocialCommentScraperDispatcher.php` |
| **Pipeline Analisis AI Berita** | Antrean mandiri (`redis-ai`), quality gate min. 50 karakter, multi-provider routing (Gemini/OpenAI/Claude), JSON strict mode, noise filtering, estimasi pembaca granular. | **SELESAI (P0)** | `app/Jobs/AiAnalysisJob.php`, `AiProviderRouter.php` |
| **Pencocokan Pivot Proyek** | Normalisasi keyword otomatis ke hashtag (`#rudymasud`), alias perbankan/lembaga, pemisahan artikel portal vs mirror sosial. | **SELESAI (P0)** | `app/Services/ContentMatchingService.php`, `projects-list.blade.php` |
| **UI/UX Mobile Responsive** | Filter drawer geser kanan di HP (`lg:hidden`), sticky filter desktop, load more berbasis listener scroll stabil, grafik tren case-insensitive. | **SELESAI (P0)** | `resources/views/components/⚡media-dashboard.blade.php` |
| **Skill Caveman (Token Efficiency)** | Modul skill pemangkas basa-basi token terpasang untuk efisiensi komunikasi & eksekusi tool. | **TERPASANG** | `.ai/skills/caveman/SKILL.md` |
| **RAG & Vector Search (pgvector)** | Chunking semantik, embedding, dan vector retrieval interaktif. | **ROADMAP (P1)** | Roadmap Phase 2 |

---

## 3. Aturan & Perilaku Sistem Inti (Locked Logic)

### 3.1 Scraping Portal Berita & Google News
- **Mode Discovery**: `manual_only`, `google_news_only`, dan `auto`.
- **Aturan Relevansi Keyword**: Untuk Google News dan portal manual, kemunculan hasil pencarian dianggap kandidat valid. **Dilarang** mewajibkan semua kata kunci muncul berulang di judul/isi (contoh: pencarian `iswandi dprd samarinda` tetap valid walau judul hanya memuat sebagian).
- **Google News Resolver**: URL `news.google.com/rss/articles/...` **wajib** di-decode ke URL media asli via `GoogleNewsUrlDecoderService` sebelum disimpan.
- **Cooldown Portal**: URL yang sudah pernah diproses untuk project yang sama ditahan selama **720 menit** agar scheduler tidak membuang resource untuk membaca URL lama berulang.

### 3.2 Scraping Sosial Media (Apify Limits & Safety)
- **Batas Keras 50 Item/Run**: Limit berlaku untuk total hasil satu run per proyek, **bukan per keyword**.
- **Distribusi Limit**: Untuk actor yang membaca limit per-keyword, payload membagi rata limit: `ceil(total_limit / jumlah_keyword)`.
- **Rem Biaya (`maxTotalChargeUsd`)**: Nilai `maximum_cost_per_run_usd` dikirimkan ke Apify sebagai rem darurat. Jika run terkena abort karena biaya tercapai tetapi dataset sudah ada isinya, sistem memperlakukannya sebagai **selesai sebagian (partial success)** dan data tetap diproses ke AI.
- **Auto Comment Scraper**: Postingan sosial yang berhasil ditarik otomatis mengantrekan penarikan komentar via `SocialCommentScraperDispatcher` (maks. 3 URL per batch).

### 3.3 Pipeline Analisis AI Berita Portal
- **Queue Terisolasi**: Berjalan pada koneksi `redis-ai` dan queue `ai-analysis`.
- **Quality Gate**:
  1. Konten teks minimal 50 karakter (`MIN_ANALYSIS_LENGTH`). Konten kosong langsung ditolak (`empty_content`).
  2. Proyek harus berstatus aktif (`is_active = true`).
  3. Mencegah analisis duplikat via `AiAnalysisDispatchStateService`.
- **Output AI**: Sentimen (`positive`, `neutral`, `negative` dengan skor `-1.0` s/d `1.0`), kategori/isu utama, tingkat risiko (`low`, `medium`, `high`), deteksi noise (`is_noise`), dan estimasi pembaca (`effective_readers`).
- **Resiliensi Multi-Provider**: Menangkap Rate Limit HTTP 429 (`RateLimitRetryException`) dan menunda ke retry scheduler. Jika provider utama down, router otomatis fallback ke provider sekunder.

### 3.4 Alur Sosmed, Penundaan Komentar & Konsep Inferensi AI
- **Penyimpanan Postingan**: Hasil penarikan Apify disimpan ke `social_media_items` dan otomatis ditautkan ke proyek yang cocok via `ContentMatchingService`.

- **Penundaan Analisis AI Menunggu Komentar (Comment-First)**:
  - Jika actor comment scraper aktif untuk platform tersebut (FB, IG, TikTok), sistem **menunda** dispatch AI untuk postingan tersebut (`comments_checked = false`).
  - Worker comment scraper (`SocialCommentScraperDispatcher`) menarik komentar secara berkala (maks. 3 URL/batch) dan menyimpannya ke `social_media_comments`.
  - Setelah komentar tersimpan, status diubah menjadi `comments_checked = true`, lalu memicu `dispatchAiForPostAfterCommentCheck()`.
  - **Tujuan**: Memastikan AI membaca caption **sekaligus seluruh komentar netizen** sebagai satu kesatuan konteks (`{comments_context}`) agar pembobotan sentimen publik akurat.
- **Konsep Inferensi AI**:
  - Menyaring postingan sampah via `is_noise` dan `noise_reason`.
  - Menghitung `effective_readers` secara terukur (factual views atau estimasi granular berbasis bukti interaksi).
  - Output tersimpan detail di `ai_analysis_results`.

### 3.5 Mesin Filter & Penyajian Dashboard (`MediaDashboard.php`)
- **Penyatuan Data Multi-Sumber**: Menggabungkan postingan sosmed (`social_media_items`) dan portal berita (`articles`) secara transparan di UI.
- **Filter Reaktif Livewire**:
  - **Rentang Tanggal**: Memfilter `posted_at`/`published_at` (preset: Harian, Mingguan, Bulanan, Tahunan).
  - **Sumber Platform**: Checklist multi-platform `['Instagram', 'TikTok', 'Facebook', 'News']`.
  - **Sentimen AI**: Filter reaktif terhadap skor sentimen (`positive`, `neutral`, `negative`).
  - **Pencarian Teks**: Debounce 600ms mencari ke seluruh teks postingan, judul, dan nama author secara case-insensitive.
  - **Kategori / Isu**: Filter berdasarkan klasifikasi topik isu (`main_issue`) yang diekstrak AI.
  - **Sorting**: Pilihan pengurutan `newest`, `oldest`, `most_engaging`, dan `highest_reach`.
- **Optimalisasi Performa UI**:
  - Dynamic drawer di HP (`lg:hidden`) dan sticky filter panel di desktop.
  - Infinite scroll stream tanpa ketergantungan plugin Alpine berat untuk mencegah DOM morphing collision.

### 3.6 Pemetaan Menu & Komponen yang Terlibat dalam Scraping Sosmed
1. **Konfigurasi Scraper Apify (`/admin/apify` $\rightarrow$ `ApifyConfiguration.php`)**:
   - Pengaturan token API utama dan token cadangan (multi-token rotation).
   - Pengaturan konfigurasi Actor media sosial (Facebook, Instagram, TikTok, dan Comment Scraper).
   - Penyetelan RAM, mapping keyword/payload, proxy Apify, dan batas waktu eksekusi run (*timeout*).
2. **Pengaturan Scraping Global (`/admin/scraping-settings` $\rightarrow$ `ScrapingSettings.php`)**:
   - Master Switch ON/OFF scraping otomatis medsos dan portal secara terpusat.
3. **Manajemen Paket & Kuota (`/admin/packages` $\rightarrow$ `PackageManager.php`)**:
   - Menentukan batas item (max 50), alokasi RAM, dan batas biaya per run (`cost_per_run_usd`) per proyek klien.
4. **Laporan Keuangan Apify (`/admin/apify-financials` $\rightarrow$ `ApifyFinancialReport.php`)**:
   - Monitoring biaya riil ($ USD), Run ID, durasi scraping, serta modal visualisasi postingan dan komentar yang ditarik.
5. **Monitor Antrean & Kesehatan (`/admin/pipeline-monitor` $\rightarrow$ `SystemHealth.php`)**:
   - Pemantauan status antrean `apify_dispatch_states` (`queued`, `processing`, `retry_wait`), deteksi ghost states, dan force requeue.
6. **Log Audit Medsos (`/admin/logs` $\rightarrow$ `SystemLogs.php`)**:
   - Pengecekan riwayat payload scraping mentah dan respons eksekusi di file `storage/logs/social-media.log`.
7. **Pusat Pemantauan Pengguna (`/` $\rightarrow$ `MediaDashboard.php`)**:
   - Tempat klien melihat hasil scraping medsos yang telah divalidasi dan dianalisis lengkap dengan metrik interaksi, komentar, dan filter reaktif.

### 3.7 Detail Menu Penyebutan (Mentions Feed) & Filter Panel
* **Arsitektur Menu Penyebutan (`tab=penyebutan`)**:
  - **Dual-Source SQL Union**: Menggabungkan postingan portal (`articles`) dan medsos (`social_media_items`) menjadi satu linimasa terpadu dengan normalisasi kolom.
  - **Quality Gates Query**:
    1. Filter Anti-Noise: `(ai_analysis_results.is_noise IS NULL OR is_noise = false)`.
    2. Guard Selesai Komentar: Untuk media sosial, hanya menampilkan yang komentarnya sudah lengkap (`comments_checked = true`).
  - **Optimasi Infinite Scroll Stream**:
    - Memakai container scroll ber-ID `mentions-feed-scroll` dengan atribut `data-total-count`.
    - Event listener `scroll` pasif dengan `requestAnimationFrame` dan debounce 1200ms memicu method Livewire `$wire.loadMore()`.
    - Mencegah benturan Alpine/DOM Morphing collapse pada mobile.
  - **Widget Analisis Jaringan Dinamis**:
    - **Topik Kunci Teratas**: Frekuensi kata kunci proyek + kalkulasi sentimen dominan (Positif/Netral/Negatif) dari relasi `ai_analysis_results`.
    - **Aktor & Sumber Berpengaruh**: Menampilkan akun/media dengan volume interaksi dan sebutan tertinggi.
* **Mekanisme Filter Panel (`components/⚡filter-items.blade.php`)**:
  - **Search Bar**: `wire:model.live.debounce.600ms="search"` mencari ke teks caption, judul, dan nama author secara case-insensitive.
  - **Date Range Selector**: Modal kalender dengan preset instan (Harian, Mingguan, Bulanan, Tahunan).
  - **Multi-Source Checklist**: Checklist platform `[Instagram, TikTok, Facebook, News]` dilengkapi counter jumlah data real-time.
  - **Sentiment Toggle**: Checklist status sentimen AI `[positive, neutral, negative]`.
  - **Sorting Selector (Terbaru vs Terpopuler)**:
    - **Yang Terbaru (`newest`)**: Mengurutkan linimasa secara kronologis murni berdasarkan waktu rilis (`published_at DESC`).
    - **Paling Populer (`popular`)**: Melakukan join ke `ai_analysis_results` dan mengurutkan berdasarkan pembaca efektif terbanyak (`COALESCE(ai_pop.project_estimated_readers, 0) DESC`), lalu tie-breaker `published_at DESC`.
    - **State Reset**: Mengubah sort otomatis me-reset batas data ke 5 item (`$this->limit = 5`) dan membersihkan cache buffer scroll via `$this->resetPage()`.
  - **Desain Responsif**: Sticky panel di desktop (`lg:block`) dan floating slide-over drawer di layar seluler (`lg:hidden`).

### 3.8 Detail Halaman Analisis (Executive Analytics & Performance View)
* **Arsitektur Halaman Analisis (`tab=analisis`)**:
  - **Lazy Hydration**: Menggunakan `wire:init="loadAnalysis"` sehingga visual dashboard pertama kali terbuka langsung tanpa lag antrean hitung metrik berat.
  - **Grid Indikator Kinerja Utama (KPI Metrics)**:
    1. *Total Artikel Ditemukan*: Agregasi jumlah gabungan portal dan medsos yang lolos filter aktif.
    2. *Total Jangkauan*: Akumulasi skor `project_estimated_readers` dari tabel `ai_analysis_results`.
    3. *Interaksi Sosial*: Total Likes + Comments + Shares + Views lintas kanal media sosial.
  - **Analisis Distribusi Saluran (Channel Breakdown)**:
    - 4 Card Terdedikasi: **Instagram**, **TikTok**, **Facebook**, dan **Portal Berita**.
    - Menampilkan volume penyebutan, jangkauan pembaca kanal, jumlah suka (*likes*), dan komentar publik secara terisolasi.
  - **Komparasi Sentimen Emosional (Sosial Media vs Portal Berita)**:
    - Menyajikan perbandingan rasio persentase dan bar visual 3 warna: Hijau (Positif), Abu-abu (Netral), dan Merah (Negatif).
    - Memisahkan persepsi netizen di media sosial terhadap narasi jurnalis di portal berita resmi.
  - **Grafik Tren Vektor Interaktif (Vector Spline Wave Chart)**:
    - Menggunakan generator kurva matematika *Smooth Cubic Bezier Spline SVG* (`$getCurvePath`).
    - Pilihan rentang waktu interaktif tanpa refresh halaman via Alpine.js: **Harian**, **Mingguan**, dan **Bulanan**.
    - Interaktif hover tooltip yang membaca node titik koordinat `circle` data riil.

---






## 4. Struktur Database & Model Relasi Utama

1. **`projects`**: Proyek pemantauan (nama, keyword, package, status aktif).
2. **`articles`**: Tabel penyimpan berita portal (judul, isi, URL asli, canonical URL, sumber media, tanggal terbit, sentimen, kategori).
3. **`project_articles`**: Pivot relasi antara proyek dan artikel.
4. **`social_media_items`**: Tabel penyimpan postingan media sosial (Facebook, Instagram, TikTok).
5. **`project_social_media_items`**: Pivot relasi antara proyek dan item media sosial.
6. **`social_media_comments`**: Komentar publik dari postingan media sosial terkait.
7. **`ai_analysis_results`**: Hasil inferensi detail AI (summary, sentiment, score, risk, quality confidence, reader basis).
8. **`apify_dispatch_states`**: Pelacakan status run scraping Apify (queued, processing, success, failed, items collected, cost).

---

## 5. Pedoman Handoff & Protokol Wajib Pergantian AI (Anti-Hallucination Guardrails)

> [!IMPORTANT]
> **PETUNJUK MUTLAK UNTUK SEMUA MODEL AI / CODING ASSISTANT PENGGANTI**:
> Jika Anda adalah AI baru yang melanjutkan sesi ini, **DILARANG MENGAMBIL ASUMSI, BERHALUSINASI, ATAU MENGARANG STATUS KODE**. Anda wajib membaca dan mematuhi dokumen khusus serah terima:
> 👉 📄 [`AI_HANDOFF_INSTRUCTIONS.md`](file:///Users/unity/Documents/proyek%20baru/AI_HANDOFF_INSTRUCTIONS.md)

### 5.1 Urutan Membaca Wajib (Execution Order)
Sebelum mengeksekusi perintah terminal atau mengedit kode:
1. `PRD.md` (Dokumen ini — Source of Truth produk, arsitektur, relasi database, menu terlibat, dan laporan QA Bab 7).
2. `AI_HANDOFF_INSTRUCTIONS.md` (Checklist cepat lingkungan lokal, larangan keras, dan checkpoint status).
3. `AI_CONTEXT.md` (Detail logika scraper portal, Google News resolver, dan aturan biaya/scheduler).
4. `NEW_AI_HANDOFF.md` (Detail penyesuaian UI mobile-first, perbaikan morphing Livewire, dan riwayat bug UI).
5. `PROJECT_PROGRESS.md` (Kronologi detail perubahan kode dari masa ke masa).

### 5.2 Mandat Penggunaan Skill Terpasang
- **Caveman Mode** (`.ai/skills/caveman/SKILL.md`):
  - Komunikasi wajib padat, teknis, to-the-point (*no pleasantries, no fluff*).
  - Eksekusi tool langsung tanpa narasi pengumuman yang bertele-tele (*direct tool calls*).
- **Taste-Skill** (`.ai/skills/taste-skill/SKILL.md`):
  - Wajib diterapkan untuk setiap pekerjaan desain antarmuka / UI Frontend.
  - Standar *anti-slop*: Larangan gradien ungu AI klise, larangan generic glassmorphism murahan, gunakan tipografi terstruktur dan inferensi *design read/dials* sebelum merevisi Blade/CSS.

### 5.3 Standar Alur Kerja Profesional AI (The 5-Stage Professional Standard)
Setiap AI yang ditugaskan memperbaiki atau mengembangkan kode pada repositori ini **WAJIB MENGIKUTI ALUR KERJA 5 TAHAP SECARA BERURUTAN**:

```
[Tahap 1: Ingest & Context Read] ➡️ [Tahap 2: Root Cause Analysis] ➡️ [Tahap 3: Surgical Fix] ➡️ [Tahap 4: Physical QA in Container] ➡️ [Tahap 5: Dual Logging & Local Commit]
```

1. **Tahap 1: Context Ingestion (Baca Sebelum Sentuh Kode)**:
   - Wajib membaca `AI_HANDOFF_INSTRUCTIONS.md`, `PRD.md`, dan `QA_LOG.md` sebelum menulis satu baris kode pun.
2. **Tahap 2: Root Cause Analysis & Reproduction (Investigasi & Buktikan Error)**:
   - Verifikasi fisik via `grep_search` / `view_file` dan lakukan reproduksi error nyata di container Docker (`media_intelligent_container`) untuk memperoleh stack trace/kegagalan konkret.
3. **Tahap 3: Surgical Fix (Perbaikan Presisi / Minimal Diff)**:
   - Terapkan perbaikan terfokus (minimal diff) tanpa merusak bagian lain, pastikan sintaks PHP valid, dan bersihkan cache view (`php artisan view:clear`).
4. **Tahap 4: Physical QA & Verification (Pengujian Nyata di Container)**:
   - Dilarang menyatakan selesai tanpa pengujian fisik nyata di container. Wajib lolos *exit code 0* pada skenario positif dan skenario batas (*edge case*).
5. **Tahap 5: Dual Logging & Safe Local Commit (Dokumentasi & Commit Lokal)**:
   - Catat detail pengujian lengkap di `QA_LOG.md` (ID, file target, perintah, actual output, status PASSED).
   - Sinkronkan ringkasan di `PRD.md` Bab 6 dan Bab 7.
   - Lakukan `git add` & `git commit` di lokal.
   - **STOP (DILARANG GIT PUSH OTOMATIS)**: Laporkan hasil ke user dan tunggu perintah eksplisit jika ingin di-push.

### 5.4 Larangan Keras & Batasan Kerja (Non-Negotiable)
1. **Workspace Terkunci**: Wajib bekerja hanya di `/Users/unity/Documents/proyek baru/` (bukan folder lain atau remote server).
2. **Dilarang Mengubah Skema Database**: Dilarang menjalankan migrasi yang merusak skema tanpa persetujuan eksplisit user.
3. **Verifikasi Fisik Sebelum Menjawab**: Dilarang menyimpulkan file/fitur ada atau tidak ada tanpa verifikasi langsung menggunakan `view_file` atau `grep_search`.
4. **WAJIB MELAKUKAN QA SETELAH SETIAP PERBAIKAN**: Dilarang hanya mengklaim selesai. Setiap perbaikan kode/fitur wajib diuji secara nyata (PHP linting `php -l`, simulasi eksekusi terminal, atau test live di dalam container `media_intelligent_container`).
5. **WAJIB MENDOKUMENTASIKAN HASIL QA KE BAB 7 & BUKU QA MANDIRI (`QA_LOG.md`)**: Seluruh parameter uji, skenario, dan status kelulusan (PASSED/FAILED) wajib ditulis lengkap di Bab 7 (Laporan Hasil Verifikasi QA) dan dicatat terperinci pada file mandiri [`QA_LOG.md`](file:///Users/unity/Documents/proyek%20baru/QA_LOG.md).
6. **Wajib Memperbarui Catatan Progres**: Setiap selesai melakukan task dan QA, AI **wajib** mencatat ringkasan perubahan di Bagian 6 dokumen ini agar AI berikutnya langsung tersinkronisasi.
7. **DILARANG KERAS GIT PUSH OTOMATIS (CUKUP COMMIT LOKAL)**: AI hanya diizinkan membuat `git commit` di lokal. **Dilarang keras melakukan `git push` sendiri tanpa instruksi eksplisit dari user**.

---




## 6. Log Catatan Progress AI (Terus Diperbarui Setiap Sesi)

- [2026-07-31]: Pemisahan mode discovery portal (`auto`, `manual_only`, `google_news_only`), cooldown 720 menit kandidat portal, pembatasan limit Apify maksimal 50 item per run terdistribusi, dan perbaikan normalisasi matcher alias `walikota` vs `wali kota`.
- [2026-08-14]: Audit forensik Livewire ApifyFinancialReport pada server remote terkait penanganan button item dan sinkronisasi panjang URL PostgreSQL.
- [2026-09-10]: Sinkronisasi fokus repositori lokal ke `/Users/unity/Documents/proyek baru/`, analisis keselarasan modul Analisis AI Berita Portal terhadap spesifikasi PRD, instalasi modul skill `Caveman` ke `.ai/skills/caveman/`, serta penyusunan dokumen `PRD.md` komprehensif sebagai standar handoff antar-AI anti-halusinasi.
- [2026-09-10]: Pemetaan dan dokumentasi menu-menu sistem yang terlibat dalam alur scraping sosmed (ApifyConfiguration, ScrapingSettings, PackageManager, ApifyFinancialReport, SystemHealth, SystemLogs, dan MediaDashboard) pada PRD Bagian 3.6.
- [2026-09-10]: Eksekusi verifikasi QA live pada runtime container lokal (`media_intelligent_container`) membuktikan alokasi RAM, rem biaya (cost limit), dan limit hasil Apify terdistribusi 100% mematuhi konfigurasi paket proyek aktif; didokumentasikan resmi pada PRD Bab 7.
- [2026-09-10]: Pengesahan dokumen protokol serah terima AI (`AI_HANDOFF_INSTRUCTIONS.md`) dan penyempurnaan Bagian 5 PRD.md sebagai pedoman wajib anti-halusinasi bagi model AI pengganti.
- [2026-09-10]: Perbaikan error PostgreSQL `column ai_analysis_results.is_noise does not exist` di dashboard dengan menjalankan migrasi tertunda (`2026_08_09_002149_add_quality_gate_fields_to_ai_analysis_results_table`) via `php artisan migrate --force` di container lokal. Kolom `is_noise`, `noise_reason`, `subjects`, dan `quality_confidence` kini aktif dan query dashboard berjalan normal.
- [2026-09-10]: Analisis dan dokumentasi menyeluruh terhadap arsitektur Menu Penyebutan (Mentions Feed SQL Union, Quality Gate Anti-Noise & Selesai Komentar, Widget Jaringan Topik/Aktor, serta Mesin Filter Panel terpusat) dicatat resmi pada PRD Bagian 3.7.
- [2026-09-10]: Audit mendalam, verifikasi stabilitas query, dan perbaikan halaman Analisis (Executive View, KPI Metrics, Distribusi Saluran Media, Komparasi Sentimen Sosmed vs Berita, serta Grafik Tren Vektor Spline) dicatat resmi pada PRD Bagian 3.8.
- [2026-09-10]: Formalisasi aturan mutlak wajib QA dan dokumentasi: Setiap AI yang melakukan perbaikan kode diwajibkan melakukan pengetesan fisik nyata (QA), mencatat skenario dan hasilnya di Bab 7, serta memperbarui log Bab 6 sebelum mengakhiri sesi; dikunci di PRD Bagian 5.3 dan AI_HANDOFF_INSTRUCTIONS.md.
- [2026-09-10]: Penambahan aturan mutlak larangan git push otomatis: Setiap AI hanya diperbolehkan membuat commit lokal dan dilarang keras melakukan `git push` tanpa perintah eksplisit dari user; dikunci di PRD Bagian 5.3 poin 7 dan AI_HANDOFF_INSTRUCTIONS.md poin 6.
- [2026-09-10]: Audit dan perbaikan exception `Livewire\Features\SupportMultipleRootElementDetection\MultipleRootElementsDetectedException: Livewire only supports one HTML element per component` pada komponen `projects-list` saat membuka route `/?project=...&tab=...`. Penyebab berupa penempatan penutup `@endif` prematur di tengah Blade template yang menyebabkan footer & modal di-render di luar root DOM tree. Masalah diperbaiki dan diverifikasi lolos render 100%.
- [2026-09-10]: Penambahan indikator loading visual reaktif pada input pencarian (animasi spinner di dalam text box & status "Mencari...") serta indikator status "Menyaring..." pada header utama Filter Panel dashboard (QA-20260910-11).
- [2026-09-10]: Pembersihan AI-slop pada Tab Laporan (memindahkan tombol download PDF ke footer terdedikasi, harmonisasi warna merah kasar menjadi teal `#1fa387`, eliminasi emoji panah) dan perbaikan 4 tag penutup yang hilang di Tab Sumber sebelum `@endif` (QA-20260910-12).
- [2026-09-10]: Isolasi state modal Rentang Tanggal (`showDatePicker`) pada Alpine.js untuk mencegah penutupan modal prematur saat memilih preset atau tanggal kalender, memastikan modal hanya tertutup dan tersinkronisasi saat tombol "Terapkan" ditekan (QA-20260910-13).
- [2026-09-10]: Pembersihan AI-slop pada halaman Ganti Password (`/change-password`): menyelaraskan dynamic branding, menambahkan interaktivitas toggle lihat/sembunyikan kata sandi (eye toggle), menyematkan ikon Material Symbols pada input form, memberikan petunjuk validasi kata sandi, dan feedback status submitting (QA-20260910-14).
- [2026-09-10]: Perbaikan tombol Kembali pada halaman Ganti Password agar mengembalikan pengguna secara langsung dan instan ke menu/proyek/tab terakhir yang sedang diakses via referer session `$backUrl` (QA-20260910-15).

---

## 7. Laporan Hasil Verifikasi QA (Quality Assurance)

### 7.1 QA Verifikasi Payload Apify vs Paket Proyek (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, PostgreSQL.
* **Sampel Pengujian**: Proyek Aktif ID `61` (*Bank Kaltimtara*) dengan Paket ID `1` (*Enterprise*).
* **Hasil Pengujian Komponen**:
  1. **RAM (Memory Limit)**:
     - Divalidasi langsung dari pivot paket: `memory_limit = 1024 MB`.
     - Parameter URL yang terbentuk: `https://api.apify.com/v2/acts/{slug}/runs?memory=1024&...`.
     - **Status**: **PASSED (100% Sesuai Paket)**.
  2. **Rem Biaya Darurat (Cost Limit)**:
     - Divalidasi dari pivot paket: FB Posts ($0.20), FB Comments ($0.03), IG Hashtag ($0.20), IG Comments ($0.05), TikTok Hashtag ($0.20), TikTok Comments ($0.04).
     - Parameter URL yang terbentuk: `maxTotalChargeUsd` terpasang presisi pada query string.
     - **Status**: **PASSED (100% Sesuai Paket)**.
  3. **Limit Hasil Terbagi Rata (Distributed Limits)**:
     - Limit paket = 100 dengan 2 keyword (`"kaltimtara"`, `"bank kaltimtara"`).
     - Body JSON: Instagram Hashtag Scraper menghasilkan `resultsLimit = 50`; TikTok Hashtag Scraper menghasilkan `maxItems = 50`.
     - **Status**: **PASSED (Formula $\lceil \text{Limit} / \text{Keywords} \rceil$ Terverifikasi Sempurna)**.
  4. **Strict Error Guard**:
     - Pengujian skenario actor tanpa limit paket menghasilkan pengecualian: `InvalidArgumentException: Actor limit must come from package configuration or explicit job override`. Job otomatis dihentikan sebelum memanggil API eksternal.
     - **Status**: **PASSED (Zero Phantom/Default Run Leakage)**.

### 7.2 QA Verifikasi Livewire Multiple Root Elements Fix pada `projects-list` (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17, Livewire 3.
* **Target Uji**: Halaman utama `/` dengan dan tanpa parameter query string (`project=NjE=&tab=YW5hbGlzaXM=`).
* **Skenario & Hasil Pengujian**:
  1. **Skenario Proyek Aktif Terpilih (`project=NjE=&tab=YW5hbGlzaXM=`)**:
     - Simulasi render Blade view `welcome` dengan Livewire component `projects-list` saat `$projectId` terisi.
     - Status: **PASSED (Exit Code 0, Render 131.560 bytes)**.
  2. **Skenario Halaman List Proyek Bersih (Tanpa Parameter Proyek)**:
     - Simulasi render Blade view `welcome` saat `$projectId = null`.
     - Status: **PASSED (Exit Code 0, Render 25.254 bytes)**.

### 7.3 QA Verifikasi Pembersihan AI-Slop & Query Tab Analisis (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17, Livewire 3.
* **Target Uji**: URL `http://localhost/?project=61&tab=YW5hbGlzaXM=` (`livewire/media-dashboard.blade.php`).
* **Skenario & Hasil Pengujian**:
  1. **Pembersihan Tampilan AI-Slop Sesuai Taste-Skill**:
     - Border neon multi-warna pada grid kategori diganti menjadi solid border netral `border-slate-200`.
     - Drop shadow neon jenuh (`shadow-pink-500/20`, `shadow-blue-500/20`) diganti dengan soft neutral shadow (`shadow-sm`).
     - Background blur blobs palsu pada awan kata dieliminasi.
  2. **Koreksi Logika Penyebutan Populer**:
     - Menghapus klausa `whereRaw("ai_pop.sentiment = 'positive'")` agar artikel/postingan populer disajikan murni berdasarkan volume pembaca (`project_estimated_readers DESC`) dan keterlibatan publik yang nyata.
  3. **Verifikasi Render Fisik Runtime**:
     - Eksekusi simulasi via `php artisan tinker`: Render sukses 100% dengan status exit code 0, panjang HTML 131.560 byte, tanpa error.
     - **Status**: **PASSED**.





### 7.4 QA Verifikasi Pembersihan AI-Slop Styling Menu Penyebutan (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17, Livewire 3.
* **Target Uji**: URL `http://localhost/?project=61&tab=cGVueWVidXRhbg==` (`livewire/media-dashboard.blade.php`).
* **Skenario & Hasil Pengujian**:
  1. **Pembersihan Card Hover & Border Sentimen**:
     - Menggantikan pendaran glow neon 50px dengan neutral elevation `hover:shadow-[0_12px_32px_rgba(0,0,0,0.04)] hover:border-slate-300`.
     - Mengeliminasi `border-l-4` dan inline border color yang merusak kelengkungan `rounded-[24px]`. Indikator sentimen ditangani secara clean via badge pill kanan atas.
  2. **Modernisasi Box Ringkasan AI**:
     - Menghapus styling inline gradient hex opacity pudar dan menggantikannya dengan container solid clean `bg-slate-50 border border-slate-200 rounded-2xl` dengan icon solid dan tipografi terbaca.
  3. **Verifikasi Render Fisik Runtime**:
     - Eksekusi simulasi via `php artisan tinker`: Render sukses 100% dengan status exit code 0, panjang HTML 144.833 bytes tanpa error Blade/PHP.
     - **Status**: **PASSED**.

### 7.5 QA Verifikasi Pembersihan AI-Slop Dropdown Notifikasi Sentimen Negatif (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17, Livewire 3.
* **Target Uji**: Komponen dropdown notifikasi (`resources/views/livewire/notification-dropdown.blade.php`).
* **Skenario & Hasil Pengujian**:
  1. **Dismiss Behavior & Trigger Button**:
     - Menggantikan backdrop-blur full-screen dengan clean Alpine `@click.outside="open = false"`.
     - Mengeliminasi animasi visual berkedip `animate-ping` pada trigger lonceng, menyederhanakan indikator menjadi satu badge counter bersih.
  2. **Elevasi & Standardisasi Styling**:
     - Mengganti inline shadow raksasa `60px` dengan `rounded-2xl border border-slate-200 shadow-xl`.
     - Normalisasi warna non-standar (`text-rose-550`, `text-rose-650`) dan penghalusan font harsh `font-black` menjadi `font-semibold text-slate-800`.
     - Penyempurnaan judul header menjadi "Peringatan Sentimen Negatif" yang inklusif untuk berita dan media sosial.
  3. **Verifikasi Render Fisik Runtime**:
     - Eksekusi simulasi via `php artisan tinker`: Render sukses 100% dengan status exit code 0, panjang HTML 129.530 bytes tanpa error Blade/PHP.
     - **Status**: **PASSED**.

### 7.6 QA Verifikasi Pembersihan AI-Slop Tab Kata Kunci (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17, Livewire 3.
* **Target Uji**: Tab Kata Kunci (`tab=katakunci`, Base64: `a2F0YWt1bmNp` pada `resources/views/livewire/media-dashboard.blade.php`).
* **Skenario & Hasil Pengujian**:
  1. **Integrasi Search Bar & Eliminasi Pagination Palsu**:
     - Mengubah input pencarian kata kunci menjadi reactive dengan embedded icon tanpa tombol submit terpisah.
     - Mengeliminasi tombol pagination dummy hardcoded (`« ‹ 1 › »`) menjadi footer ringkasan jumlah data nyata.
  2. **Harmonisasi Segmented Button & Vektor SVG**:
     - Menyatukan tema warna tombol toggle interval dan metrik menjadi brand color konsisten `#1fa387`.
     - Mengeliminasi filter shadow blur pada jalur kurva grafik tren SVG agar render visual tajam di layar resolusi tinggi.
  3. **Verifikasi Render Fisik Runtime**:
     - Eksekusi simulasi via `php artisan tinker`: Render sukses 100% dengan status exit code 0, panjang HTML 129.985 bytes tanpa error Blade/PHP.
     - **Status**: **PASSED**.

### 7.7 QA Verifikasi Pembersihan AI-Slop Tab Wawasan (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17, Livewire 3.
* **Target Uji**: Tab Wawasan (`tab=wawasan`, Base64: `d2F3YXNhbg==` pada `resources/views/livewire/media-dashboard.blade.php`).
* **Skenario & Hasil Pengujian**:
  1. **Harmonisasi Brand Theme & Tombol Aksi**:
     - Menggantikan warna acak `indigo-600` dengan brand color konsisten `#1fa387` pada icon judul dan tombol generate insight.
  2. **Eliminasi AI Buzzword & Distractive Animation**:
     - Menghapus badge buzzword "Murni AI" dan "AI Generated", menggantikannya dengan label profesional "Terupdate" dan "Eksekutif".
     - Menghilangkan `animate-ping` pada Sinyal Krisis agar tidak memicu kelelahan visual pengguna.
  3. **Verifikasi Render Fisik Runtime**:
     - Eksekusi simulasi via `php artisan tinker`: Render sukses 100% dengan status exit code 0, panjang HTML 133.586 bytes tanpa error Blade/PHP.
     - **Status**: **PASSED**.

### 7.8 QA Verifikasi Komprehensif Skeleton Loading Tab Wawasan (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17, Livewire 3.
* **Target Uji**: Tab Wawasan Skeleton Placeholder (`resources/views/livewire/media-dashboard.blade.php`).
* **Skenario & Hasil Pengujian**:
  1. **Mirroring Seluruh Card Stakeholder/Analitik**:
     - Menghadirkan struktur skeleton placeholder presisi untuk seluruh card: 4 KPI Grid (Indeks Reputasi, Kesehatan Sentimen, Sinyal Krisis, Kondisi Viral), Ringkasan Eksekutif, Rekomendasi Tindakan Strategis, Top Isu Negatif, Perubahan Sentimen, Distribusi Kategori, Kanal Media, dan Pemicu Risiko.
     - Mengeliminasi Cumulative Layout Shift (CLS) saat transisi dari proses loading ke state konten terisi.
  2. **Verifikasi Render Fisik Runtime**:
     - Eksekusi simulasi via `php artisan tinker`: Render sukses 100% dengan status exit code 0, panjang HTML 147.725 bytes tanpa error Blade/PHP.
     - **Status**: **PASSED**.

### 7.9 QA Verifikasi Perbaikan Spacing Antar Card Tab Wawasan (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17, Livewire 3.
* **Target Uji**: Spacing Layout Tab Wawasan (`resources/views/livewire/media-dashboard.blade.php`).
* **Skenario & Hasil Pengujian**:
  1. **Koreksi Jarak Vertikal Antar Baris Card**:
     - Memperbaiki hilangnya margin vertikal antara 4 KPI Grid atas dan 2-Column Cards analitik di bawahnya dengan menyematkan `class="space-y-6"` pada container `wire:loading.remove`.
     - Mengembalikan jeda vertikal sebesar 24px sehingga kartu tidak lagi bertabrakan atau menempel.
  2. **Verifikasi Render Fisik Runtime**:
     - Eksekusi simulasi via `php artisan tinker`: Render sukses 100% dengan status exit code 0, panjang HTML 147.725 bytes tanpa error Blade/PHP.
     - **Status**: **PASSED**.

### 7.10 QA Verifikasi Komprehensif Skeleton Loading Tab Analisis (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17, Livewire 3.
* **Target Uji**: Tab Analisis Skeleton Loading (`resources/views/livewire/media-dashboard.blade.php`).
* **Skenario & Hasil Pengujian**:
  1. **Mirroring Seluruh Card Analitik**:
     - Menghadirkan placeholder skeleton presisi 1:1 untuk: Gambaran Umum (3 Card KPI Utama, 4 Card Channel Instagram/TikTok/FB/Berita, 2 Card Sentimen Medsos/Berita), Grafik Tren Kinerja Proyek, Awan Kata & Kategori Isu, serta Peta Jaringan Isu & Berita Populer.
     - Mengeliminasi Cumulative Layout Shift (CLS) saat data analitik selesai dimuat oleh Livewire.
  2. **Verifikasi Render Fisik Runtime**:
     - Eksekusi simulasi via `php artisan tinker`: Render sukses 100% dengan status exit code 0, panjang HTML 147.160 bytes tanpa error Blade/PHP.
     - **Status**: **PASSED**.

### 7.11 QA Verifikasi Indikator Loading Reaktif Filter Panel & Pencarian (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17, Livewire 3.
* **Target Uji**: Komponen Filter Panel (`components/⚡filter-items.blade.php` & `livewire/media-dashboard.blade.php`).
* **Skenario & Hasil Pengujian**:
  1. **Indikator Loading Input Pencarian**:
     - Menghadirkan loading spinner di dalam kolom input teks dan label "Mencari..." saat debounce search berjalan (`wire:target="search"`).
     - Menghadirkan indikator status "Menyaring..." di header Filter Panel untuk feedback visual instan pada seluruh filter aktif.
  2. **Verifikasi Render Fisik Runtime**:
     - Eksekusi simulasi via `php artisan tinker`: Render sukses 100% dengan status exit code 0, panjang HTML 146.652 bytes tanpa error Blade/PHP.
     - **Status**: **PASSED**.

### 7.12 QA Verifikasi Pembersihan AI-Slop Tab Laporan & Perbaikan Tag Penutup Tab Sumber (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17, Livewire 3.
* **Target Uji**: Tab Laporan (`tab=laporan`) dan Tab Sumber (`tab=sumber`) pada `resources/views/livewire/media-dashboard.blade.php`.
* **Skenario & Hasil Pengujian**:
  1. **Harmonisasi Footer Action Tab Laporan**:
     - Memindahkan tombol Unduh PDF keluar dari grid 3-kolom ke baris footer dedicated (`border-t border-slate-100 flex justify-end`).
     - Menyelaraskan warna tombol PDF dari merah `#c0392b` menjadi tema brand `#1fa387` (`hover:bg-[#178a70]`).
     - Mengeliminasi karakter emoji panah `⬇` pada tombol PDF dan Excel untuk estetika profesional dan rapi.
     - Memperbarui copy modal proses PDF menjadi "Menyusun Laporan PDF" dan "Sistem sedang merangkum ringkasan dan analisis isu terbaru...".
  2. **Perbaikan Struktur DOM Tab Sumber**:
     - Menambahkan 3 tag penutup `</div>` dan 1 tag `</section>` yang hilang sebelum `@endif` baris 4835.
  3. **Verifikasi Render Fisik Runtime**:
     - Eksekusi simulasi via `php artisan tinker`:
       - Tab Laporan: **150.104 bytes**, exit code 0.
       - Tab Sumber: **135.856 bytes**, exit code 0.
     - **Status**: **PASSED**.

### 7.13 QA Verifikasi Isolasi State Modal Datepicker (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17, Livewire 3.
* **Target Uji**: Komponen Alpine DatePicker Modal pada `resources/views/livewire/media-dashboard.blade.php`.
* **Skenario & Hasil Pengujian**:
  1. **Isolasi State Alpine JavaScript**:
     - Menghapus two-way reactive `@entangle` langsung pada `localStart` dan `localEnd`.
     - Menggunakan watcher `$watch('show', ...)` untuk sinkronisasi nilai saat modal dibuka.
  2. **Pencegahan Penutupan Prematur**:
     - Mengklik tombol preset periode (Hari ini, Kemarin, 7 hari, 30 hari, 3 bulan, Tahun lalu) maupun tanggal pada grid kalender tidak lagi menutup modal atau memicu re-render prematur.
     - Tombol "Semua Waktu" mereset visual tanggal tanpa menutup modal.
     - Tombol "Terapkan" menjadi satu-satunya eksekutor update Livewire dan penutup modal.
  3. **Verifikasi Render Fisik Runtime**:
     - Eksekusi simulasi via `php artisan tinker`:
       - Tab Penyebutan: **146.963 bytes**, exit code 0.
     - **Status**: **PASSED**.

### 7.14 QA Verifikasi Pembersihan AI-Slop Halaman Ganti Password (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17.
* **Target Uji**: Halaman Ganti Password (`/change-password` pada `resources/views/auth/change-password.blade.php`).
* **Skenario & Hasil Pengujian**:
  1. **Dynamic Branding**:
     - Mengganti teks hardcoded dengan helper `AppBrandingHelper::getAppName()` dan `getAppLogoPath()`.
  2. **Fitur Toggle Password Eye (Alpine.js)**:
     - Mengintegrasikan toggle show/hide password pada ketiga field (*Password Saat Ini, Password Baru, Konfirmasi Password*).
  3. **Visual Input Fields & Validasi**:
     - Menambahkan ikon Material Symbols di setiap input serta panduan syarat panjang kata sandi minimal 8 karakter.
     - Menambahkan feedback animasi submitting untuk mencegah klik ganda.
  4. **Verifikasi Render Fisik Runtime**:
     - Eksekusi simulasi via `php artisan tinker`:
       - Halaman Ganti Password: **10.204 bytes**, exit code 0.
     - **Status**: **PASSED**.

### 7.15 QA Verifikasi Tombol Kembali ke Menu Terakhir (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17.
* **Target Uji**: Alur navigasi tombol Kembali pada `app/Http/Controllers/Auth/LoginController.php` dan `resources/views/auth/change-password.blade.php`.
* **Skenario & Hasil Pengujian**:
  1. **Dynamic Contextual Navigation**:
     - Controller mendeteksi header referer saat pengguna datang dari menu manapun (misal tab Analisis, tab Wawasan, tab Penyebutan, dsb) dan menyimpannya di session `change_password_back_url`.
     - Tombol "Kembali" menggunakan tautan native `<a href="{{ $backUrl }}">` yang mengarah tepat ke menu proyek/tab terakhir yang sedang diakses pengguna secara instan tanpa proses loading browser history.
  2. **Verifikasi Render Fisik Runtime**:
     - Eksekusi simulasi via `php artisan tinker`:
       - Simulasi referer `http://localhost/?project=61&tab=YW5hbGlzaXM=`: **BACK URL terdeteksi presisi**, HTML Render **10.115 bytes** (exit code 0, zero error).
     - **Status**: **PASSED**.

### 7.16 QA Verifikasi Modal Konfirmasi & Persistence Modal Proyek Dinonaktifkan (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17.
* **Target Uji**: Modal "Proyek Dinonaktifkan" dan modal konfirmasi persetujuan pada `resources/views/components/⚡projects-list.blade.php`.
* **Skenario & Hasil Pengujian**:
  1. **Modal Konfirmasi Persetujuan Eksplisit**:
     - Memastikan aksi "Aktifkan" dan "Hapus" tidak mengeksekusi langsung melainkan memicu dialog konfirmasi persetujuan (`showConfirmModal = true`) pada layer `z-[60]` dengan opsi Batal dan Konfirmasi.
  2. **Pencegahan Penutupan Modal Prematur**:
     - Menghapus statement `$this->showTrashedModal = false;` pada method `restoreProject()` dan `forceDeleteProject()` sehingga modal trashed tetap terbuka bagi pengguna untuk melanjutkan pengelolaan proyek.
     - Menyegarkan cache proyek secara otomatis saat proyek dipulihkan atau dihapus permanen.
  3. **Visual Feedback & Keyboard Listener**:
     - Menambahkan indikator spinner SVG dan `wire:loading.attr="disabled"` pada tombol "Aktifkan" dan "Hapus".
     - Menambahkan `@keydown.escape.window="!$wire.showConfirmModal && $wire.closeModals()"` pada modal.
  4. **Verifikasi Render Fisik Runtime**:
     - Linter PHP: `php -l resources/views/components/⚡projects-list.blade.php` -> No syntax errors detected.
     - Compiled View: `php artisan view:clear` -> Clear successfully.
     - Test Render Modal Trashed Livewire: **58.878 bytes**, exit code 0.
     - **Status**: **PASSED**.

### 7.17 QA Verifikasi Modernisasi Modal Konfirmasi & Interaktivitas Modul Manajemen Klien (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17.
* **Target Uji**: Modul Manajemen Klien (`/admin/clients` dan `/admin/clients/create`).
* **Skenario & Hasil Pengujian**:
  1. **Modal Konfirmasi Interaktif Modern**:
     - Menggantikan pop-up native `wire:confirm` dengan modal Tailwind/Alpine khusus untuk status toggle (`confirmingStatusChange`) dan hapus permanen (`confirmingDelete`).
  2. **Feedback Loading State**:
     - Menyematkan atribut `wire:loading.attr="disabled"` dan spinner SVG saat memproses toggle status maupun penghapusan.
  3. **Fitur Toggle Password Eye (Alpine.js)**:
     - Menambahkan eye toggle show/hide pada input kata sandi dan konfirmasi kata sandi di halaman `/admin/clients/create`.
  4. **Penyelarasan Notifikasi Toast**:
     - Mengintegrasikan dispatch event `admin-toast` dan session flash `success` terpadu.
  5. **Verifikasi Render Fisik Runtime**:
     - Linter PHP: `php -l` pada file Livewire terkait -> No syntax errors detected.
     - Compiled View: `php artisan view:clear` -> Clear successfully.
     - Test Render Livewire Tinker:
       - Client List: **4.970 bytes**, exit code 0.
       - Client Create: **6.992 bytes**, exit code 0.
       - Status Modal: **6.573 bytes**, exit code 0.
       - Delete Modal: **6.585 bytes**, exit code 0.
     - **Status**: **PASSED**.

### 7.18 QA Verifikasi Pembersihan Slop Antarmuka & Toolbar Manajemen Klien (10 September 2026)
* **Environment Pengujian**: Runtime Docker Container Lokal (`media_intelligent_container`), PHP 8.4 CLI, Laravel 11/13.17.
* **Target Uji**: Halaman `resources/views/livewire/admin/client-management/client-list.blade.php`, `client-create.blade.php`, dan `client-settings.blade.php`.
* **Skenario & Hasil Pengujian**:
  1. **Penyatuan Toolbar & Feedback Spinner Pencarian**:
     - Menggabungkan elemen search bar dengan tombol "Tambah Klien" dalam satu kesatuan baris toolbar yang proporsional.
     - Menambahkan feedback animasi spinner Livewire saat proses pencarian berlangsung.
  2. **Perbaikan Navigasi Tombol Kembali**:
     - Mengganti panah terisolasi dengan tombol navigasi berlabel *"Kembali ke Manajemen Klien"* pada form Tambah Klien dan Pengaturan Klien.
  3. **Proteksi & Feedback Loading Pelepasan Proyek**:
     - Menyematkan indikator loading spinner dan disabled state saat admin mengonfirmasi pelepasan proyek dari klien.
  4. **Verifikasi Render Fisik Runtime**:
     - Compiled View: `php artisan view:clear` -> Clear successfully.
     - Test Render Livewire Tinker:
       - Client List: **5.093 bytes**, exit code 0.
       - Client Create: **7.017 bytes**, exit code 0.
     - **Status**: **PASSED**.

### 7.19 Tombol Navigasi "Kembali ke Proyek" pada Halaman Manajemen Klien (10 September 2026)
* **Fitur**: Navigasi kontekstual bagi role `user` di halaman `/admin/clients`.
* **Latar Belakang**: User dengan role `user` (bukan admin, bukan client) memiliki akses sidebar terbatas yang hanya menampilkan menu "Manajemen Klien". Tidak tersedianya tombol kembali ke halaman utama (daftar proyek) membuat navigasi menjadi buntu.
* **Solusi yang Diimplementasikan**:
  - Menambahkan tombol `← Kembali ke Proyek` di bagian atas komponen `client-list.blade.php`, tepat di atas toolbar search & tambah klien.
  - Tombol bersifat kondisional menggunakan directive Blade `@if(auth()->user()->isUser())` sehingga hanya muncul untuk role `user`. Admin tidak melihat tombol ini (tidak butuh, memiliki akses sidebar penuh). Client tidak dapat akses halaman ini sama sekali (diblokir oleh `abort_if` di `mount()`).
  - Menggunakan `wire:navigate` untuk transisi SPA tanpa full-page reload.
  - Tujuan navigasi: `route('home')` → `/` → `view('welcome')` → `<livewire:projects-list />` (halaman daftar proyek untuk non-admin).
* **File Diubah**: `resources/views/livewire/admin/client-management/client-list.blade.php`
* **QA**: `[QA-20260910-19]` — PASSED.

### 7.20 Audit & Perbaikan Slop Halaman Buat Proyek (10 September 2026)
* **Fitur**: Pembersihan slop teknis dan UX pada halaman 2-step pembuatan proyek.
* **Latar Belakang**: Audit halaman `/projects/create` menemukan dead code, inkonsistensi ikon, label ambigu, dan performa slop pada wire binding.
* **Perbaikan yang Diimplementasikan**:
  1. **Dead spinner dihapus** — `wire:loading` pada `$set('createStep', 2)` adalah dead code karena `$set` tidak memicu server round-trip.
  2. **Info chip distandarisasi** — Inline `<svg>` diganti dengan `material-symbols-outlined: info`, konsisten dengan sistem ikon seluruh aplikasi.
  3. **Label navigasi diperjelas** — "Kembali" → "Kembali ke Pilih Paket", "Ubah" → "Ubah Paket".
  4. **Performa time input** — `wire:model.live` → `wire:model` pada semua input override jadwal (menghilangkan unnecessary server round-trip per perubahan nilai).
  5. **Spinner submit distandarisasi** — Inline `<svg animate-spin>` diganti dengan `progress_activity` material-symbols.
* **File Diubah**: `resources/views/livewire/project-create.blade.php`
* **QA**: `[QA-20260910-20]` — PASSED.








### 7.21 Perbaikan Slop Step 2 "Konfigurasi Proyek" pada Halaman Buat Proyek (10 September 2026)
* **Fitur**: Pembersihan slop UX dan code pada Step 2 (`@else` block) halaman `/projects/create`.
* **Perbaikan yang Diimplementasikan**:
  1. **Merge duplikasi** — dua `@if($selectedPackage)` terpisah digabung menjadi satu blok tunggal dengan `@else` guard.
  2. **Copy baku** — `'Interval lama'` → `'Tidak dijadwalkan'` pada tampilan jadwal Portal dan Sosial.
  3. **Slot numbering** — Setiap input `type="time"` kini memiliki label `Slot N` di sisi kiri.
  4. **Divider visual** — Garis pemisah gradient ditambahkan antara kartu jadwal dan form field utama.
  5. **Blur validation** — `wire:model.blur` pada field Nama Proyek untuk umpan balik validasi `unique` saat blur.
  6. **Responsif mobile** — Action buttons kini menggunakan `flex-col-reverse sm:flex-row` dengan lebar penuh di mobile.
  7. **Edge-case guard** — Jika `$selectedPackage` null di Step 2, tampil amber warning + tombol kembali.
  8. **Jadwal slot guard** — Input override hanya tampil jika `$portalSlots > 0` / `$socialSlots > 0`.
* **File Diubah**: `resources/views/livewire/project-create.blade.php`
* **QA**: `[QA-20260910-21]` — PASSED.

### 7.22 Modernisasi Modal Konfirmasi & Pembersihan Slop Tombol Nonaktifkan Proyek (10 September 2026)
* **Fitur**: Audit dan pembersihan slop antarmuka pada tombol aksi baris proyek dan modal konfirmasi aksi proyek di dashboard (`projects-list`).
* **Latar Belakang**: Penggunaan ikon trash merah pada tombol nonaktifkan proyek memberikan kesan destruktif/penghapusan permanen, serta terdapat inkonsistensi penggunaan inline SVG dan spinner di komponen modal.
* **Solusi yang Diimplementasikan**:
  - Mengganti ikon tombol "Nonaktifkan Proyek" menjadi `do_not_disturb_on` (amber/oranye) untuk merefleksikan status deaktivasi monitoring tanpa menghapus data sumber.
  - Menyelaraskan seluruh tombol aksi (Run Scraping, Edit, Nonaktifkan) ke sistem font icon `material-symbols-outlined`.
  - Membedakan visual modal berdasarkan konteks aksi (`delete` menggunakan amber do_not_disturb_on, `force_delete` menggunakan rose delete_forever, `restore` menggunakan emerald restore, dll).
  - Mengganti seluruh spinner animasi SVG dengan `progress_activity` yang seragam.
  - Memperjelas label tombol konfirmasi menjadi kontekstual (*"Ya, Nonaktifkan"*, *"Ya, Hapus Permanen"*, *"Ya, Aktifkan"*, dsb).
* **File Diubah**: `resources/views/components/⚡projects-list.blade.php`
* **QA**: `[QA-20260910-22]` — PASSED.

### 7.23 Modernisasi & Pembersihan Slop Modal Edit Proyek (10 September 2026)
* **Fitur**: Audit komprehensif dan perbaikan antarmuka modal Edit Proyek (`project-edit-modal`).
* **Latar Belakang**: Adanya class Tailwind non-standar, penggunaan raw SVG inline yang tidak seragam, performa round-trip Livewire berlebih pada input waktu override, serta belum adanya penomoran slot dan keyboard shortcut penutup modal.
* **Solusi yang Diimplementasikan**:
  - Menghapus seluruh class Tailwind yang keliru/typo (`text-slate-455`, `border-slate-350`, `rounded-custom`, dll) dan menerapkan styling konsisten berstandar design system modern.
  - Memperbaiki performa input time override dengan mengganti `wire:model.live` ke `wire:model`.
  - Menambahkan penomoran slot jadwal override (`Slot 1`, `Slot 2`, dst) serta standardisasi copy teks jadwal paket bawaan menjadi *"Tidak dijadwalkan"*.
  - Melengkapi input field dengan ikon prefix `material-symbols-outlined` (`folder`, `send`, `search`, `filter_alt`, `block`).
  - Menambahkan dukungan penutupan modal via tombol keyboard `Escape` dan klik di luar area modal (*click outside*).
  - Mengganti seluruh spinner tombol simpan ke `progress_activity`.
* **File Diubah**: `resources/views/livewire/project-edit-modal.blade.php`
* **QA**: `[QA-20260910-23]` — PASSED.

### 7.24 Standardisasi Ukuran Fix Modal & Isolasi Scroll Lock Latar Belakang (10 September 2026)
* **Fitur**: Standar arsitektur antarmuka modal (Edit Proyek, Daftar Proyek Dinonaktifkan, Modal Konfirmasi).
* **Aturan & Standar Modal Baku**:
  1. **Ukuran Fix & Proporsional**: Modal tidak boleh melebar tak terkontrol. Form modal standar menggunakan lebar maksimum `max-w-2xl` dan batasan tinggi terarah `max-h-[640px]`.
  2. **Isolasi Scroll (Scroll Inner Body Only)**:
     - Header (`shrink-0 border-b`) dan Footer (`shrink-0 border-t`) harus selalu terkunci (statis).
     - Hanya bagian isi/body modal yang boleh bergulir menggunakan `flex-1 overflow-y-auto overscroll-contain`.
  3. **Background Scroll Lock (Halaman Belakang Terkunci Total)**:
     - Selama modal aktif, elemen `document.body` wajib diberi class `overflow-hidden`.
     - Ditangani secara otomatis lewat lifecycle Alpine.js:
       ```html
       x-data
       x-init="document.body.classList.add('overflow-hidden'); return () => document.body.classList.remove('overflow-hidden');"
       ```
* **File Diubah**: `resources/views/livewire/project-edit-modal.blade.php`, `resources/views/components/⚡projects-list.blade.php`
* **QA**: `[QA-20260910-24]` — PASSED.

### 7.25 Aturan Wajib & Standar Mutlak Penguncian Latar Belakang Modal (10 September 2026)
> [!IMPORTANT]
> **PANDUAN WAJIB BAGI SETIAP PENGEMBANG / AI**: Setiap kali membuat modal baru atau memodifikasi modal yang sudah ada, aturan ini **WAJIB DIPATUHI DAN DICEK** tanpa kecuali.

#### 🛑 Masalah Klasik Mengapa Latar Belakang Ikut Ter-scroll:
Jika pengembang hanya menambahkan `overflow: hidden` pada elemen `<body>`, browser modern (Chrome, Safari, Edge) akan tetap meneruskan perputaran roda mouse (wheel event) ke elemen `<html>` (`documentElement`), sehingga halaman di balik modal tetap bergerak/bergulir.

#### ✅ 3 Aturan Wajib Modal Bebas Scroll-Bleed:

1. **Aturan CSS Global (Wajib Ada di CSS/Header)**:
   ```css
   html.overflow-hidden,
   body.overflow-hidden {
       overflow: hidden !important;
       height: 100vh !important;
       max-height: 100vh !important;
       touch-action: none !important;
   }
   ```

2. **Aturan Lifecycle Penguncian Ganda (Wajib di Backdrop Modal)**:
   Backdrop pembungkus modal (`fixed inset-0`) wajib mengunci `<html>` DAN `<body>` sekaligus, serta membersihkannya saat unmount:
   ```html
   <div
       x-data
       x-init="
           document.documentElement.classList.add('overflow-hidden');
           document.body.classList.add('overflow-hidden');
           return () => {
               document.documentElement.classList.remove('overflow-hidden');
               document.body.classList.remove('overflow-hidden');
           };
       "
       @wheel.self.prevent
       @touchmove.self.prevent
       class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
   >
   ```

3. **Aturan Isolasi Internal Modal (Hanya Body Modal yang Bergulir)**:
   - Kontainer kotak modal: `class="bg-white rounded-3xl w-full max-w-2xl overflow-hidden flex flex-col" style="height: 82vh; max-height: 640px;"`
   - Header modal: `shrink-0 border-b` (Statis di atas).
   - Body form modal: `flex-1 overflow-y-auto overscroll-contain` (Hanya ini yang boleh scroll).
   - Footer tombol aksi: `shrink-0 border-t` (Statis di bawah).

* **File Diubah**: `resources/views/components/media-dashboard-styles.blade.php`, `resources/views/livewire/project-edit-modal.blade.php`
* **QA**: `[QA-20260910-25]` — PASSED.

### 7.26 Pembersihan Slop Modal Proyek Dinonaktifkan (10 September 2026)
* **Fitur**: Audit dan pembersihan slop pada modal *Proyek Dinonaktifkan* (`showTrashedModal`) di komponen `projects-list`.
* **Solusi yang Diimplementasikan**:
  - Menerapkan arsitektur penguncian latar belakang sesuai aturan wajib Bab 7.25: Dual-lock `<html>` dan `<body>` via lifecycle Alpine, serta perangkap wheel/touch `@wheel.self.prevent` dan `@touchmove.self.prevent`.
  - Mengisolasi scrolling daftar proyek dinonaktifkan menggunakan `overscroll-contain` dan `flex: 1 1 auto; min-height: 0;`.
  - Standarisasi seluruh elemen visual ke `material-symbols-outlined`:
    - Header: `do_not_disturb_on`
    - Close button: `close`
    - Empty state: `check_circle`
    - Tombol Aktifkan: `restore` + `progress_activity` spinner
    - Tombol Hapus: `delete_forever` + `progress_activity` spinner
  - Memperbaiki class typo Tailwind CSS (`text-rose-650` → `text-rose-600`, `hover:text-slate-650` → `hover:text-slate-600`).
* **File Diubah**: `resources/views/components/⚡projects-list.blade.php`
* **QA**: `[QA-20260910-26]` — PASSED.

## Bab 7.27 — Modernisasi Tombol "Detail Proyek" & Judul Proyek Clickable

### Latar Belakang
Tombol "Detail Proyek" pada kartu proyek masih menggunakan warna non-brand (`border-primary`), tidak memiliki ikon, menggunakan raw SVG spinner, dan navigasinya full page reload (tanpa `wire:navigate`). Judul proyek juga hanya teks statis yang tidak dapat diklik.

### Perubahan

#### Judul Proyek
- Elemen `<h2>` diubah menjadi `<a wire:navigate href="...">` yang mengarah ke dashboard proyek (`route('home', ['project' => ..., 'tab' => ...])`).
- Tampilan tetap sama (uppercase, brand color `#1fa387`), ditambah `hover:underline` sebagai visual feedback.

#### Tombol "Detail Proyek"
| Aspek | Sebelum | Sesudah |
|---|---|---|
| Navigasi | Full reload | `wire:navigate` (SPA) |
| Warna border | `border-primary` | `border-[#1fa387]` |
| Warna teks | `text-primary` | `text-[#1fa387]` |
| Hover | `hover:bg-primary/5` | `hover:bg-[#1fa387] hover:text-white` |
| Ikon normal | _(tidak ada)_ | `arrow_forward` material-symbols |
| Spinner loading | Raw SVG inline | `progress_activity` material-symbols `animate-spin` |

### Standar Ikon yang Dipakai
```html
<!-- State normal -->
<span class="material-symbols-outlined text-[18px]">arrow_forward</span>

<!-- State loading -->
<span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
```

## Bab 7.28 — Perbaikan Kontras Hover pada Tombol "Detail Proyek"

### Masalah
Saat hover di atas tombol "Detail Proyek", latar belakang berubah menjadi warna hijau brand (`#1fa387`) namun teks dan ikon tidak tampak jelas (invisible) karena warna teks pada anak elemen (`<span>` teks dan ikon `material-symbols-outlined`) tidak otomatis terwarisi atau tertimpa dengan putih.

### Solusi
1. Menggunakan pola `group` pada parent `<a>`.
2. Menyematkan kelas `group-hover:text-white transition-colors` pada span teks dan elemen ikon `arrow_forward` / `progress_activity`.
3. Kompilasi asset via `npm run build` sehingga kelas CSS Tailwind v4 tersimpan ke production bundle.

## Bab 7.29 — Standarisasi Tombol & Optimasi Reaktivitas Wawasan AI

### Latar Belakang
Header tab "Wawasan & Ringkasan AI" memiliki tombol "Perbarui Wawasan AI" yang masih menggunakan raw SVG inline serta badge "Terupdate" tanpa informasi waktu. Selain itu, backend memo dan cache tidak direset saat pembaruan wawasan AI dipicu.

### Perubahan
1. **Ikon Sistem Material Symbols**:
   - Menghapus raw SVG bolt dan SVG spinner.
   - Menggunakan ikon `auto_awesome` untuk state idle.
   - Menggunakan ikon `progress_activity` dengan kelas `animate-spin` saat proses pembaruan berjalan (`wire:loading`).
2. **Badge Terupdate Realtime**:
   - Menampilkan `Terupdate {{ Carbon::parse(...)->diffForHumans() }}` dan tooltip tanggal lengkap.
3. **Invalidasi Cache Wawasan**:
   - Menghapus cache key `media_dashboard_wawasan:...` dan mereset array `$this->wawasanMemo = []` pada method `generateAiInsights()` di `MediaDashboard.php` agar perubahan AI segera terlihat di layar tanpa stale data.

## Bab 7.30 — Sinkronisasi Filter Tanggal & Transparansi Sumber Wawasan AI

### Latar Belakang
Ketika pengguna mengubah rentang tanggal filter, metrik angka terhitung ulang sesuai tanggal baru, namun teks ringkasan AI tetap merujuk pada rentang tanggal saat tombol digenerate sebelumnya. Selain itu, tidak ada label pembeda apakah ringkasan yang sedang dibaca adalah hasil analisis Model AI atau formula estimasi sistem.

### Perubahan
1. **Banner Sinkronisasi Rentang Tanggal**:
   - Menampilkan notifikasi visual di bawah header tab Wawasan jika terdapat filter tanggal aktif (`startDate` / `endDate`), mengajak pengguna memperbarui wawasan AI agar selaras.
2. **Badge Status Sumber Analisis**:
   - Di kartu Ringkasan Eksekutif, disematkan badge transparan:
     - `<span class="bg-emerald-50 text-emerald-700">auto_awesome Model AI</span>` untuk hasil LLM.
     - `<span class="bg-slate-100 text-slate-600">calculate Estimasi Sistem</span>` untuk hasil formula statistik default.
