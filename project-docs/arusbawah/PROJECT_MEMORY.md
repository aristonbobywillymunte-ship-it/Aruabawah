# Konteks Penutup Scheduling Phase
- Scheduling is no longer an open development phase.
- Future scheduling work harus tetap mempertahankan hierarchy `Global -> Package -> Project -> Resolver`.
- Fulfillment tetap success-only.
- Recovery tetap latest-due bounded recovery.
- Manual/force execution tetap terisolasi dari fulfillment otomatis.
- Comment Scraper tetap terisolasi dari MAIN Actor fulfillment.
- Actor interval bukan business scheduling authority.
- Production migration baseline harus dipertahankan.
- Arsitektur ini tidak boleh didesain ulang kecuali diminta secara eksplisit.

# Project Memory

## Konteks Fitur APIFY-SLOT-RECOVERY-8C
- **Alasan**: Social/Apify otomatis perlu bounded recovery agar slot yang terlewat atau gagal mengejar latest-due-slot sekali saja tanpa replay backlog.
- **Solusi**: `RunApifyScraping` sekarang menghitung latest due slot yang efektif, membatasi recovery ke satu slot terbaru, dan tetap memakai cooldown operasional yang sudah ada.
- **Batasan**: Tidak ada perubahan pada resolver, slot fulfillment 8B, comment scraping, package/project UI, atau arsitektur job.
- **Fallback / Legacy**: Banyak slot yang terlewat tetap collapse ke satu latest due slot; legacy ambiguous success rows tidak dipakai untuk fulfillment.

## Konteks Fitur APIFY-SLOT-FULFILLMENT-8B
- **Alasan**: Fulfillment slot Sosial/Apify harus bergantung hanya pada sukses scheduled MAIN Actor yang sah, bukan pada success komentar, force-dispatch, atau timestamp attempt.
- **Solusi**: Menambahkan marker persisten `is_scheduled_execution`, menyempitkan lookup fulfillment ke `project_id` + `actor_id` + status `success`, lalu memakai `completed_at` saja sebagai timestamp fulfillment.
- **Batasan**: Tidak ada perubahan pada schedule resolver, Portal command, package/project UI, comment scraping behavior, atau recovery yang lebih luas.
- **Fallback / Legacy**: Legacy success rows yang tidak punya marker scheduled execution tidak dipercaya untuk fulfillment; ini sengaja agar data ambigu tidak salah memakan slot.

## Konteks Fitur EFFECTIVE-SCHEDULE-RESOLVER-5B-INVALID-STATE-HOTFIX
- **Alasan**: Resolver schedule efektif harus membedakan override kosong dari override invalid, supaya inherit Package tidak tercampur dengan state proyek yang rusak.
- **Solusi**: `ProjectScheduleResolver` sekarang mengembalikan `package_schedule_not_configured` hanya untuk schedule Package yang benar-benar kosong, dan `invalid_package_schedule` untuk schedule Package yang terisi tapi malformed.
- **Batasan**: Tidak ada perubahan pada Portal command, `RunApifyScraping`, slot fulfillment, recovery, actor behavior, package/project UI, atau migrasi.
- **Fallback / Legacy**: Override Project kosong penuh tetap inherit Package; override non-empty yang invalid tidak pernah fallback diam-diam.

## Konteks Fitur APIFY-EFFECTIVE-SCHEDULE-8A
- **Alasan**: Jalur otomatis Sosial Apify perlu schedule efektif Project dari resolver yang sama supaya override Project yang valid benar-benar mengalahkan jadwal Package.
- **Solusi**: `RunApifyScraping` sekarang memakai `ProjectScheduleResolver::resolveSocial()` untuk mode otomatis, lalu aman skip saat resolver tidak menghasilkan schedule yang layak dipakai.
- **Batasan**: Tidak ada perubahan pada Portal, `latestProjectActorRunAt()`, `--force-dispatch`, `--project-id`, comment scraper, atau slot fulfillment/recovery.
- **Fallback / Legacy**: `packages.social_interval_minutes` dan fallback legacy lain tidak dipakai; jika schedule efektif tidak tersedia, automatic social dispatch berhenti aman.

## Konteks Fitur EFFECTIVE-SCHEDULE-RESOLVER-5B-CLOSURE
- **Alasan**: Gaps QA/doc pada hotfix 5B perlu ditutup agar state empty versus invalid tetap konsisten di resolver, runtime social, dan dokumen milestone.
- **Solusi**: Menambahkan coverage untuk non-string/duplicate/malformed schedule override dan package invalid state, lalu mengoreksi SHA milestone yang sebelumnya salah tercatat.
- **Batasan**: Tidak ada perubahan pada arsitektur scheduling, Portal command, slot fulfillment, recovery, actor behavior, atau migrasi.
- **Fallback / Legacy**: Override kosong tetap inherit Package; non-string/duplicate/malformed tidak pernah inherit diam-diam.

## Konteks Fitur ACTOR-INTERVAL-REMOVAL-7B-RUNTIME
- **Alasan**: Interval Actor harus berhenti memengaruhi keputusan runtime, karena scheduling Sosial kini sementara bergantung pada jadwal harian Paket dan cooldown operasional harus terpisah dari konfigurasi Actor.
- **Solusi**: `RunApifyScraping` dan `ApifyScrapingJob` tidak lagi membaca `actor.interval_minutes` untuk due-check atau cooldown; runtime memakai jadwal harian Paket yang valid dan cooldown operasional dari `services.apify.schedule_retry_cooldown_minutes`.
- **Batasan**: Tidak ada wiring `ProjectScheduleResolver::resolveSocial()` pada task ini, tidak ada perubahan pada Portal, dan DB legacy `interval_minutes` tetap dipertahankan.
- **Fallback / Legacy**: Jika jadwal harian Paket tidak valid atau kosong, otomatis Sosial di-skip aman. `packages.social_interval_minutes` tetap ada di DB tetapi tidak dipakai sebagai fallback runtime.

## Konteks Fitur ACTOR-INTERVAL-REMOVAL-7A-UI
- **Alasan**: Interval Actor tidak boleh lagi menjadi pengaturan yang bisa diedit di admin surface karena scheduling kini ditentukan oleh Package/Project, sementara Actor hanya menjelaskan bagaimana scraping berjalan.
- **Solusi**: Menghapus field `interval_minutes` dari form, state, validasi, dan payload save/update di `ApifyConfiguration`, serta mengganti judul section menjadi `Konfigurasi Performa`.
- **Batasan**: Kolom DB legacy `apify_actors.interval_minutes` tetap dipertahankan, dan runtime `RunApifyScraping` belum diubah pada task ini.
- **Fallback / Legacy**: Data legacy interval masih ada di database dan bisa dibaca runtime lama sampai task 7B menghapus dependensi runtime-nya.

## Konteks Fitur PORTAL-SLOT-RECOVERY-6C
- **Alasan**: Portal otomatis perlu recovery bounded agar slot yang terlewat atau gagal dapat dikejar sekali tanpa replay backlog atau retry setiap menit.
- **Solusi**: Menambahkan latest-due-slot evaluation dan cooldown retry per Project+slot di `RunNewsPortalScraping`, dengan cooldown duration kecil dari `config/services.php`.
- **Batasan**: Tidak ada perubahan pada ProjectScheduleResolver precedence, project/package UI, Apify/social runtime, global switches, atau pemisahan fulfillment 6B.
- **Catatan Implementasi**: Manual `--project-id` tetap terpisah; retry cooldown hanya menunda slot yang gagal, bukan menandainya fulfilled.

## Konteks Fitur PORTAL-SLOT-FULFILLMENT-6B
- **Alasan**: Portal otomatis membutuhkan penanda fulfillment yang terpisah dari attempt/prioritas supaya slot harian hanya dianggap terpakai setelah eksekusi otomatis sukses.
- **Solusi**: Menambahkan `portal_last_scheduled_success_at` pada Project, membaca marker itu untuk due-check portal otomatis, dan menulisnya hanya setelah eksekusi otomatis selesai sukses.
- **Batasan**: Tidak ada perubahan pada ProjectScheduleResolver precedence, project/package UI, Apify/social runtime, global switches, atau prioritas project selain pemisahan fulfillment.
- **Catatan Implementasi**: Manual `--project-id` tidak menulis fulfillment otomatis; prioritas tetap memakai `news_last_scraped_at`.

## Konteks Fitur PORTAL-EFFECTIVE-SCHEDULE-6A
- **Alasan**: Jalur otomatis portal perlu memakai schedule efektif Project agar override per project benar-benar mengalahkan default Package saat menentukan kapan scraping berjalan.
- **Solusi**: `RunNewsPortalScraping` sekarang memanggil `ProjectScheduleResolver::resolvePortal()` pada mode otomatis dan memakai hasilnya untuk gate timing portal.
- **Batasan**: Tidak ada perubahan pada Apify/social runtime, global switches, package schedule UI, project override data/UI, atau semantics `--project-id` manual.
- **Catatan Implementasi**: Query project otomatis harus membawa kolom `news_run_times_override` dan `social_run_times_override`, kalau tidak override Project tidak akan terbaca.

## Konteks Fitur EFFECTIVE-SCHEDULE-RESOLVER-5A
- **Alasan**: Perhitungan schedule efektif Project perlu sumber tunggal agar precedence override Project versus default Package bisa dibaca konsisten di langkah lanjutan.
- **Solusi**: Menambahkan `ProjectScheduleResolver` untuk mengembalikan schedule Portal dan Sosial efektif beserta `source` dan `reason` yang kecil, deterministik, dan mudah diuji.
- **Batasan**: Tidak ada perubahan pada `RunNewsPortalScraping`, `RunApifyScraping`, `routes/console.php`, atau job runtime lain pada task ini.
- **Fallback / Legacy**: Override Project yang kosong penuh tetap berarti memakai schedule Package.

## Konteks Fitur PROJECT-SCHEDULE-OVERRIDE-4B-UI
- **Alasan**: Project membutuhkan UI create/edit untuk override jadwal harian opsional agar user bisa memilih ikut jadwal paket atau mengatur jam sendiri per project.
- **Solusi**: Menambahkan state Livewire dan input `type="time"` dinamis di Project Create/Edit yang mengikuti `runs_per_day` paket, lalu menyimpan override sebagai array normalized atau `null` jika seluruh slot dikosongkan.
- **Batasan**: Tidak ada perubahan pada runtime scheduling, package schedule, actor interval, project/package relation, authorization, atau content sync.
- **Fallback / Legacy**: Mengosongkan semua slot tetap berarti inherit jadwal paket.

## Konteks Fitur PROJECT-SCHEDULE-OVERRIDE-4A-DATA
- **Alasan**: Project perlu fondasi data-layer untuk override jadwal harian opsional agar nanti bisa menyimpan waktu Portal dan Sosial sendiri tanpa mengubah sumber utama paket.
- **Solusi**: Menambahkan dua field JSON nullable pada `projects`, yaitu `news_run_times_override` dan `social_run_times_override`, lalu memetakan keduanya sebagai array di model `Project`.
- **Batasan**: Tidak ada perubahan pada UI Project create/edit, runtime scheduling, project/package resolution logic, AI, matching, authorization, atau content sync.
- **Fallback / Legacy**: Paket tetap menjadi sumber utama untuk `runs_per_day`; override project hanya menyimpan waktu opsional untuk langkah lanjutan.

## Konteks Fitur PACKAGE-SCHEDULE-STRICT-3A
- **Alasan**: Jadwal harian paket perlu bersifat tegas agar portal dan sosial tidak bisa disimpan dengan jumlah run yang tidak lengkap atau ambigu.
- **Solusi**: `PackageManager` sekarang mewajibkan `news_runs_per_day` beserta seluruh `news_run_times` saat portal aktif, dan mewajibkan `social_runs_per_day` beserta seluruh `social_run_times` saat sosial benar-benar aktif menurut semantik paket yang sudah ada.
- **Batasan**: Tidak ada perubahan pada schedule runtime, `RunNewsPortalScraping`, `RunApifyScraping`, `routes/console.php`, Apify actor interval, failed-slot recovery, global scraping switches, AI/matching/notifications, atau package quotas/client limits.
- **Fallback / Legacy**: `news_interval_minutes` dan `social_interval_minutes` tetap dipertahankan untuk paket lama, dan slot jam dinamis tetap dibatasi maksimal 24.

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
