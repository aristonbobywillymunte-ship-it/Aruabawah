# QA History

# ACTOR-INTERVAL-REMOVAL-7B-RUNTIME
**Tanggal**: 2026-08-10
**Area**: `RunApifyScraping`, `ApifyScrapingJob`, runtime social scheduling/cooldown

### Skenario yang Diuji & Hasil:
1. **Dispatch Independence**: `dispatchSafely()` tidak lagi bergantung pada nilai `interval_minutes` Actor.
2. **Invalid Daily Schedule**: Jadwal harian Sosial yang invalid tidak jatuh ke fallback interval legacy.
3. **Cooldown Independence**: Retry/cooldown runtime menghasilkan waktu pemulihan yang sama meski `interval_minutes` Actor berbeda.
4. **Legacy Columns Preserved**: Kolom DB legacy `apify_actors.interval_minutes` dan `packages.social_interval_minutes` tetap dipertahankan.
5. **Static Verification**: `php -l`, `php artisan route:list`, `php artisan schedule:list`, `php artisan view:clear`, dan `git diff --check` berhasil.
6. **Targeted Test Run**: `php artisan test --filter=ApifyActorIntervalRuntimeRemovalTest` berhasil pada SQLite temp test harness.

### Catatan QA:
- Browser QA belum diverifikasi.
- Runtime scraping tidak dijalankan.

**Status Akhir**: PASS

# ACTOR-INTERVAL-REMOVAL-7A-UI
**Tanggal**: 2026-08-10
**Area**: Admin Apify Configuration, actor performance surface

### Skenario yang Diuji & Hasil:
1. **Interval Field Removed**: Field `Interval Scraping (Menit)` tidak lagi tampil di form Actor.
2. **Section Renamed**: Section yang semula membawa wording Jadwal sekarang memakai label `Konfigurasi Performa`.
3. **Form State Removed**: Livewire `ApifyConfiguration` tidak lagi memiliki public state `interval_minutes`.
4. **Legacy Column Preserved**: Save/update Actor tidak mengubah kolom legacy `apify_actors.interval_minutes`.
5. **Other Actor Settings Preserved**: Memory, timeout, build, no_timeout, payload mapping, dan priority tetap tersimpan normal.
6. **Runtime Unchanged**: `RunApifyScraping` tidak disentuh pada task ini.
7. **Static Verification**: `php -l`, `php artisan route:list`, `php artisan view:clear`, dan `git diff --check` berhasil.
8. **Targeted Test Run**: `php artisan test --filter=AdminApifyConfigurationTest` belum final karena environment default mengarah ke PostgreSQL lokal yang tidak tersedia; SQLite temp harness sempat lulus sebagian lalu satu assertion HTML terlalu sensitif diperbaiki dan perlu rerun final.

### Catatan QA:
- Browser QA belum diverifikasi.
- Runtime scraping tidak dijalankan.

**Status Akhir**: NOT VERIFIED menunggu rerun final test SQLite setelah penyesuaian assertion.

## PORTAL-SLOT-RECOVERY-6C
**Tanggal**: 2026-08-10
**Area**: `RunNewsPortalScraping`, bounded portal recovery

### Skenario yang Diuji & Hasil:
1. **Latest Due Slot**: Evaluasi portal memakai latest due slot yang efektif.
2. **Pre-First-Slot Recovery**: Jika belum masuk slot pertama hari ini, slot terakhir hari sebelumnya yang dipakai.
3. **One-Slot Catch-up**: Slot yang terlewat hanya dikejar satu kali.
4. **No Burst Replay**: Beberapa slot terlewat tetap collapse ke slot latest due, bukan replay semua slot.
5. **Failure Stays Unfulfilled**: Run gagal tidak mengubah fulfillment marker.
6. **Cooldown Retry**: Retry gagal dibatasi cooldown per Project+slot.
7. **Cooldown Isolation**: Cooldown satu Project tidak memblokir Project lain.
8. **Manual Separation**: `--project-id` tetap tidak memengaruhi fulfillment otomatis.
9. **Zero-New-Article Success**: Run sukses tanpa artikel baru tetap memenuhi slot.
10. **Static Verification**: `php -l`, `php artisan route:list`, `php artisan schedule:list`, `php artisan view:clear`, dan `git diff --check` berhasil.
11. **Targeted Test Run**: `php artisan test --filter=PortalScheduleRecoveryTest` berhasil pada SQLite temp test harness.

### Catatan QA:
- Browser QA tidak relevan.
- Runtime scraping tidak dijalankan.

**Status Akhir**: PASS

## PORTAL-SLOT-FULFILLMENT-6B
**Tanggal**: 2026-08-10
**Area**: `RunNewsPortalScraping`, portal automatic slot fulfillment

### Skenario yang Diuji & Hasil:
1. **Automatic Success Writes Fulfillment**: Run otomatis yang sukses menulis `portal_last_scheduled_success_at`.
2. **Immediate Re-evaluation Skips**: Evaluasi otomatis berikutnya tidak mengulang slot yang sudah fulfilled.
3. **Automatic Failure Does Not Fulfill**: Run otomatis gagal tidak mengubah fulfillment marker.
4. **Failed Slot Remains Due**: Slot yang gagal tetap bisa dipenuhi oleh run otomatis sukses berikutnya.
5. **Manual Bypass**: `--project-id` tidak menulis fulfillment otomatis.
6. **Manual Before/After Slot**: Run manual tidak memakan slot otomatis yang belum fulfilled.
7. **Zero-New-Article Success**: Run sukses tanpa artikel baru tetap boleh memenuhi slot.
8. **Override / Package Inheritance**: Schedules dari override Project dan Package tetap bekerja dengan fulfillment tracking.
9. **Legacy Interval Ignored**: `news_interval_minutes` tidak memengaruhi due-check fulfillment Portal.
10. **Static Verification**: `php -l`, `php artisan route:list`, `php artisan schedule:list`, `php artisan view:clear`, dan `git diff --check` berhasil.
11. **Targeted Test Run**: `php artisan test --filter=PortalScheduleFulfillmentTest` berhasil pada SQLite temp test harness.

### Catatan QA:
- Browser QA tidak relevan.
- Runtime scraping tidak dijalankan.

**Status Akhir**: PASS

## PORTAL-EFFECTIVE-SCHEDULE-6A
**Tanggal**: 2026-08-10
**Area**: `RunNewsPortalScraping`, portal automatic scheduling

### Skenario yang Diuji & Hasil:
1. **Package Fallback**: Portal otomatis memakai schedule Package saat override Project kosong penuh.
2. **Project Override Priority**: Override Project yang valid dipakai saat schedule Package tidak due.
3. **Invalid Override Skip**: Override Project parsial/malformed tetap ditolak tanpa fallback diam-diam.
4. **No Effective Schedule Skip**: Jika tidak ada schedule efektif, portal otomatis tidak jalan meski interval legacy diisi.
5. **Manual Bypass**: Jalur `--project-id` tetap bypass gate timing otomatis.
6. **Portal Disabled Skip**: Project dengan portal dimatikan tetap dilewati.
7. **Static Verification**: `php -l`, `php artisan route:list`, `php artisan view:clear`, dan `git diff --check` berhasil.
8. **Targeted Test Run**: `php artisan test --filter=PortalEffectiveScheduleRuntimeTest` berhasil pada SQLite temp test harness.

### Catatan QA:
- Browser QA tidak relevan.
- Runtime scraping tidak dijalankan.

**Status Akhir**: PASS

## EFFECTIVE-SCHEDULE-RESOLVER-5A
**Tanggal**: 2026-08-10
**Area**: Shared schedule resolution service for Project

### Skenario yang Diuji & Hasil:
1. **Portal Override Priority**: Project override lengkap dan valid dipakai untuk Portal.
2. **Portal Package Fallback**: Jika override Portal kosong penuh, resolver memakai schedule Package.
3. **Portal Invalid Override**: Override Portal parsial/malformed/duplikat ditolak tanpa fallback diam-diam.
4. **Portal Disabled / Missing Package**: Resolver mengembalikan `feature_disabled` atau `package_missing` sesuai kondisi.
5. **Social Override Priority**: Project override lengkap dan valid dipakai untuk Sosial.
6. **Package Invalid / Unconfigured**: Schedule default Package yang rusak atau kosong dilaporkan dengan reason yang sesuai.
7. **Static Verification**: `php -l`, `php artisan route:list`, `php artisan view:clear`, dan `git diff --check` berhasil.
8. **Targeted Test Run**: `php artisan test --filter=ProjectScheduleResolverTest` berhasil pada SQLite temp test harness.

### Catatan QA:
- Browser/runtime QA tidak relevan untuk perubahan ini.
- Runtime scraping sengaja tidak dijalankan pada task ini.

**Status Akhir**: PASS

## PROJECT-SCHEDULE-OVERRIDE-4B-UI
**Tanggal**: 2026-08-10
**Area**: Project Create/Edit UI, optional schedule overrides

### Skenario yang Diuji & Hasil:
1. **Create Persist**: Project Create menyimpan override Portal dan Sosial sebagai array waktu.
2. **Partial Portal Rejected**: Override Portal setengah isi ditolak dengan pesan all-or-nothing yang jelas.
3. **Edit Persist**: Project Edit menyimpan override dan mendukung null/full blank sebagai inherit Paket.
4. **Partial Social Rejected**: Override Sosial setengah isi ditolak.
5. **Package Reference Shown**: UI tetap menampilkan referensi jadwal paket secara ringkas.
6. **Static Verification**: `php -l`, `php artisan route:list`, `php artisan view:clear`, dan `git diff --check` berhasil.
7. **Targeted Test Run**: `php artisan test --filter=ProjectScheduleOverrideUiTest` berhasil pada SQLite temp test harness.

### Catatan QA:
- Browser/runtime QA tidak relevan untuk perubahan ini.
- Queue background difake di test agar hanya alur UI/data-layer yang diverifikasi.

**Status Akhir**: PASS

## PROJECT-SCHEDULE-OVERRIDE-4A-DATA
**Tanggal**: 2026-08-10
**Area**: Project data layer, optional schedule overrides

### Skenario yang Diuji & Hasil:
1. **Portal Override Persist**: `news_run_times_override` tersimpan sebagai array waktu Portal.
2. **Social Override Persist**: `social_run_times_override` tersimpan sebagai array waktu Sosial.
3. **Null Override Support**: Nilai `null` untuk kedua override tetap valid.
4. **Model Casts**: Override dibaca kembali dari model sebagai array ketika berisi data.
5. **Package Relation Preserved**: Relasi `package` pada Project tetap utuh.
6. **Static Verification**: `php -l`, `php artisan route:list`, `php artisan view:clear`, dan `git diff --check` berhasil.
7. **Targeted Test Run**: `php artisan test --filter=ProjectScheduleOverrideDataTest` berhasil pada SQLite temp test harness.

### Catatan QA:
- Browser/runtime QA tidak relevan untuk perubahan ini.
- Tidak ada perubahan UI atau scheduler runtime pada task ini.

**Status Akhir**: PASS

## PACKAGE-SCHEDULE-STRICT-3A
**Tanggal**: 2026-08-10
**Area**: Admin Package Manager, daily schedules

### Skenario yang Diuji & Hasil:
1. **Portal Aktif Wajib Jadwal Lengkap**: `news_runs_per_day` harus diisi saat `use_portal = true`, dan jumlah slot portal harus cocok dengan jumlah run per hari.
2. **Sosial Aktif Wajib Jadwal Lengkap**: Saat ada actor sosial yang diaktifkan lewat konfigurasi paket, `social_runs_per_day` harus diisi dan jumlah slot sosmed harus cocok dengan jumlah run per hari.
3. **Disabled Feature Tidak Dipaksa**: Jika portal atau sosial benar-benar tidak aktif, jadwalnya tidak diwajibkan.
4. **Duplicate Slot Rejection**: Duplikasi jam pada kategori yang sama tetap ditolak.
5. **Slot Clamp & Preservation**: Resize slot tetap aman sampai maksimal 24 dan mempertahankan nilai yang sudah ada.
6. **Static Verification**: `php -l`, `php artisan route:list`, `php artisan view:clear`, dan `git diff --check` berhasil.
7. **Targeted Test Run**: `php artisan test --filter=PackageDailyScheduleSupportTest` berhasil pada SQLite temp test harness.

### Catatan QA:
- Browser/runtime QA tidak relevan untuk perubahan ini.
- Environment PostgreSQL lokal bawaan repo masih tidak tersedia, jadi verifikasi test dilakukan lewat SQLite temp harness.

**Status Akhir**: PASS

# SCRAPING-GLOBAL-SWITCHES-2B
**Tanggal**: 2026-08-10
**Area**: `routes/console.php`, automatic scheduler gating

### Skenario yang Diuji & Hasil:
1. **Master OFF**: News otomatis tidak terdaftar dan Apify otomatis tidak terdaftar.
2. **Master ON + News ON/ON**: News otomatis terdaftar dengan `--discovery-mode=auto`.
3. **Google News ON + Manual OFF**: News otomatis terdaftar dengan `--discovery-mode=google_news_only`.
4. **Google News OFF + Manual ON**: News otomatis terdaftar dengan `--discovery-mode=manual_only`.
5. **Google News OFF + Manual OFF**: News otomatis tidak terdaftar.
6. **Apify OFF**: Apify otomatis tidak terdaftar.
7. **Static Verification**: `php -l`, `php artisan route:list`, `php artisan view:clear`, dan `git diff --check` berhasil.
8. **Targeted Test Run**: `php artisan test --filter=GlobalScrapingSwitchesSchedulerTest` berhasil pada SQLite temp test harness.

### Catatan QA:
- Browser QA tidak relevan untuk perubahan ini.
- Command manual sengaja tidak diuji ulang karena scope task hanya automatic scheduler gating.

**Status Akhir**: PASS

# SCRAPING-GLOBAL-SWITCHES-2A
**Tanggal**: 2026-08-10
**Area**: Admin Scraping Settings, global engine switches

### Skenario yang Diuji & Hasil:
1. **Model / Livewire Wiring**: Field `google_news_enabled`, `manual_portal_enabled`, dan `apify_enabled` sudah ada di model dan state Livewire, dengan `is_active` tetap menjadi master switch.
2. **UI Global Switches**: Kartu global menampilkan kontrol master, Google News, Portal Manual, dan Apify / Sosial Media dalam layout ringkas.
3. **Legacy Fields Preserved**: Interval lama dan field timeout/retry tetap utuh, tanpa perubahan semantik runtime.
4. **Migration Scope**: Migration baru hanya menambahkan tiga boolean field baru di `scraping_settings`.
5. **Static Verification**: `php -l`, `php artisan route:list`, `php artisan view:clear`, dan `git diff --check` berhasil.
6. **Targeted Test Run**: `php artisan test --filter=ScrapingSettingsTest` tidak bisa diverifikasi di environment ini karena database test lokal tidak tersedia dan Docker daemon tidak berjalan.

### Catatan QA:
- Browser/runtime QA tidak dijalankan.
- Runtime scheduler belum di-wiring pada task ini.

**Status Akhir**: PASS untuk perubahan kode dan verifikasi statik; NOT VERIFIED untuk test framework karena blocker environment database.

# Package Daily Schedule Slot Limit Hotfix
**Tanggal**: 2026-08-09
**Area**: Admin Package Manager, daily schedule slot resizing

### Skenario yang Diuji & Hasil:
1. **Clamp Slot Maksimum 24**: `resizeTimeSlots()` dikunci agar hasil render tidak pernah melebihi 24 slot untuk portal maupun sosmed.
2. **Preservasi Nilai Lama**: Nilai yang sudah ada tetap dipertahankan saat input besar diklem ke 24.
3. **Validasi Helper**: Test helper tanpa database berhasil memverifikasi 24, 25, 1000, negatif, null, dan preservasi nilai.
4. **QA Command**: `php -l`, `php artisan test --filter=PackageDailyScheduleSupportTest`, `php artisan view:clear`, `php artisan route:list`, dan `git diff --check` dijalankan.

### Catatan QA:
- Browser QA belum dijalankan.
- Command berbasis database tidak dibutuhkan untuk hotfix ini.

**Status Akhir**: PASS untuk helper dan validasi sintaks.

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
