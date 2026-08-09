# Milestone: Client Project Assignment Hotfix

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

## Komponen Kunci
- `app/Livewire/Admin/ClientManagement/ClientSettings.php` (pengaturan logic `syncWithoutDetaching` dan array validation)
- `resources/views/livewire/admin/client-management/client-settings.blade.php` (integrasi Select2 CDN, SPA event hooking, `$wire.$set` Livewire 4)
- `tests/Feature/ClientProjectAssignmentTest.php` (unit test limitasi aktif/non-aktif)
- `resources/views/welcome.blade.php` (struktur root element / stack scripts)

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO (memanfaatkan relasi `project_user` existing)
- **Scraping / AI / Route berubah**: NO

## Commit SHA Terkait
- `3e71ac8` (fix: stabilize client project select2 on livewire 4)
- `3e59302` (fix: use native Livewire v3 @assets and @script for robust select2 SPA init)
- `7c12653` (fix: select2 init hooks on SPA navigation)
- `96ce116` (fix: add @stack('scripts') to welcome layout for select2)
- `fdab246` (feat: select2 multi-select pillbox for projects)
- `cefc5f2` (fix: use toast and select2 for client project assignment)
- `876a9b8` (feat: allow internal users to manage client project assignments)
