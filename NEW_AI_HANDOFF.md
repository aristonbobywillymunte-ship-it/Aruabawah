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
