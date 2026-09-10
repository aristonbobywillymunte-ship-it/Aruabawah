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
