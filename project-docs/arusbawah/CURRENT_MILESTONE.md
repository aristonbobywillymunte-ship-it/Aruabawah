# Milestone: Global Scraping Switches 2B Closure

## Tambahan Milestone: PROJECT-SCHEDULE-OVERRIDE-4A-DATA

## Apa yang Diubah
Menambahkan fondasi data-layer Project untuk override jadwal harian opsional per project, tanpa menyentuh UI atau runtime scheduler.

## Behavior Final
- Project kini dapat menyimpan override waktu Portal melalui `news_run_times_override`.
- Project kini dapat menyimpan override waktu Sosial melalui `social_run_times_override`.
- Kedua field bersifat nullable JSON dan dibaca sebagai array oleh model.
- Package tetap menjadi sumber utama untuk `runs_per_day`.
- Tidak ada perubahan pada UI Project create/edit.
- Tidak ada perubahan pada runtime scheduling.

## Komponen Kunci
- `database/migrations/2026_08_10_000001_add_schedule_run_times_overrides_to_projects_table.php`
- `app/Models/Project.php`
- `tests/Feature/ProjectScheduleOverrideDataTest.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: YES
- **Route berubah**: NO
- **Runtime scheduler berubah**: NO
- **Package / Project / Actor scheduling berubah**: NO

## Verifikasi
- `php -l app/Models/Project.php`: PASS
- `php -l database/migrations/2026_08_10_000001_add_schedule_run_times_overrides_to_projects_table.php`: PASS
- `php -l tests/Feature/ProjectScheduleOverrideDataTest.php`: PASS
- `php artisan route:list`: PASS
- `php artisan view:clear`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=ProjectScheduleOverrideDataTest`: PASS pada SQLite temp test harness

## Commit SHA Terkait
- `ed255c999f94487cd442abd7dd4bd01d19d0f907`

## Tambahan Milestone: PACKAGE-SCHEDULE-STRICT-3A

## Apa yang Diubah
Mengunci jadwal harian paket agar portal dan sosial bersifat strict, eksplisit, dan tidak ambigu saat fitur terkait memang aktif.

## Behavior Final
- Jika portal aktif (`use_portal = true`), `news_runs_per_day` wajib diisi dan `news_run_times` harus berisi tepat jumlah slot yang diminta.
- Jika sosial aktif menurut semantik paket yang sudah ada, `social_runs_per_day` wajib diisi dan `social_run_times` harus berisi tepat jumlah slot yang diminta.
- Jadwal portal dan sosial tetap independen.
- Input jam tetap memakai slot dinamis yang dibatasi maksimal 24.
- Duplikasi jam di kategori yang sama tetap ditolak.
- Jika fitur terkait benar-benar tidak aktif, jadwalnya tidak dipaksa.
- Legacy interval `news_interval_minutes` dan `social_interval_minutes` tetap dipertahankan.

## Komponen Kunci
- `app/Livewire/Admin/PackageManager.php`
- `tests/Feature/PackageDailyScheduleSupportTest.php`
- `resources/views/livewire/admin/package-manager.blade.php`
- `app/Models/Package.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: NO
- **Runtime scheduler berubah**: NO
- **Package / Project / Actor scheduling berubah**: NO
- **Legacy intervals berubah**: NO

## Verifikasi
- `php -l app/Livewire/Admin/PackageManager.php`: PASS
- `php -l tests/Feature/PackageDailyScheduleSupportTest.php`: PASS
- `php artisan route:list`: PASS
- `php artisan view:clear`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=PackageDailyScheduleSupportTest`: PASS pada SQLite temp test harness

## Commit SHA Terkait
- `f342ccbfca7efc8c8e7986757df57be36cb4547d`

## Tambahan Milestone: SCRAPING-GLOBAL-SWITCHES-2B

## Apa yang Diubah
Mewiring switch global engine yang persisten ke scheduler otomatis di `routes/console.php` tanpa mengubah command manual, package, project, actor, atau recovery logic.

## Behavior Final
- `scraping_settings.is_active` tetap menjadi master switch untuk scheduler otomatis.
- News scheduler sekarang mengikuti matrix `google_news_enabled` + `manual_portal_enabled`:
  - ON / ON -> `--discovery-mode=auto`
  - ON / OFF -> `--discovery-mode=google_news_only`
  - OFF / ON -> `--discovery-mode=manual_only`
  - OFF / OFF -> tidak ada schedule otomatis News
- Apify scheduler sekarang mensyaratkan `apify_enabled` selain master switch, config safety flag, dan readiness check yang sudah ada.
- Command manual tetap tidak diblokir oleh switch engine DB pada task ini.
- Legacy interval dan seluruh logika scheduling package/project/actor tetap tidak diubah.

## Komponen Kunci
- `routes/console.php`
- `tests/Feature/GlobalScrapingSwitchesSchedulerTest.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: YES
- **Runtime scheduler berubah**: YES, hanya pada gating engine otomatis
- **Package / Project / Actor scheduling berubah**: NO
- **Legacy intervals berubah**: NO

## Verifikasi
- `php -l routes/console.php`: PASS
- `php artisan route:list`: PASS
- `php artisan view:clear`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=GlobalScrapingSwitchesSchedulerTest`: PASS

## Commit SHA Terkait
- Pending

# Milestone: Global Scraping Switches 2A Closure

## Tambahan Milestone: SCRAPING-GLOBAL-SWITCHES-2A

## Apa yang Diubah
Menambahkan tiga switch global scraping yang persisten untuk Google News, Manual Portal, dan Apify/Sosial Media, sambil mempertahankan `scraping_settings.is_active` sebagai master switch utama.

## Behavior Final
- `scraping_settings.is_active` tetap menjadi master switch dan tidak diubah semantiknya.
- Tiga field baru ditambahkan untuk kontrol engine:
  - `google_news_enabled`
  - `manual_portal_enabled`
  - `apify_enabled`
- Ketiganya default `true` agar perilaku produksi tetap aman setelah migrasi.
- Livewire Scraping Settings kini memuat, memvalidasi, dan menyimpan ketiga switch tersebut.
- UI admin menampilkan kontrol master + tiga switch engine dengan label yang ringkas.
- Runtime scheduler, package scheduling, project scheduling, actor scheduling, dan recovery logic belum di-wiring pada task ini.
- Legacy interval fields tetap utuh dan tidak berubah semantik runtime-nya.

## Komponen Kunci
- `database/migrations/2026_08_10_000000_add_global_engine_switches_to_scraping_settings_table.php`
- `app/Models/ScrapingSetting.php`
- `app/Livewire/Admin/ScrapingSettings.php`
- `resources/views/livewire/admin/scraping-settings.blade.php`
- `tests/Feature/ScrapingSettingsTest.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: YES
- **Route berubah**: NO
- **Runtime scheduler berubah**: NO
- **Package / Project / Actor scheduling berubah**: NO
- **Legacy intervals berubah**: NO

## Verifikasi
- `php -l app/Models/ScrapingSetting.php`: PASS
- `php -l app/Livewire/Admin/ScrapingSettings.php`: PASS
- `php artisan route:list`: PASS
- `php artisan view:clear`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=ScrapingSettingsTest`: NOT VERIFIED di environment ini karena database test lokal tidak tersedia dan Docker daemon tidak bisa diakses

## Commit SHA Terkait
- Pending

# Milestone: Package Daily Schedule Slot Limit Hotfix

## Tambahan Milestone: PACKAGE-DAILY-SCHEDULE-SLOT-LIMIT-HOTFIX-2

## Apa yang Diubah
Membatasi dynamic slot jadwal harian agar Livewire tidak pernah merender lebih dari 24 slot time picker untuk portal maupun sosmed.

## Behavior Final
- `resizeTimeSlots()` kini meng-clamp permintaan slot ke maksimum 24.
- Nilai forged atau manual yang lebih besar dari 24 tidak dapat membengkakkan state UI.
- Perilaku tambah/kurang slot, preservasi nilai lama, dan fallback interval lama tetap dipertahankan.

## Komponen Kunci
- `app/Livewire/Admin/PackageManager.php`
- `tests/Feature/PackageDailyScheduleSupportTest.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: NO
- **Backend behavior berubah**: YES, defensif pada state UI Livewire
- **Scraping / AI**: NO

## Commit SHA Terkait
- `e097af3a9437f8dea77c5b7d058c715a6a93fed1`

# Milestone: Package Daily Schedule Time Picker Hotfix

## Tambahan Milestone: PACKAGE-DAILY-SCHEDULE-TIME-PICKER-HOTFIX-1

## Apa yang Diubah
Mengeraskan fitur jadwal scraping harian untuk paket dengan migrasi yang aman, input jam native, validasi per-field, dan fallback interval lama yang tetap utuh.

## Behavior Final
- Kolom jadwal harian dipindah ke migration baru yang aman, sementara migration lama dikembalikan ke tanggung jawab aslinya untuk `news_interval_minutes` dan `social_interval_minutes`.
- Form paket sekarang memakai input `type="time"` native untuk slot jam portal dan sosmed.
- Jumlah slot jam mengikuti `runs_per_day`, dengan nilai lama dipertahankan saat slot bertambah dan sisanya dipangkas saat slot berkurang.
- Validasi mencegah jam kosong, jam ganda, format tidak valid, dan mismatch jumlah slot terhadap jumlah run per hari.
- Scheduler memakai jadwal harian jika tersedia, lalu fallback ke interval lama jika belum dikonfigurasi.

## Komponen Kunci
- `database/migrations/2026_08_03_031226_add_interval_minutes_to_packages_table.php`
- `database/migrations/2026_08_09_220000_add_daily_run_schedule_to_packages_table.php`
- `app/Models/Package.php`
- `app/Livewire/Admin/PackageManager.php`
- `resources/views/livewire/admin/package-manager.blade.php`
- `app/Console/Commands/RunApifyScraping.php`
- `app/Console/Commands/RunNewsPortalScraping.php`
- `tests/Feature/PackageDailyScheduleSupportTest.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: YES
- **Route berubah**: NO
- **Scraping / AI berubah**: YES, hanya pada semantik jadwal paket dan fallback interval

## Commit SHA Terkait
- `067dc1fec1339356e82e06a88cbf9891c7130dfa`

# Milestone: Client Project Assignment Hotfix

## Tambahan Milestone: ADMIN-HEADER-CONSISTENCY-2

## Apa yang Diubah
Menyelesaikan konsistensi visual header untuk 6 halaman Admin yang masih memakai header lokal atau menaruh konten berat di area header.

## Behavior Final
- `Apify`, `Apify Financial Report`, `Packages`, `News Sources`, `Branding Aplikasi`, dan `AI Prompt Templates` kini memakai pola header Admin yang konsisten.
- Dashboard tetap dipakai hanya sebagai referensi visual, bukan dependensi komponen.
- Kontrol interaktif dipindahkan ke toolbar/konten jika sebelumnya membebani header.
- Informational warning dan kartu penjelasan dipindah keluar dari header ke konten halaman.
- Tidak ada shared header component dan tidak ada perubahan business logic.

## Komponen Kunci
- `resources/views/admin/apify-financial-report.blade.php`
- `resources/views/admin/packages.blade.php`
- `resources/views/admin/branding.blade.php`
- `resources/views/admin/ai-prompt-templates.blade.php`
- `resources/views/livewire/admin/apify-configuration.blade.php`
- `resources/views/livewire/admin/apify-financial-report.blade.php`
- `resources/views/livewire/admin/package-manager.blade.php`
- `resources/views/livewire/admin/news-sources.blade.php`
- `resources/views/livewire/admin/branding-manager.blade.php`
- `resources/views/livewire/admin/ai-prompt-templates.blade.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: NO
- **Scraping / AI berubah**: NO

## Commit SHA Terkait
- `6b39ef8443f2b7fce5dd8d047e723b647a389838`

## Tambahan Milestone: ADMIN-HEADER-STRUCTURE-HOTFIX-2

## Apa yang Diubah
Memperbaiki struktur Blade pada `system-logs` dan `client-settings` setelah hotfix header sebelumnya, agar setiap halaman tetap punya satu root Livewire yang valid.

## Behavior Final
- `system-logs` sekarang memiliki satu root Livewire, tanpa orphan `@endsection`.
- `client-settings` sekarang menempatkan Back button di dalam root, dekat toolbar atas, sebelum section proyek.
- Tidak ada perubahan pada page-header ownership: `logs` tetap dimiliki wrapper `admin/logs.blade.php`, dan `client-settings` tetap dimiliki wrapper `admin/clients-settings.blade.php`.
- Tidak ada perubahan pada Select2, assign/detach, quota, save flow, permissions, route, scraping, AI, atau database operations.

## Komponen Kunci
- `resources/views/livewire/admin/system-logs.blade.php`
- `resources/views/livewire/admin/client-management/client-settings.blade.php`
- `resources/views/admin/logs.blade.php`
- `resources/views/admin/clients-settings.blade.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: NO
- **Scraping / AI berubah**: NO

## Commit SHA Terkait
- `f56f6f5c774ea781d191c661aeb8b48e32fc19ca`

## Tambahan Milestone: ADMIN-HEADER-VISUAL-CONSISTENCY-1

## Apa yang Diubah
Menyamakan tampilan header halaman Admin agar mengikuti gaya visual Dashboard yang sudah ada, tanpa membuat komponen header bersama atau mengubah perilaku halaman.

## Behavior Final
- Header Admin kini memakai pola visual yang konsisten: eyebrow `Panel Administrator`, judul 2xl tebal, deskripsi singkat, rata kiri, dan jarak vertikal yang seragam.
- Dashboard tetap dipakai hanya sebagai referensi visual, bukan dependensi bersama.
- Halaman wrapper `admin/*` yang memuat Livewire kini memiliki header yang seragam, sementara aksi interaktif tetap berada di toolbar konten bila diperlukan.
- Halaman client management, users, logs, maintenance, database, scraping settings, dan AI providers diselaraskan tipografinya untuk menghindari judul ganda dan spacing yang tidak konsisten.
- Tidak ada perubahan route, permission, migration, scraping, AI behavior, atau skema database.

## Komponen Kunci
- `resources/views/layouts/admin.blade.php`
- `resources/views/admin/*.blade.php`
- `resources/views/livewire/admin/**/*.blade.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: NO
- **Scraping / AI berubah**: NO

## Commit SHA Terkait
- `f56f6f5c774ea781d191c661aeb8b48e32fc19ca`

## Apa yang Diubah
Menambahkan fitur relasi *Assign* & *Detach* antara Client dan Project di halaman `Client Settings` melalui antarmuka *multi-select* (Select2 pillbox).

## Behavior Final
- User internal dapat melihat, menambah (multi-select), dan melepas proyek yang diakses oleh klien.
- "Lepas" hanya menghapus relasi pivot (`project_user`), tidak menghapus proyek atau memicu *scraping*.
- Quota (limit `max_projects` aktif) klien ditegakkan secara absolut selama pemilihan proyek, menjumlahkan total proyek aktif existing dengan pilihan baru.
- UI menggunakan Select2 pillbox (multi-select) yang dapat memuat ulang opsinya dengan handal saat SPA navigation menggunakan `@assets` dan `@script` Livewire 4. Desain dipercantik dengan meta-data opsi (*templateResult*), *placeholder* & label informatif, serta status tombol *disabled* jika kosong.
- Perataan vertikal untuk teks pencarian, input sebaris, ikon hapus (`x`), dan teks tombol telah disempurnakan menggunakan `display: flex`, menjamin tata letak piksel sempurna di berbagai perangkat.
- Select2 initialization dibuat *idempotent* (destroy-before-init) dan ter-*scope* ke instance `$wire.$el` agar tidak bentrok saat Livewire 4 melakukan morphing DOM.
- Relasi Assign/Detach otomatis mereset dan memuat ulang dropdown options Select2 tanpa merusak integrasi JavaScript.
- Alur penyimpanan Client Settings disempurnakan dengan *loading spinner* interaktif (`wire:loading`), navigasi SPA instan menggunakan `wire:navigate` ke halaman Client Management, dan notifikasi konfirmasi *sweetalert* global (`admin-toast`).
- Menghapus UI tombol "Sinkronisasi" / "Sinkronisasi Ulang" manual dari setiap kartu proyek untuk menyederhanakan antarmuka, namun tetap mempertahankan mekanisme *background resync* otomatis (`ProjectContentResyncJob` & `ContentMatchingService`).

## Komponen Kunci
- `app/Livewire/Admin/ClientManagement/ClientSettings.php` (pengaturan logic `syncWithoutDetaching` dan array validation)
- `resources/views/livewire/admin/client-management/client-settings.blade.php` (integrasi Select2 CDN, SPA event hooking, `$wire.$set` Livewire 4)
- `tests/Feature/ClientProjectAssignmentTest.php` (unit test limitasi aktif/non-aktif)
- `resources/views/welcome.blade.php` (struktur root element / stack scripts)
- `resources/views/components/⚡projects-list.blade.php` (UI styling & removal of manual sync button)

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO (memanfaatkan relasi `project_user` existing)
- **Scraping / AI / Route berubah**: NO (Scraping scheduler & AI pipeline unchanged)
- **Automatic Resync Preserved**: YES (Create/Edit otomatis men-trigger backend sync)

## Commit SHA Terkait
- `3e71ac8` (fix: stabilize client project select2 on livewire 4)
- `3e59302` (fix: use native Livewire v3 @assets and @script for robust select2 SPA init)
- `7c12653` (fix: select2 init hooks on SPA navigation)
- `96ce116` (fix: add @stack('scripts') to welcome layout for select2)
- `fdab246` (feat: select2 multi-select pillbox for projects)
- `cefc5f2` (fix: use toast and select2 for client project assignment)
- `876a9b8` (feat: allow internal users to manage client project assignments)

---

# Milestone: PROJECT-TRASH-AUTH-HARDENING

## Apa yang Diubah
Memperkuat otorisasi aksi Deactivate, Restore, dan Force Delete proyek agar Client tidak dapat mengakses fitur yang tidak seharusnya, bahkan dengan bypass UI.

## Behavior Final
- **Terminologi**: Rename "Lihat Proyek Dihapus" dan "Daftar Proyek Dihapus" menjadi "Proyek Dinonaktifkan" di seluruh UI.
- **Tombol Deactivate**: Disembunyikan dari Client jika `clientSettings.can_delete_projects = false`. Admin/User Internal tetap memiliki akses penuh.
- **Modal Proyek Dinonaktifkan**: Hanya dapat dibuka oleh Admin dan User Internal. Client tidak dapat mengakses modal restore/force-delete meski memiliki `can_delete_projects = true`.
- **Backend Guards**: Semua method `confirmDeleteProject`, `deleteProject`, `openTrashedProjectsModal`, `confirmRestoreProject`, `restoreProject`, `confirmForceDeleteProject`, `forceDeleteProject` dilindungi oleh pemeriksaan `isClient()` eksplisit.
- **Permanent Delete Message**: Diperbarui menjadi pesan yang akurat tentang preservasi data sumber dan penghapusan relasi operasional.
- **Source Data Preservation**: `Article` dan `SocialMediaItem` tidak dihapus saat force delete — hanya kolom `project_id` yang di-null-kan.
- **Dedicated Method**: Tombol modal trashed menggunakan `wire:click="openTrashedProjectsModal"` (bukan inline `$set`) untuk menjamin guard backend aktif.

## Komponen Kunci
- `app/Http/Livewire/ProjectsList.php` (backend auth guards, `openTrashedProjectsModal`)
- `resources/views/components/⚡projects-list.blade.php` (UI rename, visibility guards, button restrictions)
- `tests/Feature/ProjectTrashAuthorizationTest.php` (17 regression tests baru)

## Status
- **Migration berubah**: NO
- **Scraping / AI / Route berubah**: NO
- **Source Article/Social preservation**: YES
- **Stale Volt class di blade**: DOCUMENTED (dijadikan catatan cleanup terpisah)
