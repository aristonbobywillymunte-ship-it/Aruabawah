# QA History

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

