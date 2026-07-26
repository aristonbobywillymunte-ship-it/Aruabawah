# 📖 Panduan Serah Terima (Handoff) Proyek untuk AI Baru

Dokumen ini ditujukan bagi AI coding assistant baru untuk memahami arsitektur, modul inti, struktur data, dan penyesuaian terbaru pada proyek **Arusbawah Media Intelligence**.

---

## 🚀 1. Ringkasan Proyek & Tech Stack
*   **Fungsi Utama**: Media Monitoring & Intelligence (Berita online & Media Sosial Facebook/Instagram/TikTok).
*   **Framework**: Laravel 10/11 (PHP 8.4) & Livewire.
*   **Database**: PostgreSQL & Redis (Antrean Antarmuka/Queue).
*   **Container Docker**: Service `media-intelligent` (Workspace: `/var/web/`).

---

## 📱 2. Desain Tampilan Mobile (Responsif)
Semua penyesuaian UI terbaru dipusatkan agar ramah perangkat HP/Mobile pada komponen dashboard pengguna:
*   **File Utama**: [⚡media-dashboard.blade.php](file:///Users/unity/Documents/proyek%20baru/resources/views/components/⚡media-dashboard.blade.php).
*   **Pemisahan Filter Panel**:
    *   **Desktop (`hidden lg:block`)**: Menggunakan panel filter permanen di sebelah kanan dengan orientasi `lg:sticky lg:top-24` agar tetap diam saat di-scroll.
    *   **HP/Mobile (`lg:hidden`)**: Panel disembunyikan dan diubah menjadi *slide-out drawer* (laci meluncur dari kanan) yang diaktifkan melalui tombol melayang bundar hijau (*Floating Action Button*) di kanan bawah.
    *   **Modularitas**: Konten filter disatukan dalam template [⚡filter-items.blade.php](file:///Users/unity/Documents/proyek%20baru/resources/views/components/⚡filter-items.blade.php) dan dimasukkan menggunakan `@include`.
*   **Tab Menu Mobile**: Tab navigasi utama di HP otomatis dapat digeser secara horizontal (*scrollable*).
*   **Responsivitas Kartu Berita**:
    *   Padding menyusut dari `p-6` menjadi `p-4 sm:p-6`.
    *   Metrik grid menggunakan pembatas dinamis dan jumlah kolom responsif (`grid-cols-2 sm:grid-cols-3 lg:grid-cols-5`).
    *   Panjang nama Kategori dibatasi menggunakan `Str::limit($article->category, 30)` dan dilarang memakai `whitespace-nowrap` agar kartu tidak melebar keluar layar HP.
    *   Tombol "Kembali ke Atas" dipindah ke pojok kiri bawah khusus di HP (`left-6 md:left-auto md:right-6`) agar tidak bertabrakan dengan tombol filter.

---

## ✨ 2B. Form Project: Normalisasi Keyword & Toast
*   **File Utama**: [⚡projects-list.blade.php](file:///Users/unity/Documents/proyek%20baru/resources/views/components/⚡projects-list.blade.php).
*   **Normalisasi Wajib**:
    *   Keyword project sekarang selalu dinormalisasi ke bentuk hashtag saat disimpan.
    *   Apostrophe variasi (`'`, `’`, `‘`, `` ` ``) dihapus sebelum pembentukan hashtag.
    *   Contoh input `Rudy Mas'ud` akan diproses menjadi `#rudymasud`.
*   **Preview UI**:
    *   Form create dan edit menampilkan preview hashtag live dari keyword yang diinput.
    *   Checkbox normalisasi dihapus karena perilaku ini sekarang wajib.
*   **Toast Aksi**:
    *   Setiap create project dan update project wajib memicu `action toast`.
    *   Toast dipakai sebagai feedback utama selain modal sukses.
*   **Edit Form**:
    *   Modal edit mengikuti aturan normalisasi yang sama dengan form create.
    *   Keyword hasil edit tetap disimpan dalam bentuk yang sudah dinormalisasi.

---

## 📈 2C. Grafik Tren Kata Kunci
*   **File Utama**: [⚡media-dashboard.blade.php](file:///Users/unity/Documents/proyek%20baru/resources/views/components/⚡media-dashboard.blade.php).
*   **Pencarian Keyword Grafik**:
    *   Grafik tren keyword wajib memakai pencarian case-insensitive di PostgreSQL.
    *   Keyword yang dimulai dari hashtag harus dinormalisasi dulu sebelum dipakai sebagai filter grafik atau tabel kata kunci.
    *   Pencarian grafik membaca bentuk keyword tanpa `#` dan juga varian hashtag agar data yang sudah ada tetap terdeteksi.
*   **Catatan Audit**:
    *   Jika grafik terlihat kosong padahal data tersedia, cek dulu query keyword, normalisasi hashtag, dan case-sensitivity PostgreSQL sebelum mengubah logika chart.
    *   Audit project `Wagub Kaltim` (`project_id=3`) menunjukkan data memang ada: `project_articles` berisi 433 relasi, 121 artikel berada di rentang `2026-07-01` s/d `2026-07-18`, dan keyword `wagub kaltim` masih punya 55 kecocokan.
    *   Jika chart tetap kosong pada kasus seperti ini, sumber masalah paling mungkin ada di jalur render/filter aktif/cache, bukan di ketersediaan data mentah.
    *   **Cache Keyword Table**: Tabel kata kunci memakai cache key yang harus dibersihkan sebelum `primaryKeywords` diubah. Jangan hanya `forget()` key baru setelah array keyword sudah terlanjur dimodifikasi.
    *   **Tab Kata Kunci Blank**: Saat tab `katakunci` dibuka langsung via URL, `dashboardLoaded` sekarang dipaksa aktif di `mount()` supaya area utama tidak tetap kosong menunggu `wire:init`. Jika halaman ini terlihat kosong lagi, cek dulu gate render `@if($dashboardLoaded)` dan pastikan `loadDashboard()`/hydrasi Livewire benar-benar jalan.
    *   **Dashboard Loaded Default**: `dashboardLoaded` kini di-set `true` sejak `mount()` agar shell workspace tidak hilang pada render awal. Ini menutup celah ketika Livewire/Alpine gagal memicu `wire:init` tepat waktu.
    *   **Keyword Tab Request Flag**: Tab `katakunci` sekarang memakai flag request mentah (`keywordTabRequested`) supaya render awal tidak bergantung penuh pada hidrasi `activeTab` Livewire. Ini dipakai untuk membedakan tab keyword saat halaman dibuka langsung dari URL.
    *   **Gate Render Kata Kunci**: Workspace `Kata Kunci` sekarang juga dibolehkan tampil saat `isTab('katakunci')` walau `dashboardLoaded` belum sempat ter-set. Ini menjaga konten tetap muncul jika hidrasi Livewire terlambat atau `wire:init` gagal memicu awal.
    *   **Layout Clipping Kata Kunci**: Jika area tab `katakunci` tampak seperti tertutup / kosong walau panel filter hidup, cek wrapper `section` dan container scroll internal di `media-dashboard.blade.php`. Pembungkus dengan `overflow-hidden` dan tinggi tetap bisa men-clipping tabel serta grafik sebelum sempat terlihat.
    *   **Wire Key Tab**: Tab `katakunci` sekarang memakai `wire:key="dashboard-keyword-section"` supaya Livewire tidak salah morphing saat pindah tab. Jika area kosong muncul lagi, cek dulu apakah tab section ini masih punya key unik dan apakah tab lain memakai pola yang sama.
    *   **Scroll Shell Kata Kunci**: Tab `katakunci` memakai shell `flex-1 min-h-0 overflow-y-auto pb-24` agar konten tetap scrollable dan tidak tertutup footer/panel fixed. Jika layout tampak kosong lagi, cek apakah shell ini berubah atau hilang saat refactor.
    *   **Loading Shell Dihapus**: Wrapper workspace utama di `media-dashboard.blade.php` tidak lagi memakai `wire:init="loadDashboard"` karena pemicu itu sempat membuat skeleton/loading menempel di halaman Kata Kunci. Render kini mengandalkan state mount Livewire yang sudah dipaksa aktif untuk tab ini.
    *   **Chain Blade Dipulihkan**: Branch `katakunci` dikembalikan sebagai `@elseif($this->isTab('katakunci'))` agar tidak memecah chain tab utama dan tidak memunculkan skeleton branch tab lain secara tidak sengaja. Jika loading muncul lagi, cek dulu keseimbangan `@if/@elseif/@endif` di `media-dashboard.blade.php` sebelum menyentuh query/data.
    *   **Parse Error Blade**: Jika muncul `unexpected token "endif"`, cek penutup `@endif` di area setelah mobile tabs dan di akhir workspace utama. Beberapa edit sebelumnya sempat meninggalkan atau menghapus penutup yang salah sehingga compiled Livewire view gagal dirender.
    *   **Fallback Loading Dihapus**: Skeleton fallback `@else` di akhir workspace utama sudah dihapus agar tab `katakunci` tidak lagi menampilkan blok loading statis yang bisa memicu parse/branch mismatch saat Livewire mengompilasi view.
    *   **Tail `@endif` Harus Pas**: Penutup `@endif` terakhir di `media-dashboard.blade.php` harus tetap menutup `@if($dashboardLoaded)`; jangan menambah atau menghapus penutup itu tanpa mengecek pasangan branch tab utama dan modal yang mengikuti di bawahnya.
    *   **Tail Double Endif Dihapus**: Jika compiled Livewire masih mengeluh `unexpected token "endif"`, cek tail `media-dashboard.blade.php` dan pastikan tidak ada dua `@endif` berdiri sendiri setelah modal detail. Dua penutup terakhir sempat tersisa dari refactor sebelumnya dan memicu EOF parse error.

---

## 🔗 3. Sistem Decode URL Google News
Setiap berita dari Google News RSS memiliki tautan terenkripsi yang harus diterjemahkan ke URL asli portal berita sebelum di-scrape:
*   **Service PHP**: [`GoogleNewsUrlDecoderService.php`](file:///Users/unity/Documents/proyek%20baru/app/Services/News/GoogleNewsUrlDecoderService.php).
*   **Script Python**: [`decode_google_news_url.py`](file:///Users/unity/Documents/proyek%20baru/scripts/google-news/decode_google_news_url.py) (menggunakan package `googlenewsdecoder==0.1.7` di `/opt/google-news-venv/bin/python`).
*   **Aturan Failover (Cadangan)**: Jika Python Decoder gagal (misal karena token kedaluwarsa atau IP diblokir), PHP otomatis menjalankan lapis cadangan:
    1.  Mengecek Header Redirect HTTP (**301/302**).
    2.  Mencari tag HTML `<meta http-equiv="refresh" url="...">`.
    3.  Mengekstraksi link portal keluar (`<a>`) non-Google di dalam body HTML halaman transit Google.

---

## 📊 4. Konfigurasi Batas Scraping Apify (Sosial Media)
Integrasi Apify diatur agar seimbang antara kebutuhan data dan efisiensi biaya:
*   **Batas Maksimal**: 50 item per proyek sekali jalan (Facebook, Instagram, TikTok).
*   **Distribusi Keyword**: Jika satu proyek memiliki $N$ kata kunci, batas per kata kunci diatur dinamis: `ceil(50 / N)` untuk menghindari konsumsi kuota berlebih.
*   **Rem Biaya Darurat**: Mengirim parameter `maxTotalChargeUsd` (menggunakan nilai `maximum_cost_per_run_usd`) ke Apify.
*   **Penanganan Selesai Sebagian**: Jika kuota biaya habis atau waktu tunggu habis (15 menit) tetapi dataset sudah terisi sebagian, data tetap diambil, dibersihkan, disimpan ke DB, dan diproses normal dengan status *sukses sebagian*.

## 🎛️ 4B. Konsistensi Nama Source Filter
*   **Aturan Penting**: Nilai `wire:model` untuk source harus sama persis dengan state default Livewire.
*   **Daftar Kanonik**: Gunakan `Instagram`, `TikTok`, `Facebook`, dan `News` sebagai daftar source filter utama di seluruh UI yang menampilkan checkbox filter.
*   **Kasus TikTok**: Gunakan `TikTok` secara konsisten di semua panel, karena `Tiktok` akan dianggap berbeda dan checkbox tidak ikut tercentang saat default state diisi `TikTok`.
*   **Dampak**: Mismatch casing bisa membuat checkbox tampak tidak aktif walaupun source sebenarnya sudah ada di array default.

## 🔗 4C. Top Isu Negatif
*   **Link Sumber**: Item pada kartu `Top Isu Negatif` kini dapat dibuka ke sumber artikel pertama yang terasosiasi dengan isu tersebut.
*   **Fallback**: Jika isu tidak punya URL yang valid, judul tetap tampil sebagai teks biasa agar layout tidak rusak.

---

## 🛠️ 5. Perintah Penting (Command Cheat Sheet)
Jalankan perintah ini di dalam direktori proyek utama di host machine (macOS Anda):

*   **Kompilasi Frontend Aset (Tailwind/Vite)**:
    ```bash
    npm run build
    ```
*   **Membersihkan Cache View Laravel (Wajib setelah update Blade)**:
    ```bash
    docker compose exec media-intelligent php artisan view:clear
    ```
*   **Menjalankan Pekerjaan Antrean (Queue Worker)**:
    ```bash
    docker compose exec media-intelligent php artisan queue:work
    ```
*   **Simulasi Uji Coba Scraping Portal Berita**:
    ```bash
    docker compose exec media-intelligent php artisan scraping:run-news --project-id=2 --discovery-mode=google_news --limit=3
    ```
*   **Menjalankan Unit Test (Testing Environment)**:
    ```bash
    docker compose exec media-intelligent php artisan test tests/Unit/GoogleNewsUrlDecoderServiceTest.php
    ```

---

*Catatan: Baca file pendukung [AI_CONTEXT.md](file:///Users/unity/Documents/proyek%20baru/AI_CONTEXT.md) untuk detail arsitektur scheduler dan aturan antrean.*


## 2026-07-26 08:15 WITA
- Menemukan dan menghapus `@if($dashboardLoaded)` ganda di `resources/views/components/⚡media-dashboard.blade.php` yang menggeser balance Blade pada workspace dashboard.
- Langkah ini ditujukan untuk menghilangkan parse error `unexpected token "endif"` pada compiled Livewire view dan memulihkan render halaman Kata Kunci tanpa mengubah query/data.
- Setelah perubahan, cache view Laravel dibersihkan lagi agar compiled Livewire view diregenerasi dari source terbaru.


## 2026-07-26 08:22 WITA
- Source Blade `resources/views/components/⚡media-dashboard.blade.php` sudah diselaraskan kembali dan balance directive telah diverifikasi `0`.
- Cache Livewire compiled (`storage/framework/views/livewire/views/3f87d10b.blade.php`, `classes/3f87d10b.php`, `styles/3f87d10b.css`) dihapus manual karena masih memuat tail `@endif` lama walaupun source sudah benar.
- View cache Laravel dibersihkan ulang agar request berikutnya meregenerasi compiled view dari source terbaru.


## 2026-07-26 09:00 WITA
- Menormalkan shell layout halaman Penyebutan/Kata Kunci dengan mengubah workspace desktop menjadi `overflow-hidden` dan memperbesar ruang scroll panel konten dari `calc(100vh - 250px)` ke `calc(100vh - 320px)`.
- Tujuannya agar kartu konten terakhir tidak tertutup footer fixed dan area utama tetap bisa discroll normal di desktop, tanpa mengubah query/data.


## 2026-07-26 09:08 WITA
- Menutup kembali `@if($dashboardLoaded)` yang sempat belum tertutup di `resources/views/components/⚡media-dashboard.blade.php`, sehingga workspace desktop bisa render tanpa parse error dan tanpa mendorong konten ke bawah footer fixed.
- Cache view Laravel dibersihkan ulang setelah koreksi layout dan directive Blade.


## 2026-07-26 09:18 WITA
- Mengubah loading state menjadi blok terpisah `@if(!$dashboardLoaded)` agar tidak bergantung pada `@else` dari blok utama dan mengurangi risiko parse error Blade.
- Cache view Laravel dibersihkan ulang setelah perubahan.


## 2026-07-26 09:56 WITA
- Memperbaiki bug scope variabel pada `app/Console/Commands/QueueUnscoredAiContent.php` dengan menambahkan `SchedulerQueueGuard` ke closure `chunkById()`.
- Perbaikan ini menghilangkan error `Undefined variable $schedulerQueueGuard` saat command `ai:queue-unscored-content` berjalan.


## 2026-07-26 10:12 WITA
- Menambahkan fallback dan self-healing pada `AiPromptTemplate` agar template aktif untuk `source_type=article` dan `source_type=social` tetap bisa dipakai walau penanda default sempat hilang.
- `AiAnalysisJob`, `AiAnalysisDispatchStateService`, dan `TestArticleAiAnalysis` kini tidak lagi berhenti di `missing_configuration` hanya karena default template hilang, selama masih ada template aktif yang valid.
- Livewire admin template sekarang ikut memulihkan default untuk article/social saat render dan setelah perubahan data, supaya sistem analisis tetap otomatis dan tidak mudah rusak lagi.


## 2026-07-26 10:33 WITA
- Mengizinkan dispatch state `failed` dengan `last_error_code=missing_configuration` untuk dipromosikan kembali ke `queued` jika konfigurasi template/provider sudah pulih.
- 27 artikel lama yang sebelumnya gagal karena `missing_configuration` berhasil direqueue ulang dengan template artikel aktif `Analisis Portal`.
- Setelah requeue, status di database menunjukkan `queued=25`, `processing=0`, `retry_wait=0`, dan `failed_missing_configuration=0`, sehingga antrean AI kembali hidup untuk kasus yang memang bisa diselamatkan.
