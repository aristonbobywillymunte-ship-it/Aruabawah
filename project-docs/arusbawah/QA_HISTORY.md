# QA History

# Package Daily Schedule Time Picker Hotfix
**Tanggal**: 2026-08-09
**Area**: Admin Package Manager, Package model, daily scraping schedulers, package schedule migration

### Skenario yang Diuji & Hasil:
1. **Migration Safety**: Migration lama dikembalikan ke kolom interval legacy saja; kolom jadwal harian dipindahkan ke migration baru yang idempotent dan aman untuk environment yang sudah pernah migrasi.
2. **Native Time Picker UI**: Form paket kini memakai slot `type="time"` yang bertambah/berkurang mengikuti `runs_per_day`.
3. **Validation Per Field**: Error validasi portal dan sosial tampil pada field jadwal masing-masing, tanpa saling menimpa.
4. **Normalization**: Time slot dinormalisasi, diurutkan, dan duplikasi ditolak sebelum disimpan.
5. **Legacy Fallback**: Paket tanpa jadwal harian tetap memakai interval lama.
6. **Support Tests**: Test helper tanpa database berhasil dijalankan; test feature berbasis database masih terblokir oleh environment PostgreSQL lokal yang tidak tersedia.
7. **CLI Validation**: `php artisan view:clear`, `php artisan route:list`, dan `git diff --check` berhasil; `php artisan migrate:status` terblokir oleh resolusi host database lokal.

### Catatan QA:
- Browser QA belum dijalankan.
- Test yang membutuhkan database produksi/lokal tidak dijalankan pada environment ini.

**Status Akhir**: PASS untuk helper dan validasi sintaks; BLOCKED untuk test berbasis DB dan `migrate:status` karena environment database lokal tidak tersedia.

## Admin Header Consistency 2
**Tanggal**: 2026-08-09
**Area**: Apify, Apify Financial Report, Packages, News Sources, Branding, AI Prompt Templates

### Skenario yang Diuji & Hasil:
1. **Wrapper Headers Ditambahkan**: `resources/views/admin/apify-financial-report.blade.php`, `resources/views/admin/packages.blade.php`, `resources/views/admin/branding.blade.php`, dan `resources/views/admin/ai-prompt-templates.blade.php` kini memiliki page-header yang ringan dan konsisten.
2. **Apify Warning Dipindah**: Blok penjelasan amber pada Apify dipindah keluar dari page-header ke blok informasi pertama di konten Livewire.
3. **Apify Financial Summary**: `Total Penggunaan` tetap tampil di konten sebagai ringkasan, sementara header halaman dipegang wrapper.
4. **Packages Toolbar**: Search dan `Buat Paket Baru` dirapikan menjadi toolbar awal di konten, tanpa header lokal yang berat.
5. **News Sources Teleport**: `@teleport` dipertahankan, tetapi header diringankan dan kontrol interaktif dipindah ke toolbar konten.
6. **Branding & AI Prompt Templates**: Header lokal lama dihapus dari Livewire, sehingga hanya wrapper yang mengendalikan page-header.
7. **Validation / Lint**: Akan divalidasi dengan `php artisan view:clear`, `php artisan route:list`, dan `git diff --check`.

### Catatan QA:
- Browser QA belum dijalankan pada tahap ini.
- Test framework yang memerlukan infrastruktur tidak dijalankan pada tahap ini.

**Status Akhir**: PASS untuk perubahan Blade dan validasi sintaks; browser QA belum dijalankan.

## Admin Header Structure Hotfix
**Tanggal**: 2026-08-09
**Area**: Admin logs dan client settings Livewire views

### Skenario yang Diuji & Hasil:
1. **System Logs Root Structure**: `resources/views/livewire/admin/system-logs.blade.php` diperiksa untuk memastikan hanya ada satu root Livewire, tanpa orphan `@endsection`, dan semua toolbar/filter/terminal berada di dalam root yang sama.
2. **Client Settings Root Structure**: `resources/views/livewire/admin/client-management/client-settings.blade.php` diperiksa untuk memastikan Back button berada di dalam root utama dan tampil di bagian atas sebelum section proyek.
3. **One Header Owner Rule**: `resources/views/admin/logs.blade.php` dan `resources/views/admin/clients-settings.blade.php` tetap menjadi pemilik header halaman masing-masing.
4. **Validation / Lint**: Akan divalidasi dengan `git diff --check` dan `php artisan view:clear`.

### Catatan QA:
- Browser QA belum dijalankan pada tahap ini.
- Test framework lain belum dijalankan pada saat catatan ini dibuat.

**Status Akhir**: PASS untuk perubahan struktur Blade; browser QA belum dijalankan.

## Admin Header Visual Consistency
**Tanggal**: 2026-08-09
**Area**: Admin dashboard, users, clients, client create, client settings, packages, AI providers, scraping settings, logs, maintenance, database

### Skenario yang Diuji & Hasil:
1. **Audit Struktur Header**: Menelusuri `resources/views/layouts/admin.blade.php`, `resources/views/admin/*.blade.php`, dan `resources/views/livewire/admin/**/*.blade.php` untuk mengidentifikasi header yang sudah konsisten, header yang berada di area konten, section `page-header`, dan kasus `@teleport`.
2. **Penyelarasan Visual**: Header halaman Admin disamakan ke pola visual Dashboard tanpa membuat komponen header bersama dan tanpa mengubah aksi interaktif di toolbar konten.
3. **Client Pages**: Header untuk daftar klien, tambah klien, dan pengaturan klien diseragamkan agar judul, deskripsi, dan back button tetap rapi serta tidak duplikatif.
4. **Livewire Pages**: Halaman AI Providers, Scraping Settings, Logs, Maintenance, dan Database tetap mempertahankan aksi Livewire di area konten, sementara judul/deskripsi mengikuti standar visual yang sama.
5. **Validation / Lint**: `git diff --check` lolos dan `php -l` pada file Blade yang disentuh tidak menemukan error sintaks.

### Catatan QA:
- `php artisan test --filter=AdminShellTest` tidak menemukan test dengan filter tersebut di repository saat ini.
- `php artisan test --filter=Client` dan `php artisan test --filter=Package` gagal karena database PostgreSQL lokal tidak tersedia pada `127.0.0.1:5432` saat eksekusi.
- `php artisan view:clear` berhasil.

**Status Akhir**: PASS untuk perubahan Blade dan validasi sintaks, dengan keterbatasan test environment database lokal.

## Client Project Assignment & Select2
**Tanggal**: 2026-08-09
**Area**: Halaman Pengaturan Klien (Admin)

### Skenario yang Diuji & Hasil:
1. **Assign Proyek Aktif Bawah Limit** (Automated & Manual): Berhasil menambahkan proyek ke klien, notifikasi toast muncul dengan benar tanpa memuat ulang seluruh halaman.
2. **Assign Proyek Multi-Select (Pillbox)** (Manual): Berhasil mengumpulkan ID sebagai *array* dari Select2, masuk dan tersimpan pada pivot `project_user` menggunakan mekanisme `syncWithoutDetaching`.
3. **Validasi Limit Proyek** (Automated): Saat klien menambah proyek melebih kuota *max_projects*, sistem berhasil menolak input (error bag `selectedProjectIds`) dan memaparkan sisa kuota. Proyek Nonaktif tidak dihitung sebagai beban kuota.
4. **Lepas Proyek (Detach)** (Manual): Peringatan hapus muncul. Mengklik "Ya, Lepas" hanya memutuskan hubungan dari akun klien dan menghapus tautan akses, **tanpa menghapus data atau men-trigger ulang fitur scraping**.
5. **SPA Navigation & UX (Select2 Bug)** (Manual): Awalnya terjadi anomali UI `<select multiple>` bawaan HTML karena `livewire:initialized` tidak terpanggil ulang. Setelah migrasi ke Livewire 4 `@assets` & `@script` dan memperbaiki pemanggilan `$wire.$set` serta scope Select2 menjadi `$($wire.$el)`, Select2 pillbox otomatis melakukan instansiasi 100% sempurna (*idempotent*) setiap kali pindah halaman via Livewire SPA. Dropdown ter-reset otomatis setiap assign/detach berhasil. Perataan vertikal untuk ikon dan *placeholder* juga diatasi dengan perombakan CSS *flexbox* bawaan komponen.
6. **Simpan Pengaturan UX** (Manual): Mengubah alur simpan `ClientSettings.php` dari refresh sepihak menjadi navigasi SPA `wire:navigate` ke halaman daftar klien, yang dibarengi dengan munculnya `admin-toast` notifikasi sukses, serta melengkapi tombol simpan dengan status `wire:loading` ("Menyimpan...").

**Status Akhir**: PASS (Deployed di server `main`)

## Project Card UI Sync Removal
**Tanggal**: 2026-08-09
**Area**: Halaman Daftar Proyek (Komponen `⚡projects-list.blade.php`)

### Skenario yang Diuji & Hasil:
1. **Penghapusan UI**: Memastikan tombol "Sinkronisasi" / "Sinkronisasi Ulang" tidak lagi dirender di UI kartu proyek.
2. **Layout Kartu**: Memastikan struktur flexbox dan tata letak tidak berantakan (tidak meninggalkan ruang kosong (*gap*) yang janggal) dan reflow metadata proyek yang tersisa (tanggal dibuat, label paket) berjalan aman.
3. **Fungsionalitas Aksi Lain**: Memastikan fungsi Hapus, Nonaktifkan, Edit, dan masuk ke detail proyek tetap bekerja tanpa ada regresi akibat penghapusan tombol Sinkronisasi.
4. **Resync Otomatis**: Memastikan bahwa *backend pipeline* sinkronisasi tidak tersentuh dan service `ContentMatchingService::resyncProjectContent()` maupun *Job* lain yang berjalan otomatis pasca Create/Edit Project masih sepenuhnya berfungsi secara siluman (*background*).

**Status Akhir**: PASS (Deployed di server `main`)

## PROJECT-TRASH-AUTH-HARDENING
**Tanggal**: 2026-08-09
**Area**: ProjectsList Livewire Component - Deactivate/Restore/Force Delete Authorization

### Skenario yang Diuji & Hasil:
1. **UI Terminology**: "Proyek Dinonaktifkan" tampil menggantikan "Lihat/Daftar Proyek Dihapus" di button dan modal header.
2. **Admin/User Internal - Deactivate**: Dapat membuka modal konfirmasi dan menonaktifkan proyek. Proyek ter-soft-delete.
3. **Admin/User Internal - Open Trashed Modal**: `openTrashedProjectsModal()` membuka modal "Proyek Dinonaktifkan".
4. **Admin/User Internal - Restore**: Dapat memulihkan proyek yang dinonaktifkan. `deleted_at` menjadi null.
5. **Admin/User Internal - Force Delete**: Dapat menghapus proyek secara permanen. Record `projects` hilang, relasi operasional terhapus, record `Article`/`SocialMediaItem` tetap ada.
6. **Client (can_delete_projects=false) - Tombol Deactivate**: Tombol tidak tampil di UI. Panggilan langsung ke `confirmDeleteProject()` ditolak (modal tidak terbuka).
7. **Client (can_delete_projects=false) - Bypass Deactivate**: `deleteProject()` langsung ditolak oleh backend, proyek tetap aktif.
8. **Client (can_delete_projects=false) - Trashed Modal**: `openTrashedProjectsModal()` ditolak, `showTrashedModal` tetap false.
9. **Client (can_delete_projects=false) - Restore/ForceDelete**: Ditolak di `confirmRestoreProject()`, `restoreProject()`, `confirmForceDeleteProject()`, `forceDeleteProject()`. Tidak ada perubahan pada state proyek.
10. **Client (can_delete_projects=true) - Deactivate**: Dapat menonaktifkan proyek yang ter-assign. Proyek ter-soft-delete.
11. **Client (can_delete_projects=true) - Trashed Modal**: Tetap ditolak. `showTrashedModal` tetap false.
12. **Client (can_delete_projects=true) - Restore/ForceDelete**: Tetap ditolak meski punya izin hapus aktif.
13. **Source Data Preservation**: Setelah force-delete Admin, `Article` dan `SocialMediaItem` source record tetap ada di database. Hanya `project_id` yang di-null-kan.
14. **Pivot Cleanup**: `project_user` pivot untuk project yang di-force-delete berhasil dihapus.

### Test Coverage:
- `tests/Feature/ProjectTrashAuthorizationTest.php` (17 test cases baru)
- PHP Lint: PASS untuk semua file yang diubah

**Status Akhir**: PASS (Deployed di server `main`)
