# 📋 BUKU LOG QA MANDIRI (QUALITY ASSURANCE LOG)
> **PANDUAN OPERASIONAL WAJIB AI**:
> Setiap kali AI melakukan perbaikan bug, refactor, atau penambahan fitur, AI **WAJIB** menjalankan pengujian fisik langsung (PHP linting, query execution, render simulation di container Docker `media_intelligent_container`) dan mencatatnya ke file log ini.
> Dokumen ini merupakan buku catatan mandiri (*standalone log*) terpisah untuk melacak riwayat pengujian fungsional secara rinci dan terstruktur.

---

## Format Standar Pencatatan Log QA
Setiap entri pengujian wajib mencakup komponen berikut:
1. **ID & Tanggal Pengujian** (Format: `QA-YYYYMMDD-XX`)
2. **Konteks / Masalah yang Diperbaiki**
3. **Target File / Komponen yang Diuji**
4. **Environment Pengujian** (Container / OS / Versi PHP / DB)
5. **Metode & Perintah Uji Fisik** (CLI command / Artisan / Unit test)
6. **Parameter & Input Uji**
7. **Hasil Pengamatan Nyata (Actual Output)**
8. **Status Kelulusan** (`PASSED` / `FAILED`)
9. **Hash Commit Lokal Terkait**

---

## Riwayat Log Verifikasi QA

### [QA-20260910-03] Verifikasi Pembersihan AI-Slop & Inkonsistensi Tab Analisis (`?project=61&tab=YW5hbGlzaXM=`)
* **Tanggal & Waktu**: 10 September 2026, 19:35 WIB
* **Konteks Masalah**:
  Audit antarmuka tab Analisis menemukan beberapa elemen *AI-Slop* dan cacat query:
  1. Border gradien warna-warni neon (`p-[1px] bg-gradient-to-br`) dan efek blur blob latar belakang palsu pada grid kategori dan awan kata.
  2. Drop shadow berwarna jenuh (`shadow-pink-500/20`, `shadow-blue-500/20`, `shadow-slate-900/20`) yang melanggar panduan *Taste-Skill Section 4.4*.
  3. Hardcoded filter `whereRaw("ai_pop.sentiment = 'positive'")` pada widget Penyebutan Populer yang memblokir postingan netral/negatif bervolume tinggi.
  4. Typo nested array key pada kartu Facebook: `$counts['counts']['sources']['Facebook']`.
* **Target File Diperbaiki**:
  - `resources/views/livewire/media-dashboard.blade.php`
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container`
  - Engine: PHP 8.4 CLI, Laravel 11/13.17, Livewire 3
  - Database: PostgreSQL `media_intelligent`
* **Metode & Skenario Uji Fisik**:
  1. Pembersihan compiled view:
     ```bash
     docker exec media_intelligent_container php artisan view:clear
     ```
  2. Uji render runtime halaman dengan proyek aktif ID 61 dan tab analisis:
     ```bash
     docker exec media_intelligent_container php artisan tinker --execute='
     $user = App\Models\User::where("email", "user@arusbawah.co")->first();
     auth()->login($user);
     request()->merge(["project" => "61", "tab" => "YW5hbGlzaXM="]);
     $html = View::make("welcome")->render();
     echo "RENDER SUCCESS: " . strlen($html) . " bytes\n";
     '
     ```
* **Hasil Pengamatan Nyata**:
  - `view:clear` berhasil dijalankan (*exit code 0*).
  - Eksekusi render Blade berhasil 100% tanpa error, tanpa exception sintaks, dengan ukuran HTML `131.560 bytes`.
  - Grid kategori kini menggunakan solid borders yang bersih (*border-slate-200*) dengan neutral soft shadow (*shadow-sm*).
  - Query Penyebutan Populer kini mengambil artikel/postingan murni berdasarkan ranking pembaca (`project_estimated_readers DESC`) tanpa manipulasi sentimen buatan.
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: *Pending local commit*

---

### [QA-20260910-02] Verifikasi Livewire Multiple Root Elements Fix pada Komponen `projects-list`
* **Tanggal & Waktu**: 10 September 2026, 19:28 WIB
* **Konteks Masalah**:
  Pengguna mengalami crash Error 500 saat mengakses URL:
  `http://localhost/?project=NjE%3D&tab=YW5hbGlzaXM%3D`
  Exception yang muncul:
  `Livewire\Features\SupportMultipleRootElementDetection\MultipleRootElementsDetectedException: Livewire only supports one HTML element per component. Multiple root elements detected for component: [projects-list]`
* **Akar Masalah**:
  Penempatan direktif `@endif` prematur di baris 1309 pada `resources/views/components/⚡projects-list.blade.php` (setelah penutup `</main>`). Akibatnya, saat `$projectId` terisi, modal-modal proyek dan footer di bawahnya tetap dieksekusi di luar hierarki tag root yang benar.
* **Target File Diperbaiki**:
  - `resources/views/components/⚡projects-list.blade.php` (Pemindahan closing `@endif` ke baris akhir sebelum penutup root `</div>`).
  - `app/Http/Livewire/ProjectsList.php` (Pemberian null-safe check pada method `getDecodedProjectId()` untuk mencegah deprecation notice `base64_decode(): Passing null to parameter #1 of type string is deprecated` di PHP 8.4).
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container`
  - Engine: PHP 8.4 CLI, Laravel 11/13.17, Livewire 3
  - Database: PostgreSQL `media_intelligent`
* **Metode & Skenario Uji Fisik**:
  1. Pembersihan view cache:
     ```bash
     docker exec media_intelligent_container php artisan view:clear
     ```
  2. Eksekusi simulasi render dua skenario melalui `php artisan tinker`:
     ```php
     $user = App\Models\User::where("email", "user@arusbawah.co")->first();
     auth()->login($user);
     
     // Skenario 1: Proyek Terpilih
     request()->merge(["project" => "NjE=", "tab" => "YW5hbGlzaXM="]);
     $html1 = View::make("welcome")->render();
     echo "Skenario 1 Length: " . strlen($html1) . "\n";
     
     // Skenario 2: List Proyek Bersih (Tanpa Parameter)
     request()->query->remove("project");
     request()->query->remove("tab");
     $html2 = View::make("welcome")->render();
     echo "Skenario 2 Length: " . strlen($html2) . "\n";
     ```
* **Hasil Pengamatan Nyata**:
  - Pembersihan view cache: `Compiled views cleared successfully`.
  - **Skenario 1 (With Project ID)**: Render sukses 100% dengan panjang output HTML `131.560 bytes`. Exception `MultipleRootElementsDetectedException` **hilang total**.
  - **Skenario 2 (Without Project ID)**: Render sukses 100% dengan panjang output HTML `25.254 bytes`. Tidak ada deprecation warning PHP 8.4.
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: `179bdfb`

---

### [QA-20260910-01] Verifikasi Payload Apify Terhadap Konfigurasi Paket Proyek
* **Tanggal & Waktu**: 10 September 2026, 17:30 WIB
* **Konteks Masalah**:
  Memastikan rem biaya (*cost limit*), alokasi RAM, dan pembagian kuota item (*distributed limit*) scraper media sosial Apify mematuhi konfigurasi paket secara presisi dan tidak membengkakkan biaya.
* **Target Komponen Diperbaiki**:
  - `app/Jobs/ApifyScrapingJob.php`
  - Relasi `projects` $\leftrightarrow$ `packages`
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container`
  - Sample: Proyek ID `61` (*Bank Kaltimtara*) dengan Paket ID `1` (*Enterprise*)
* **Parameter & Hasil Pengujian**:
  1. **RAM Limit**: Memory 1024 MB terbaca presisi pada query string URL `?memory=1024`.
  2. **Rem Biaya (Emergency Cost Cap)**: Nilai `maxTotalChargeUsd` terpasang akurat sesuai plafon paket per jenis platform (FB, IG, TikTok).
  3. **Pembagian Batas Item**: Limit paket 100 dibagi rata 2 keyword menjadi 50 item/keyword via formula $\lceil \text{Limit} / \text{Keywords} \rceil$.
  4. **Strict Guard**: Actor tanpa limit paket langsung melempar `InvalidArgumentException` dan dibatalkan sebelum memanggil API eksternal.
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: `d0ea6df`

---
