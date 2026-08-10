# Project Memory

# Konteks Fitur SCRAPING-GLOBAL-SWITCHES-2B
- **Alasan**: Switch global engine yang disimpan di DB perlu benar-benar mengontrol scheduler otomatis, sementara command manual dan runtime schedule lain tetap aman.
- **Solusi**: `routes/console.php` sekarang memilih mode discovery News dari kombinasi `google_news_enabled` dan `manual_portal_enabled`, serta menahan scheduling Apify otomatis saat `apify_enabled` nonaktif.
- **Batasan**: Tidak ada perubahan pada package schedule, project schedule, actor interval, recovery, daily schedule, atau command manual semantics.
- **QA**: Test scheduler matrix membangun app SQLite temp sendiri, memuat ulang scheduler, dan memverifikasi command otomatis yang terdaftar.

# Konteks Fitur SCRAPING-GLOBAL-SWITCHES-2A
- **Alasan**: Sistem membutuhkan switch global persisten per engine agar Google News, Portal Manual, dan Apify/Sosial Media bisa dikontrol terpisah tanpa mengubah runtime scheduler dulu.
- **Solusi**: Menambahkan field `google_news_enabled`, `manual_portal_enabled`, dan `apify_enabled` ke `scraping_settings`, lalu memuat dan menyimpan semuanya dari Livewire Scraping Settings.
- **Batasan**: `scraping_settings.is_active` tetap menjadi master switch; runtime scheduler, package, project, actor, dan recovery logic tidak diubah pada task ini.
- **Fallback / Legacy**: Interval lama tetap dipertahankan dan belum dipensiunkan.

## Konteks Fitur PACKAGE-DAILY-SCHEDULE-SLOT-LIMIT-HOTFIX-2
- **Alasan**: Dynamic time picker jadwal harian bisa dipaksa merender terlalu banyak slot sebelum validasi save berjalan.
- **Solusi**: `resizeTimeSlots()` dikunci maksimal 24 elemen sehingga state UI tidak pernah membengkak lewat batas yang diizinkan.
- **Batasan**: Tidak ada perubahan migrasi, scheduler, route, atau logika bisnis paket; hanya hardening defensive pada state Livewire.
- **QA**: Test helper tanpa database dipakai untuk memverifikasi 24, 25, 1000, negatif, null, dan preservasi nilai lama saat diklem.

## Konteks Fitur PACKAGE-DAILY-SCHEDULE-TIME-PICKER-HOTFIX-1
- **Alasan**: Paket scraping perlu jadwal harian yang lebih jelas daripada interval menit, dengan slot jam native yang mudah diatur per portal dan sosmed.
- **Solusi**: Menyimpan `runs_per_day` dan `run_times` sebagai array JSON terpisah untuk portal dan sosial media, lalu memetakan slot itu ke input `type="time"` dinamis di form paket.
- **Batasan**: Migration lama dikembalikan ke fungsi awalnya; migration baru dipakai untuk kolom schedule harian agar deployment production yang sudah pernah migrasi tetap aman.
- **Fallback**: Jika jadwal harian tidak diisi, scheduler tetap memakai `news_interval_minutes` dan `social_interval_minutes` agar paket existing tidak rusak.

## Konteks Fitur ADMIN-HEADER-CONSISTENCY-2
- **Alasan**: Enam halaman Admin masih memiliki header lokal yang tidak sepenuhnya konsisten dengan pola visual Dashboard.
- **Solusi**: Menambahkan `page-header` yang ringan di wrapper untuk Apify Financial Report, Packages, Branding, dan AI Prompt Templates; mempertahankan teleport pada News Sources; serta memindahkan warning/kontrol berat ke toolbar konten atau block informasi di bawah header.
- **Batasan**: Tidak ada shared header component, tidak ada refactor layout besar, dan tidak ada perubahan business logic.

## Konteks Fitur ADMIN-HEADER-STRUCTURE-HOTFIX-2
- **Alasan**: Setelah hotfix header sebelumnya, audit menemukan dua regresi struktur Blade yang berpotensi memicu render error Livewire.
- **Root Cause**: `resources/views/livewire/admin/system-logs.blade.php` masih memiliki orphan `@endsection` dan penutup root tambahan, sedangkan `resources/views/livewire/admin/client-management/client-settings.blade.php` masih memiliki Back button di luar root utama.
- **Solusi**: Menjaga page-header tetap dimiliki wrapper, lalu membenahi struktur Livewire agar masing-masing view hanya memiliki satu root element dan semua kontrol penting tetap berada di dalam root yang sama.
- **Batasan**: Tidak ada perubahan backend, route, permission, scraper, AI, atau migrasi.

## Konteks Fitur ADMIN-HEADER-VISUAL-CONSISTENCY-1
- **Alasan**: Beberapa halaman Admin menampilkan header dengan markup dan spacing yang berbeda-beda, sehingga pengalaman visual terasa tidak seragam dibanding Dashboard.
- **Solusi**: Menyamakan header per-halaman pada wrapper Blade dan komponen Livewire yang memang membutuhkan toolbar interaktif, dengan mempertahankan judul, deskripsi, dan action masing-masing halaman.
- **Batasan**: Tidak dibuat komponen header bersama, tidak ada refactor layout besar, dan tidak ada perubahan business logic atau navigasi.

## Konteks Fitur Client Project Assignment
- **Alasan & Root Cause**: Client memerlukan penunjukan proyek manual oleh akun internal (Admin) tanpa menghapus database asal proyek saat dilepas (`detach`). Integrasi antar-halaman berbasis Livewire SPA (`wire:navigate`) menyebabkan komponen JavaScript dari pihak ketiga (Select2) gagal diinisialisasi ulang karena `livewire:initialized` hanya jalan sekali di awal. Livewire 4 mewajibkan akses properti JS scope lewat identifier khusus `$wire.$set`, `$wire.$get`, `$wire.$on` (bukan `$wire.set`).
- **Solusi**: Menggunakan fitur bawaan Livewire 4 yaitu `@assets` dan `@script` untuk melampirkan *dependencies* CDN secara *lazy-load* dan aman. Syntax pemanggilan di-update ke `$wire.$set`, `$wire.$get`, `$wire.$on` dan Select2 scope difokuskan pada DOM component via `$($wire.$el)`. Setiap inisialisasi Select2 dibuat *idempotent* dengan mendestroy instance lama sebelum *re-init*. Batas maksimal proyek klien (`max_projects`) selalu mengacu pada metode turunan `getEffectiveMaxProjects()` dan diverifikasi ketat di back-end.
- **UX Simpan Pengaturan (Save Flow)**: Menghindari flash message sepihak dengan cara mengubah form submit untuk menampilkan status `wire:loading` ("Menyimpan...") yang di-scope ketat via `wire:target="saveSettings"`. Setelah penyimpanan berhasil, sistem menggunakan session success flash lalu *redirect* SPA (`wire:navigate`) ke halaman manajemen klien dan memunculkan *success toast* di sana. *Validation errors* tertahan di halaman Client Settings dan ditampilkan secara visual melalui *Livewire validation error bag* (teks merah pada form fields). *Transaction/save exceptions* (seperti gagal ke DB) tertahan di halaman Client Settings dan dimunculkan melalui notifikasi merah via `admin-toast`.
- **Penghapusan Sinkronisasi Manual**: UI "Sinkronisasi" / "Sinkronisasi Ulang" manual dari kartu proyek (*Project Card*) telah dihilangkan untuk menyederhanakan interface. Walaupun UI Sinkronisasi manual dibuang, mekanisme re-sync latar belakang otomatis (*ProjectContentResyncJob* & *ContentMatchingService*) saat `Create` dan `Edit` tetap dipertahankan. Semua aksi kartu lainnya (Edit, Hapus, Deaktivasi) dan pipeline scraping serta AI insight tidak terpengaruh oleh perubahan antarmuka ini.

## Konteks Fitur PROJECT-TRASH-AUTH-HARDENING
- **Root Issue**: Method `accessibleBy(auth()->user())` tidak cukup sebagai guard untuk aksi trash (restore/force-delete). Client dapat mengakses project yang ter-assign padanya melalui scope ini, yang berpotensi memungkinkan Client memanggil `restoreProject()` atau `forceDeleteProject()` secara langsung via bypass UI.
- **Solusi**: Setiap method trash di `app/Http/Livewire/ProjectsList.php` sekarang memiliki guard `$user->isClient()` eksplisit. Method `openTrashedProjectsModal()` ditambahkan sebagai pintu masuk terautentikasi menggantikan inline `$set('showTrashedModal', true)`. Client dengan `can_delete_projects = true` boleh menonaktifkan proyek, tetapi tidak boleh membuka modal trashed, restore, atau force-delete.
- **Aturan Permanent Delete**: Hanya Admin dan User Internal yang dapat melakukan force-delete. Client TIDAK PERNAH diperbolehkan, apapun nilai `can_delete_projects`-nya.
- **Preservasi Data Sumber**: `forceDeleteProject()` TIDAK menghapus record `Article` atau `SocialMediaItem`. Hanya kolom `project_id` yang di-null-kan, dan relasi operasional (pivot `project_user`, `ai_analysis_dispatch_states`, `apify_dispatch_states`, dll.) yang dihapus.
- **Stale Volt Class di Blade**: `resources/views/components/⚡projects-list.blade.php` mengandung anonymous PHP class (stale Volt-like syntax) yang tidak dieksekusi — backend logic aktif berasal dari `app/Http/Livewire/ProjectsList.php`. Cleanup refactoring dijadwalkan sebagai task terpisah.
- **UI Terminology**: "Lihat Proyek Dihapus" dan "Daftar Proyek Dihapus" diubah menjadi "Proyek Dinonaktifkan" karena soft-delete hanya menonaktifkan proyek dari monitoring, bukan menghapus datanya.
