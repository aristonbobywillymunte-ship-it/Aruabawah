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

### 5.3 Larangan Keras & Batasan Kerja (Non-Negotiable)
1. **Workspace Terkunci**: Wajib bekerja hanya di `/Users/unity/Documents/proyek baru/` (bukan folder lain atau remote server).
2. **Dilarang Mengubah Skema Database**: Dilarang menjalankan migrasi yang merusak skema tanpa persetujuan eksplisit user.
3. **Verifikasi Fisik Sebelum Menjawab**: Dilarang menyimpulkan file/fitur ada atau tidak ada tanpa verifikasi langsung menggunakan `view_file` atau `grep_search`.
4. **Wajib Memperbarui Catatan Progres**: Setiap selesai melakukan task, AI **wajib** mencatat ringkasan perubahan di Bagian 6 dokumen ini agar AI berikutnya langsung tersinkronisasi.

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



