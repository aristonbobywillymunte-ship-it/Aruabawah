# Milestone: SCHEDULING-PHASE-CLOSEOUT

## SCHEDULING PHASE
**CLOSED / PRODUCTION READY**

## Final Architecture

### GLOBAL
- `scraping_settings.is_active` adalah master automatic scraping switch.
- `google_news_enabled` mengontrol eksekusi otomatis Google News.
- `manual_portal_enabled` mengontrol eksekusi otomatis Portal Manual.
- `apify_enabled` mengontrol eksekusi otomatis Social/Apify.
- Global hanya menentukan engine mana yang boleh berjalan.

### PACKAGE
- Package menentukan jumlah run otomatis dan jam default.
- Portal memakai `news_runs_per_day` dan `news_run_times`.
- Social memakai `social_runs_per_day` dan `social_run_times`.
- Maksimum slot harian yang didukung adalah 24.
- Jadwal harian Package adalah default yang authoritative.

### PROJECT
- Project hanya boleh meng-override waktu, bukan jumlah run.
- Portal memakai `news_run_times_override`.
- Social memakai `social_run_times_override`.
- Override kosong mewarisi Package.
- Override lengkap dan valid menggantikan waktu Package.
- Override malformed, partial, duplicate, atau count-mismatch memblokir automatic execution dengan aman.

### RESOLVER
- `ProjectScheduleResolver` adalah authority efektif schedule bersama.
- Entry point: `resolvePortal()` dan `resolveSocial()`.
- Precedence: valid Project override > Package schedule > safe no-schedule state.

### PORTAL
- `RunNewsPortalScraping` memakai effective Portal schedule.
- Fulfillment bersifat success-only.
- `portal_last_scheduled_success_at` adalah marker fulfillment persisten.
- Failed automatic run tetap due.
- Latest-due-slot recovery aktif.
- Sebelum slot pertama hari ini, slot final hari sebelumnya bisa direcover.
- Beberapa missed slot collapse ke satu latest due slot.
- Tidak ada burst replay.
- Operational cooldown mencegah hot-loop retry.
- Eksekusi explicit/manual Project tidak memakan slot otomatis.

### SOCIAL / APIFY
- `RunApifyScraping` memakai effective Social schedule.
- Fulfillment bersifat actor-specific dengan `project_id + actor_id`.
- Fulfillment membutuhkan `status = success`, `is_scheduled_execution = true`, dan `completed_at IS NOT NULL`.
- `queued_at` dan `started_at` bukan fulfillment.
- `queued`, `processing`, `retry_wait`, dan `failed` tidak fulfill slot.
- `--force-dispatch` tidak fulfill slot.
- Comment Scraper tidak memenuhi schedule MAIN Actor.
- Actor lain pada platform yang sama tidak memenuhi Actor lain.
- Latest-due recovery aktif.
- Pre-first-slot previous-day recovery aktif.
- Multiple missed slots collapse ke latest due.
- Tidak ada backlog burst replay.
- Operational cooldown tetap terpisah dari fulfillment.

### ACTOR
- Actor mengontrol bagaimana scraping bekerja.
- Actor boleh mengontrol payload, limits, memory, timeout, build, priority, dan konfigurasi platform-specific.
- Actor interval bukan authority business scheduling otomatis.
- `interval_minutes` legacy boleh tetap ada untuk kompatibilitas saja.
- `social_interval_minutes` dan `news_interval_minutes` legacy bukan final business timing authority.

### SCHEDULER
- Laravel scheduler hanya technical poller.
- Business timing ditentukan oleh effective schedule logic.
- `scraping:run-news` tetap terdaftar.
- `scraping:run-apify` tetap terdaftar.

## Production Baseline
- Production code baseline pulled: `21723daf5e8d402bcc6d34d6ec0463838f097349`
- Scheduling migrations applied successfully:
  - `2026_08_10_000000_add_global_engine_switches_to_scraping_settings_table`
  - `2026_08_10_000001_add_schedule_run_times_overrides_to_projects_table`
  - `2026_08_11_000000_add_portal_last_scheduled_success_at_to_projects_table`
  - `2026_08_11_000001_add_is_scheduled_execution_to_apify_dispatch_states_table`
- Scheduler: running, refreshed after deployment
- Laravel workers: gracefully refreshed using `php artisan queue:restart`
- Worker refresh result: PASS
- Refreshed workers: main, Apify, notification, AI
- All production services verified UP: app, scheduler, main worker, Apify worker, notification worker, AI worker, PostgreSQL, Redis
- Post-refresh worker log check: PASS
- New scheduling/schema failures: NO
- Runtime scraping manually executed during deployment QA: NO
- Production code modified during migration/runtime execution: NO
- Unrelated server dirty file preserved: `public/._build`

## Phase Status
- Scheduling phase: CLOSED / PRODUCTION READY
- Remaining scheduling blockers: NONE

# Milestone: APIFY Slot Recovery 8C

## Apa yang Diubah
Recovery otomatis Sosial/Apify sekarang memakai latest-due-slot yang efektif, one-slot catch-up, dan cooldown retry bounded yang sudah ada, tanpa mengubah arsitektur job atau UI.

## Behavior Final
- Latest due slot bisa mundur ke hari sebelumnya sebelum slot pertama hari ini.
- Satu actor hanya mengejar latest due slot, bukan semua slot yang terlewat.
- Multiple missed slots collapse ke satu latest due slot.
- Hanya sukses scheduled MAIN Actor yang memenuhi slot; failed/queued/processing/retry_wait tetap tidak memenuhi.
- Bounded retry tetap memakai cooldown operasional yang sudah ada.

## Komponen Kunci
- `app/Console/Commands/RunApifyScraping.php`
- `tests/Feature/ApifyEffectiveScheduleRuntimeTest.php`
- `app/Jobs/ApifyScrapingJob.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: NO
- **Runtime scheduler berubah**: YES, terbatas pada bounded social recovery
- **Package / Project / Actor scheduling berubah**: NO

## Verifikasi
- `php -l app/Console/Commands/RunApifyScraping.php`: PASS
- `php -l app/Jobs/ApifyScrapingJob.php`: PASS
- `php -l app/Models/ApifyDispatchState.php`: PASS
- `php -l tests/Feature/ApifyEffectiveScheduleRuntimeTest.php`: PASS
- `php artisan route:list`: PASS
- `php artisan schedule:list`: PASS
- `php artisan view:clear`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=ApifyEffectiveScheduleRuntimeTest`: PASS pada SQLite temp test harness

## Catatan QA
- Browser QA tidak relevan.
- Runtime scraping tidak dijalankan.
- Legacy ambiguous success rows tetap tidak dipercaya untuk fulfillment; slot yang betul-betul due bisa mengeksekusi sekali lagi setelah deploy.

## Commit SHA Terkait
- `94f7498e3999f3a16d54c48a7feba0dc4f93a3a7`

# Milestone: APIFY Slot Fulfillment 8B

## Apa yang Diubah
Fulfillment slot otomatis Sosial/Apify sekarang hanya bergantung pada sukses scheduled MAIN Actor yang persisten, spesifik per `project_id` + `actor_id`, dan ditandai `is_scheduled_execution = true`.

## Behavior Final
- Hanya sukses scheduled MAIN Actor yang dapat memenuhi slot.
- `queued`, `processing`, `retry_wait`, `failed`, dan `cancelled` tidak pernah memenuhi slot.
- Comment Scraper success tidak memenuhi slot main actor.
- `--force-dispatch` success tidak memenuhi slot.
- Lookup fulfillment tidak lagi memakai `queued_at` atau `started_at`.
- Legacy success rows yang ambigu tidak dipercaya untuk fulfillment.
- `latestProjectActorRunAt()` sekarang sempit ke actor yang sama dan status success scheduled.

## Komponen Kunci
- `app/Models/ApifyDispatchState.php`
- `database/migrations/2026_08_11_000001_add_is_scheduled_execution_to_apify_dispatch_states_table.php`
- `app/Console/Commands/RunApifyScraping.php`
- `app/Jobs/ApifyScrapingJob.php`
- `tests/Feature/ApifyEffectiveScheduleRuntimeTest.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: YES
- **Route berubah**: NO
- **Runtime scheduler berubah**: YES, terbatas pada fulfillment lookup
- **Package / Project / Actor scheduling berubah**: NO

## Verifikasi
- `php -l app/Models/ApifyDispatchState.php`: PASS
- `php -l app/Console/Commands/RunApifyScraping.php`: PASS
- `php -l app/Jobs/ApifyScrapingJob.php`: PASS
- `php -l tests/Feature/ApifyEffectiveScheduleRuntimeTest.php`: PASS
- `php artisan route:list`: PASS
- `php artisan schedule:list`: PASS
- `php artisan view:clear`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=ApifyEffectiveScheduleRuntimeTest`: PASS pada SQLite temp test harness

## Catatan QA
- Browser QA tidak relevan.
- Runtime scraping tidak dijalankan.
- Setelah deploy, legacy ambiguous success rows sengaja tidak dipercaya, jadi slot yang saat ini due bisa terlihat berjalan sekali lagi.

## Commit SHA Terkait
- `88c8f834f3037590ea09257ea91947ee1a2e056c`

# Milestone: EFFECTIVE Schedule Resolver 5B Closure

## Apa yang Diubah
QA/documentation 5B ditutup dengan verifikasi tambahan untuk override Social invalid, package invalid state, dan koreksi SHA milestone yang sempat salah tercatat.

## Behavior Final
- Override kosong tetap inherit Package.
- Override malformed, duplicate, dan non-string non-empty tidak pernah inherit diam-diam.
- Package duplicate dan non-string non-empty menghasilkan `invalid_package_schedule`.
- Runtime Apify Sosial tetap aman skip untuk override invalid.
- Tidak ada perubahan arsitektur scheduling atau migrasi.

## Komponen Kunci
- `app/Services/Scraping/ProjectScheduleResolver.php`
- `tests/Feature/ProjectScheduleResolverTest.php`
- `tests/Feature/ApifyEffectiveScheduleRuntimeTest.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: NO
- **Runtime scheduler berubah**: NO
- **Package / Project / Actor scheduling berubah**: NO

## Verifikasi
- `php -l app/Services/Scraping/ProjectScheduleResolver.php`: PASS
- `php -l tests/Feature/ProjectScheduleResolverTest.php`: PASS
- `php -l tests/Feature/ApifyEffectiveScheduleRuntimeTest.php`: PASS
- `php artisan route:list`: PASS
- `php artisan schedule:list`: PASS
- `php artisan view:clear`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=ProjectScheduleResolverTest`: PASS pada SQLite temp test harness
- `php artisan test --filter=ApifyEffectiveScheduleRuntimeTest`: PASS pada SQLite temp test harness
- `php artisan test --filter=PortalEffectiveScheduleRuntimeTest`: PASS pada SQLite temp test harness
- `php artisan test --filter=PortalScheduleFulfillmentTest`: PASS pada SQLite temp test harness
- `php artisan test --filter=PortalScheduleRecoveryTest`: PASS pada SQLite temp test harness

## Catatan QA
- Browser QA tidak relevan.
- Runtime scraping tidak dijalankan.

## Commit SHA Terkait
- `f4f1fa7dd9515b723883d96f980be99be88bbd77`

# Milestone: EFFECTIVE Schedule Resolver 5B Invalid State Hotfix

## Apa yang Diubah
`ProjectScheduleResolver` sekarang membedakan override Project yang kosong penuh dari override non-empty yang invalid, dan juga membedakan schedule Package yang belum dikonfigurasi dari schedule Package yang rusak.

## Behavior Final
- Override Project kosong penuh tetap inherit Package.
- Override Project non-empty yang invalid selalu menghasilkan `invalid_project_override`.
- Package kosong/blank penuh menghasilkan `package_schedule_not_configured`.
- Package yang punya isi tapi tidak valid menghasilkan `invalid_package_schedule`.
- Valid override Project tetap menang bila jumlah dan format slot sesuai.

## Komponen Kunci
- `app/Services/Scraping/ProjectScheduleResolver.php`
- `tests/Feature/ProjectScheduleResolverTest.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: NO
- **Runtime scheduler berubah**: NO
- **Package / Project / Actor scheduling berubah**: NO

## Verifikasi
- `php -l app/Services/Scraping/ProjectScheduleResolver.php`: PASS
- `php -l tests/Feature/ProjectScheduleResolverTest.php`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=ProjectScheduleResolverTest`: PASS pada SQLite temp test harness

## Catatan QA
- Browser QA tidak relevan.
- Runtime scraping tidak dijalankan.

## Commit SHA Terkait
- `9c5b0db6b9febbc38034f63a3ff15673ba71b299`

# Milestone: APIFY Effective Schedule 8A

## Apa yang Diubah
`RunApifyScraping` untuk jalur otomatis Sosial sekarang memakai `ProjectScheduleResolver::resolveSocial()` sebagai sumber schedule efektif Project, tanpa mengubah runtime scheduler lain.

## Behavior Final
- Override Project Sosial yang valid dipakai dulu.
- Override Project Sosial yang kosong penuh tetap mewarisi jadwal Package.
- Override Project Sosial parsial atau malformed dianggap invalid dan aman di-skip.
- `scraping_settings.is_active` tetap tidak disentuh pada task ini.
- `--force-dispatch`, `--project-id`, dan perilaku comment scraper tetap dipertahankan.
- Fallback legacy ke `packages.social_interval_minutes` tidak dipakai.
- Slot fulfillment Portal, recovery, dan wiring Portal tidak berubah.
- `latestProjectActorRunAt()` belum diubah.

## Komponen Kunci
- `app/Console/Commands/RunApifyScraping.php`
- `tests/Feature/ApifyEffectiveScheduleRuntimeTest.php`
- `app/Services/Scraping/ProjectScheduleResolver.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: NO
- **Runtime scheduler berubah**: YES, hanya untuk wiring effective schedule Sosial
- **Package / Project / Actor scheduling berubah**: NO

## Verifikasi
- `php -l app/Console/Commands/RunApifyScraping.php`: PASS
- `php -l app/Services/Scraping/ProjectScheduleResolver.php`: PASS
- `php -l tests/Feature/ApifyEffectiveScheduleRuntimeTest.php`: PASS
- `php artisan route:list`: PASS
- `php artisan schedule:list`: PASS
- `php artisan view:clear`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=ApifyEffectiveScheduleRuntimeTest`: PASS pada SQLite temp test harness

## Catatan QA
- Browser QA belum diverifikasi.
- Runtime scraping tidak dijalankan.

## Commit SHA Terkait
- `3ae1394d2e12fab9494709c1f3304b286f073c44`

# Milestone: Actor Interval Removal 7B Runtime

## Apa yang Diubah
Menghapus semua ketergantungan runtime pada `ApifyActor.interval_minutes` untuk scheduling dan cooldown Apify, sambil mempertahankan jadwal harian Sosial dari Paket sebagai sumber runtime sementara.

## Behavior Final
- Interval Actor tidak lagi memengaruhi kapan scraping otomatis berjalan.
- Interval Actor tidak lagi memengaruhi retry/cooldown runtime.
- Scheduler otomatis Sosial hanya memakai jadwal harian Paket yang valid.
- Jika jadwal harian Paket tidak valid atau kosong, otomatis Sosial aman di-skip.
- Fallback ke `actor.interval_minutes` dan `package.social_interval_minutes` di runtime tidak dipakai lagi.
- Kolom DB legacy `apify_actors.interval_minutes` dan `packages.social_interval_minutes` tetap dipertahankan.
- Project Social override belum di-wiring pada task ini.
- Perilaku comment scraper tetap dipertahankan.

## Komponen Kunci
- `config/services.php`
- `app/Console/Commands/RunApifyScraping.php`
- `app/Jobs/ApifyScrapingJob.php`
- `tests/Feature/ApifyActorIntervalRuntimeRemovalTest.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: NO
- **Runtime scheduler berubah**: YES, tapi hanya untuk menghapus ketergantungan interval actor
- **Package / Project / Actor scheduling berubah**: NO

## Verifikasi
- `php -l config/services.php`: PASS
- `php -l app/Console/Commands/RunApifyScraping.php`: PASS
- `php -l app/Jobs/ApifyScrapingJob.php`: PASS
- `php -l tests/Feature/ApifyActorIntervalRuntimeRemovalTest.php`: PASS
- `php artisan route:list`: PASS
- `php artisan schedule:list`: PASS
- `php artisan view:clear`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=ApifyActorIntervalRuntimeRemovalTest`: PASS pada SQLite temp test harness

## Catatan QA
- Browser QA belum diverifikasi.
- Runtime scraping tidak dijalankan.

## Commit SHA Terkait
- `e0ce528755d4f58a2e6f7c8b5c9fdc8ad2f7f95f`

# Milestone: Actor Interval Removal 7A UI

## Apa yang Diubah
Menghapus pengaturan interval menit Actor dari surface admin Apify, sehingga Actor hanya menyimpan cara scraping berjalan dan bukan jadwal kapan dijalankan.

## Behavior Final
- Field `interval_minutes` tidak lagi tampil di form Actor admin.
- Section actor performance memakai label `Konfigurasi Performa`.
- Save/update Actor tidak lagi membaca, memvalidasi, atau mengirim `interval_minutes` dari UI.
- Kolom DB `apify_actors.interval_minutes` tetap ada sebagai legacy compatibility.
- Runtime `RunApifyScraping` belum diubah pada task ini.
- Package/Project scheduling tetap tidak berubah.

## Komponen Kunci
- `app/Livewire/Admin/ApifyConfiguration.php`
- `resources/views/livewire/admin/apify-configuration.blade.php`
- `tests/Feature/AdminApifyConfigurationTest.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: NO
- **Runtime scheduler berubah**: NO
- **Package / Project / Actor scheduling berubah**: NO

## Verifikasi
- `php -l app/Livewire/Admin/ApifyConfiguration.php`: PASS
- `php -l app/Models/ApifyActor.php`: PASS
- `php -l tests/Feature/AdminApifyConfigurationTest.php`: PASS
- `php artisan route:list`: PASS
- `php artisan view:clear`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=AdminApifyConfigurationTest`: NOT VERIFIED di default DB, FAIL di SQLite temp harness karena satu assert raw HTML terlalu sensitif, lalu test diperbaiki dan perlu rerun final

## Catatan QA
- Browser QA belum diverifikasi.
- Runtime scraping tidak dijalankan.

## Commit SHA Terkait
- pending

# Milestone: Global Scraping Switches 2B Closure

## Tambahan Milestone: PORTAL-SLOT-RECOVERY-6C

## Apa yang Diubah
Menambahkan recovery bounded untuk slot Portal otomatis yang terlewat atau gagal, dengan evaluasi latest-due-slot, one-slot catch-up, dan cooldown retry gagal per Project+slot.

## Behavior Final
- Slot due sekarang dihitung dari latest due slot yang efektif.
- Jika beberapa slot terlewat, hanya slot terbaru yang dieksekusi sekali.
- Jika slot sebelumnya sudah fulfilled, slot yang lebih lama tidak di-replay.
- Run gagal tidak menulis fulfillment dan tetap bisa dicoba lagi setelah cooldown.
- Manual `--project-id` tetap terpisah dan tidak memengaruhi fulfillment otomatis.
- Zero-new-article success semantics dari 6B tetap dipertahankan.

## Komponen Kunci
- `config/services.php`
- `app/Console/Commands/RunNewsPortalScraping.php`
- `tests/Feature/PortalScheduleRecoveryTest.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: NO
- **Runtime scheduler berubah**: YES, tetapi hanya recovery bounded portal otomatis
- **Package / Project / Actor scheduling berubah**: NO

## Verifikasi
- `php -l config/services.php`: PASS
- `php -l app/Console/Commands/RunNewsPortalScraping.php`: PASS
- `php -l tests/Feature/PortalScheduleRecoveryTest.php`: PASS
- `php artisan route:list`: PASS
- `php artisan schedule:list`: PASS
- `php artisan view:clear`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=PortalScheduleRecoveryTest`: PASS pada SQLite temp test harness
- `php artisan test --filter=PortalScheduleFulfillmentTest`: PASS pada SQLite temp test harness
- `php artisan test --filter=PortalEffectiveScheduleRuntimeTest`: PASS pada SQLite temp test harness
- `php artisan test --filter=ProjectScheduleResolverTest`: PASS pada SQLite temp test harness

## Catatan QA
- Browser QA tidak relevan.
- Runtime scraping tidak dijalankan.

## Commit SHA Terkait
- pending

## Tambahan Milestone: PORTAL-SLOT-FULFILLMENT-6B

## Apa yang Diubah
Memisahkan penanda fulfillment slot Portal otomatis dari timestamp attempt/prioritas dengan menambahkan marker persisten `portal_last_scheduled_success_at` pada Project dan memakai marker itu untuk due-check portal otomatis.

## Behavior Final
- Portal otomatis sekarang dianggap memenuhi slot hanya setelah eksekusi otomatis yang sukses.
- Manual `--project-id` tidak menulis marker fulfillment otomatis.
- Jika eksekusi otomatis gagal, marker fulfillment tidak berubah.
- Zero-new-article run tetap boleh memenuhi slot selama run operasional selesai normal.
- `ProjectScheduleResolver` tetap hanya menentukan schedule yang berlaku, bukan fulfillment.
- Apify dan scheduling sosial tidak berubah.

## Komponen Kunci
- `database/migrations/2026_08_11_000000_add_portal_last_scheduled_success_at_to_projects_table.php`
- `app/Models/Project.php`
- `app/Console/Commands/RunNewsPortalScraping.php`
- `tests/Feature/PortalScheduleFulfillmentTest.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: YES
- **Route berubah**: NO
- **Runtime scheduler berubah**: YES, tetapi hanya penentuan fulfillment portal otomatis
- **Package / Project / Actor scheduling berubah**: NO

## Verifikasi
- `php -l app/Console/Commands/RunNewsPortalScraping.php`: PASS
- `php -l app/Models/Project.php`: PASS
- `php -l database/migrations/2026_08_11_000000_add_portal_last_scheduled_success_at_to_projects_table.php`: PASS
- `php -l tests/Feature/PortalScheduleFulfillmentTest.php`: PASS
- `php artisan route:list`: PASS
- `php artisan schedule:list`: PASS
- `php artisan view:clear`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=PortalScheduleFulfillmentTest`: PASS pada SQLite temp test harness
- `php artisan test --filter=PortalEffectiveScheduleRuntimeTest`: PASS pada SQLite temp test harness
- `php artisan test --filter=ProjectScheduleResolverTest`: PASS pada SQLite temp test harness

## Catatan QA
- Browser QA tidak relevan.
- Runtime scraping tidak dijalankan.

## Commit SHA Terkait
- pending

## Tambahan Milestone: PORTAL-EFFECTIVE-SCHEDULE-6A

## Apa yang Diubah
Menghubungkan `ProjectScheduleResolver` ke `RunNewsPortalScraping` pada jalur otomatis portal, sehingga schedule efektif Project benar-benar dipakai saat menentukan kapan portal news dijalankan.

## Behavior Final
- Portal otomatis sekarang memakai schedule efektif Project dari resolver.
- Override Project yang valid menang atas schedule Package.
- Override Project tetap bisa kosong penuh dan jatuh ke Package.
- Jalur `--project-id` manual tetap mempertahankan semantik bypass timing yang lama.
- `google_news_enabled`, `manual_portal_enabled`, dan `apify_enabled` tidak diubah.

## Komponen Kunci
- `app/Console/Commands/RunNewsPortalScraping.php`
- `app/Services/Scraping/ProjectScheduleResolver.php`
- `tests/Feature/PortalEffectiveScheduleRuntimeTest.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: NO
- **Runtime scheduler berubah**: YES, hanya portal otomatis memakai resolver schedule efektif
- **Package / Project / Actor scheduling berubah**: NO

## Verifikasi
- `php -l app/Console/Commands/RunNewsPortalScraping.php`: PASS
- `php -l tests/Feature/PortalEffectiveScheduleRuntimeTest.php`: PASS
- `php artisan route:list`: PASS
- `php artisan view:clear`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=PortalEffectiveScheduleRuntimeTest`: PASS pada SQLite temp test harness

## Catatan QA
- Browser QA tidak relevan.
- Runtime scraping tidak dijalankan.

## Commit SHA Terkait
- pending

## Tambahan Milestone: EFFECTIVE-SCHEDULE-RESOLVER-5A

## Apa yang Diubah
Menambahkan shared resolver tunggal untuk menghitung schedule Portal dan Sosial yang efektif per Project, dengan precedence Project override lalu Package default.

## Behavior Final
- Resolver dapat mengembalikan schedule efektif Portal dan Sosial dalam bentuk struktur kecil dan deterministik.
- Project override yang lengkap dan valid dipakai sebagai sumber utama.
- Jika override kosong penuh, resolver jatuh ke schedule Package.
- Override parsial, duplikat, atau malformed dianggap invalid dan tidak diam-diam fallback ke Package.
- Jika Package atau fitur terkait tidak tersedia, resolver mengembalikan source `none` beserta reason yang sesuai.
- Runtime scraper belum menggunakan resolver ini pada task 5A.

## Komponen Kunci
- `app/Services/Scraping/ProjectScheduleResolver.php`
- `tests/Feature/ProjectScheduleResolverTest.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: NO
- **Runtime scheduler berubah**: NO
- **Package / Project / Actor scheduling berubah**: NO

## Verifikasi
- `php -l app/Services/Scraping/ProjectScheduleResolver.php`: PASS
- `php -l tests/Feature/ProjectScheduleResolverTest.php`: PASS
- `php artisan route:list`: PASS
- `php artisan view:clear`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=ProjectScheduleResolverTest`: PASS pada SQLite temp test harness

## Commit SHA Terkait
- `f9e4a34c4d25632eb62611f78113382deb2b2e51`

## Tambahan Milestone: PROJECT-SCHEDULE-OVERRIDE-4B-UI

## Apa yang Diubah
Menambahkan UI create/edit Project untuk override jadwal harian opsional per project, beserta validasi all-or-nothing yang mengikuti jumlah slot dari paket terpilih.

## Behavior Final
- Project Create dan Project Edit kini menampilkan referensi jadwal paket serta input override waktu Portal dan Sosial.
- Jumlah input override mengikuti `runs_per_day` paket, tanpa menambah input run-count baru di level Project.
- Jika semua slot dikosongkan, Project akan mewarisi jadwal paket.
- Jika sebagian slot diisi, save ditolak dengan pesan Indonesia yang jelas.
- Nilai valid disimpan sebagai array normalized; nilai kosong penuh disimpan sebagai `null`.
- Tidak ada perubahan pada runtime scheduling.

## Komponen Kunci
- `app/Livewire/ProjectCreate.php`
- `app/Livewire/ProjectEditModal.php`
- `resources/views/livewire/project-create.blade.php`
- `resources/views/livewire/project-edit-modal.blade.php`
- `tests/Feature/ProjectScheduleOverrideUiTest.php`

## Status Migrasi / Routing / Scraping
- **Migration berubah**: NO
- **Route berubah**: NO
- **Runtime scheduler berubah**: NO
- **Package / Project / Actor scheduling berubah**: NO

## Verifikasi
- `php -l app/Livewire/ProjectCreate.php`: PASS
- `php -l app/Livewire/ProjectEditModal.php`: PASS
- `php -l tests/Feature/ProjectScheduleOverrideUiTest.php`: PASS
- `php artisan route:list`: PASS
- `php artisan view:clear`: PASS
- `git diff --check`: PASS
- `php artisan test --filter=ProjectScheduleOverrideUiTest`: PASS pada SQLite temp test harness

## Commit SHA Terkait
- `c13f08ecd5d136b59e3e0acab9d34c21a76ce7ed`

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

---

# Milestone: PROJECT-CREATE-SCHEDULE-DISPLAY-CLEANUP

## Apa yang Diubah
- `/projects/create` yang sebelumnya masih menampilkan interval menit legacy sekarang memakai hitungan run harian.
- Tooltip opsional memakai jam yang dikonfigurasi di `news_run_times` dan `social_run_times`.

## Behavior Final
- Portal menampilkan `news_runs_per_day` sebagai `Nx / hari`.
- Sosial menampilkan `social_runs_per_day` sebagai `Nx / hari`.
- Nilai null / nol memakai wording aman `Tidak dijadwalkan`.
- Runtime scheduling tidak diubah.
- Field interval legacy tetap ada hanya untuk kompatibilitas.
- Tidak ada migration baru.

## Verifikasi
- `ProjectCreatePageTest` lulus pada SQLite temp test harness.

---

# Milestone: DATABASE-MANAGEMENT-SAFETY-LOCAL

## Apa yang Diubah
- Halaman `/admin/database` yang sebelumnya menjalankan `DROP SCHEMA public CASCADE` lalu import di proses terpisah kini memakai restore atomik dalam satu eksekusi `psql`.
- Validasi file restore kini memeriksa ekstensi `.sql`, file terbaca, tidak kosong, dan memiliki signature backup PostgreSQL plain-text.
- Restore wajib dikonfirmasi dengan teks persis `PULIHKAN DATABASE`.
- Akses admin ditegakkan langsung di komponen Livewire selain middleware route.
- Pesan error ke user dibuat singkat dan aman, tanpa raw stderr.
- Temp file backup/restore dibersihkan pada jalur sukses maupun gagal.

## Behavior Final
- Restore schema reset + import berjalan atomik dan rollback-safe.
- `CREATE SCHEMA public AUTHORIZATION CURRENT_USER` dipakai untuk recreate schema yang aman.
- Tidak ada real restore database yang dijalankan saat audit ini.
- Tidak ada migration, deploy, atau perubahan produksi.

## Verifikasi
- `AdminDatabaseManagementTest` lulus pada SQLite temp test harness.
- `php -l` pada file yang diubah lulus.
- `php artisan view:clear`, `php artisan route:list`, dan `git diff --check` lulus.
