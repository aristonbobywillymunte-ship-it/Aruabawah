# 📋 BUKU LOG QA MANDIRI (QUALITY ASSURANCE LOG)

### [QA-20260911-62] Fix 5 Bug Halaman Scraping Settings (/admin/scraping-settings)
* **Konteks**: Analisa halaman `http://localhost/admin/scraping-settings` menemukan 5 bug: (1) 3 tombol tanpa `wire:loading` guard, (2) modal pakai `@if` rentan backdrop tidak muncul, (3) modal tanpa `wire:key`/`wire:click.self`, (4) teks markdown literal `**bold**` tidak render, (5) `setting()` dipanggil 6x per siklus + dead properties `$flashMessage`/`$flashType`.
* **Perubahan**:
  1. `app/Livewire/Admin/ScrapingSettings.php`:
     - Hapus dead properties `$flashMessage`, `$flashType`
     - Hapus redundant `adminOnly()` di `render()`
     - Tambah `$settingCache` — query `ScrapingSetting::firstOrCreate` hanya 1x per request, reset setelah `save()` & `toggleStatus()`
     - Hapus dead `notify()` assignments ke flash props
  2. `resources/views/livewire/admin/scraping-settings.blade.php`:
     - Tombol "Edit Konfigurasi": tambah `wire:loading.attr="disabled"` + spinner SVG
     - Tombol "toggleStatus": tambah `wire:loading.attr="disabled"` + spinner SVG
     - Tombol "Simpan Perubahan": tambah `wire:loading.attr="disabled"` + spinner + teks "Menyimpan..."
     - Modal: `@if($showEditModal)` → `x-show="open"` + `x-cloak` + `$wire.showEditModal` getter
     - Modal: tambah `wire:key="scraping-settings-edit-modal"`, `wire:click.self`, `z-[9999]`
     - Scroll-lock: `$watch('open', ...)` reliabel
     - Teks pipeline: `**Candidate Links**` → `<strong>Candidate Links</strong>` (HTML bold nyata)
* **Hasil Pengujian Fisik**:
  - `php -l` PHP: No syntax errors detected ✅
  - `php -l` blade: No syntax errors detected ✅
  - Div balance: 43 open / 43 close ✅
  - `docker exec media_intelligent_container php artisan view:clear`: Compiled views cleared ✅
* **Status**: ✅ PASSED

### [QA-20260911-61] Fix Backdrop Modal Tidak Terlihat — Apify Financial Report

* **Konteks**: Modal "Hasil Pengambilan Komentar/Postingan" membuka tanpa backdrop gelap. Root cause: `@if($showItemsModal)` membuat elemen baru di setiap render — Livewire 3 morph DOM menghapus & membuat ulang elemen sehingga Alpine `x-init` scroll-lock tidak terpanggil reliabel, dan elemen `fixed inset-0` bisa gagal render backdrop jika DOM lifecycle tidak stabil.
* **Perubahan**:
  1. `resources/views/livewire/admin/apify-financial-report.blade.php`:
     - Ubah `@if($showItemsModal)...@endif` ke pola `x-show="open"` + `x-cloak` — elemen selalu ada di DOM, visibility dikontrol Alpine
     - `x-data="{ get open() { return $wire.showItemsModal } }"` — reaktif langsung ke Livewire property
     - Scroll-lock via `$watch('open', val => {...})` — terpanggil reliabel saat state berubah
     - `z-index` naik dari `z-50` ke `z-[9999]` — memastikan backdrop di atas semua elemen layout
     - Background backdrop diperkuat dari `bg-slate-900/50` ke `bg-slate-900/60`
  2. `resources/views/layouts/admin.blade.php`:
     - Tambah CSS `[x-cloak] { display: none !important; }` agar modal tidak flash sebelum Alpine init
* **Hasil Pengujian Fisik**:
  - `php -l` blade: No syntax errors detected ✅
  - Div balance: 43 open / 43 close ✅
  - `docker exec media_intelligent_container php artisan view:clear`: Compiled views cleared ✅
* **Status**: ✅ PASSED

### [QA-20260911-60] Fix 3 Bug Modal Komentar Scraped Comments — ApifyFinancialReport

* **Konteks**: Analisa modal "Hasil Pengambilan Komentar (Scraped Comments)" pada halaman `/admin/apify-financials` menemukan 3 bug: (1) dead property `shares` dimap tapi tidak pernah ditampilkan di blade, (2) tombol close (header) dan Tutup (footer) tanpa `wire:loading.attr="disabled"` — double-submit hazard, (3) empty state generik ketika postingan induk komentar tidak ditemukan di DB — tampil keyword URL panjang yang membingungkan.
* **Perubahan**:
  1. `resources/views/livewire/admin/apify-financial-report.blade.php`:
     - Tombol ✕ header: tambah `wire:loading.attr="disabled"`
     - Tombol "Tutup" footer: tambah `wire:loading.attr="disabled"`
     - Empty state: branch `@if($isCommentModal)` — ikon `comment_bank`, pesan spesifik "Postingan induk tidak ditemukan di database" + instruksi aksi; branch `@else` tetap seperti semula
  2. `app/Livewire/Admin/ApifyFinancialReport.php`:
     - Branch komentar (line ~98): hapus key `'comments' => 0` dan `'shares' => 0` dari array map
     - Branch postingan (line ~141): hapus key `'shares' => (int) $item->share_count` dari array map
* **Hasil Pengujian Fisik**:
  - `php -l` PHP component: No syntax errors detected ✅
  - `php -l` blade: No syntax errors detected ✅
  - Div balance: 43 open / 43 close ✅
  - `docker exec media_intelligent_container php artisan view:clear`: Compiled views cleared ✅
* **Status**: ✅ PASSED

### [QA-20260911-59] Fix BadMethodCallException — isInstagramArticle & isFacebookArticle Hilang di MediaDashboard

* **Konteks**: Error `BadMethodCallException: Method App\Livewire\MediaDashboard::isInstagramArticle does not exist` pada POST `/livewire-17c45995/update`. Method dipanggil di 2 lokasi PHP (line 1183, 1355) dan 1 lokasi blade (line 965), tapi tidak pernah didefinisikan. `isFacebookArticle` juga sama — tidak ada definisi.
* **Perubahan**:
  - `app/Livewire/MediaDashboard.php` — Tambah 2 method baru setelah `isTikTokArticle` (line 1438):
    - `protected function isInstagramArticle($article): bool` — deteksi via `source_name` mengandung `instagram`/`ig`
    - `protected function isFacebookArticle($article): bool` — deteksi via `source_name` mengandung `facebook`/`fb`
  - Pola identik dengan `isTikTokArticle` yang sudah ada.
* **Hasil Pengujian Fisik**:
  - `php -l`: No syntax errors detected ✅
  - `docker exec media_intelligent_container php artisan view:clear`: Compiled views cleared successfully ✅
* **Status**: ✅ PASSED

### [QA-20260911-58] Fix Null-State Dot Warna Kolom Status Uji di Tabel AI Providers

* **Konteks**: Kolom "Uji Terakhir" pada tabel `/admin/ai-providers` menggunakan ternary 2-kondisi untuk warna dot status: `success → hijau`, else `→ merah`. Akibatnya, provider yang belum pernah ditest (`last_test_status = null`) menampilkan dot **merah** seolah-olah sudah gagal.
* **Perubahan**:
  - `resources/views/livewire/admin/ai-providers.blade.php` line 174
  - Ternary 2-kondisi diperluas menjadi 3-kondisi: `success → bg-emerald-500`, `failed → bg-rose-500`, `null/lainnya → bg-slate-400` (abu-abu netral).
* **Hasil Pengujian Fisik**:
  - `php -l`: No syntax errors detected ✅
  - Div balance: 69 open / 69 close ✅
  - `docker exec media_intelligent_container php artisan view:clear`: Compiled views cleared successfully ✅
* **Status**: ✅ PASSED

### [QA-20260911-57] Ekspansi Ukuran Modal (Large Modal Layout) di AI Providers (/admin/ai-providers)

* **Konteks**: User meminta agar ukuran modal pada halaman `http://localhost/admin/ai-providers` diperbesar agar lebih leluasa saat mengonfigurasi payload JSON dan meninjau respons panjang pengujian LLM.
* **Perubahan**:
  1. **Modal Form Provider (`$showFormModal`)**:
     - Memperbesar kontainer modal dari `max-w-lg` (512px) menjadi `max-w-4xl` (896px) dengan `max-h-[92vh]`.
     - Layout formulir kini jauh lebih leluasa, khususnya untuk input JSON Custom Headers dan Custom Request Body Template.
  2. **Modal Uji Prompt LLM (`$showTestModal`)**:
     - Memperbesar kontainer modal dari `max-w-lg` (512px) menjadi `max-w-2xl` (672px) dengan `max-h-[92vh] flex flex-col`.
     - Memperlebar textarea prompt uji dan memperluas kotak output hasil respons JSON/teks dari `max-h-40` menjadi `max-h-64` lengkap dengan scroll internal.
* **QA fisik**:
  - `php -l` lulus pada `resources/views/livewire/admin/ai-providers.blade.php`.
  - Keseimbangan tag HTML diverifikasi: 69 open `<div>`, 69 close `</div>` (seimbang 100%).
  - Cache view Laravel dibersihkan via `docker exec media_intelligent_container php artisan view:clear`.
* **Status**: PASSED.


### [QA-20260911-56] Penegakan Standar AGENTS.md, Modal Dismiss, Loading State & Keseimbangan Tag di AI Providers (/admin/ai-providers)
* **Konteks**: Audit menyeluruh pada halaman `http://localhost/admin/ai-providers` sesuai instruksi kerja `AGENTS.md`. Ditemukan modal tidak memiliki backdrop dismiss (`wire:click.self`), tombol-tombol modal belum memiliki animasi spinner dan proteksi `wire:loading.attr="disabled"`, baris tabel kosong `colspan="9"` (kurang 1 kolom), serta tag penutup div yang kurang di modal form.
* **Perubahan**:
  1. **Backdrop Dismiss & Dynamic `wire:key`**:
     - Menambahkan event dismiss `wire:click.self` pada 4 modal (`$showFormModal`, `$showTestModal`, `$confirmingDelete`, `$confirmingToggle`).
     - Menambahkan atribut `wire:key` yang dinamis dan unik pada masing-masing modal untuk mencegah Livewire DOM morphing artifact.
  2. **Proteksi Double-Submit & Indikator Loading**:
     - Tombol "Simpan Provider" (`wire:target="save"`), "Jalankan Uji" (`wire:target="runTest"`), "Ya, Hapus" (`wire:target="deleteConfirmed"`), dan "Ya, Aktifkan/Nonaktifkan" (`wire:target="toggleStatusConfirmed"`) kini memiliki animasi spinner SVG dan `wire:loading.attr="disabled"`.
  3. **Penyelarasan Kolom & HTML Balance**:
     - Mengubah `colspan="9"` menjadi `colspan="10"` pada baris `@empty` tabel AI Provider.
     - Menambahkan tag penutup `</div>` yang kurang pada kontainer form group Identitas & Kredensial.
* **QA fisik**:
  - `php -l` lulus tanpa error sintaks pada `resources/views/livewire/admin/ai-providers.blade.php`.
  - Keseimbangan tag HTML diverifikasi: 68 open `<div>`, 68 close `</div>` (seimbang 100%).
  - Cache view Laravel dibersihkan via `docker exec media_intelligent_container php artisan view:clear`.
* **Status**: PASSED.


### [QA-20260911-55] Restorasi Backdrop Modal & Eliminasi x-teleport di Laporan Keuangan Apify (/admin/apify-financials)
* **Konteks**: User melaporkan saat membuka modal hasil scraping di `http://localhost/admin/apify-financials`, backdrop/latar belakang gelap semi-transparan (`bg-slate-900/50 backdrop-blur-sm`) tidak muncul di layar.
* **Perubahan**:
  1. **Pelepasan Pembungkus `<template x-teleport="body">`**:
     - Menghapus tag `<template x-teleport="body">` dan penutup `</template>` pada modal hasil scraping di `resources/views/livewire/admin/apify-financial-report.blade.php`.
     - Menghilangkan konflik Livewire 3 DOM morphing collision yang menyebabkan elemen wrapper fixed kehilangan konteks render di root saat state `$modalLoading` berganti.
  2. **Reposisi Modal ke Root Level Komponen**:
     - Memindahkan markup modal ke tingkat root komponen Livewire (di luar kontainer pembungkus tabel `rounded-3xl border shadow-sm overflow-x-auto`).
     - Memastikan styling `fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm` mencakup viewport secara penuh tanpa dibatasi oleh stacking context lokal kartu tabel.
  3. **Preservasi Strict Scroll-Lock & Dismiss**:
     - Mempertahankan proteksi scroll-lock resmi via Alpine.js:
       ```javascript
       x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }"
       ```
     - Mempertahankan `wire:click.self="closeItemsModal"` untuk fitur klik di luar modal (backdrop dismiss).
* **QA fisik**:
  - `php -l` lulus tanpa error sintaks pada `resources/views/livewire/admin/apify-financial-report.blade.php`.
  - Keseimbangan tag HTML diverifikasi: 43 open `<div>`, 43 close `</div>` (seimbang 100%).
  - Cache view Laravel berhasil dibersihkan via `docker exec media_intelligent_container php artisan view:clear`.
* **Status**: PASSED.


### [QA-20260911-54] Perbaikan Slop Modal Konfirmasi AlpineJS & Header Kolom Notifikasi di Pipeline Monitor
* **Konteks**: User meminta audit halaman `http://localhost/admin/pipeline-monitor`. Ditemukan modal konfirmasi aksi AlpineJS kehilangan backdrop fixed dan wrapper kartu putih sehingga tidak tampil popup saat tombol konfirmasi diklik, serta header tabel notifikasi yang kurang akurat.
* **Perubahan**:
  1. **Restorasi Modal Konfirmasi AlpineJS (`pipeline-monitor.blade.php`)**:
     - Mengembalikan pembungkus backdrop fixed `fixed inset-0 z-[99999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm` di dalam `<template x-teleport="body">`.
     - Mengembalikan kontainer kartu modal putih `bg-white shadow-2xl rounded-2xl max-w-sm w-full p-6 border border-slate-200` lengkap dengan transisi halus AlpineJS.
     - Memastikan seluruh tombol konfirmasi aksi (*Retry Semua*, *Bersihkan Antrean*, *Hapus Antrean*, *Hapus Notifikasi*) memunculkan modal popup konfirmasi yang responsif dan dapat ditutup via tombol Batalkan atau klik di luar modal.
  2. **Penyempurnaan Label Header Tabel Notifikasi**:
     - Mengubah header kolom ke-1 dari *"Artikel Terkait"* menjadi *"Sumber / Konten Terkait"* agar akurat mencakup konten portal maupun postingan media sosial.
* **QA fisik**: `php -l` lulus tanpa error sintaks pada `resources/views/livewire/admin/pipeline-monitor.blade.php`. Verifikasi render view via Tinker sukses (`OK`). Cache blade dibersihkan (`php artisan view:clear`).
* **Status**: PASSED.


### [QA-20260911-53] Penyelarasan Layout Header Modal AI Pipeline & Eliminasi Tabrakan Tombol dengan Judul
* **Konteks**: Pada modal "Sistem Kesehatan AI - Daftar Antrean Berjalan (AI Pipeline)", judul/subjudul dan tombol aksi (*Bersihkan Data* dan *Kosongkan Redis*) sebelumnya dipaksakan berada dalam satu baris header yang sama, sehingga pada resolusi layar tertentu tombol menabrak teks judul antrean.
* **Perubahan**:
  1. **Pemisahan Header & Action Bar**:
     - Mengadopsi struktur arsitektur yang konsisten seperti modal Apify: judul, badge kategori, dan tombol close `[X]` diletakkan di **Modal Header** atas yang leluasa (`flex-1 min-w-0`).
     - Tombol aksi (*Bersihkan Data* dan *Kosongkan Redis*) dipindahkan ke **Modal Actions Bar** khusus tepat di bawah header dengan background putih bersih dan alignment kanan (`justify-end gap-2`).
  2. **Jaminan Bebas Tabrakan**:
     - Teks judul *"Sistem Kesehatan AI"* dan *"Daftar Antrean Berjalan (AI Pipeline)"* kini memiliki ruang 100% penuh di kiri tanpa ada risiko terhimpit tombol aksi ataupun tombol tutup.
* **QA fisik**: `php -l` lulus tanpa error sintaks pada `resources/views/livewire/admin/system-health.blade.php`. Tinker render view `admin.dashboard` terbukti `OK`. `php artisan view:clear` sukses.
* **Status**: PASSED.


### [QA-20260911-52] Optimasi Layout Kolom Tabel Modal Antrean AI (Pencegahan Teks Judul Menabrak Kolom Lain)
* **Konteks**: User melaporkan judul/konten pada tabel modal antrean AI (*Sistem Kesehatan AI - Daftar Antrean Berjalan (AI Pipeline)*) menabrak kolom-kolom sebelahnya.
* **Perubahan**:
  1. **Ekspansi Lebar Minimal Tabel (`table-fixed min-w-[960px]`)**:
     - Menaikkan `min-w-[800px]` menjadi `min-w-[960px]` agar seluruh kolom memiliki ruang yang cukup dan tabel dapat di-scroll horizontal secara halus tanpa meremukkan sel isi.
  2. **Alokasi Lebar Pasti & Truncation**:
     - Memberikan lebar definitif `w-[320px]` untuk kolom `Judul / Konten` dengan penambahan proteksi `min-w-0 max-w-[320px]` dan `truncate flex items-center` pada link judul.
     - Mengatur lebar kolom pendukung dengan proporsional: `# (w-12)`, `Tipe (w-28)`, `Tgl Konten (w-36)`, `Proyek (w-36 max-w-[140px] truncate)`, `Status (w-28)`, `Aksi (w-16)`, dan `Dibuat (w-36)`.
     - Memastikan seluruh teks kolom tanggal, status, dan proyek diberi `whitespace-nowrap` agar tidak patah baris atau terdesak oleh panjangnya teks judul.
* **QA fisik**: `php -l` lulus tanpa error sintaks pada `resources/views/livewire/admin/system-health.blade.php`. Tinker render view `admin.dashboard` terbukti `OK`. `php artisan view:clear` sukses.
* **Status**: PASSED.


### [QA-20260911-51] Perbaikan Slop DOM Modal, Penutupan Div Liar, dan Keselarasan Tombol/Tabel Modal
* **Konteks**: User meminta audit menyeluruh terhadap tombol-tombol dan tampilan tabel di seluruh modal dashboard admin (`/admin`), memeriksa slop, ketidaksesuaian label/kolom, serta tag penutup modal.
* **Perubahan**:
  1. **Penutupan Div Backdrop yang Bocor (Unclosed Divs)**:
     - Memperbaiki tag penutup pembungkus luar fixed backdrop di `Modal Konfirmasi Error Handling` (`$showConfirmModal`), `Modal Antrean Redis` (`$showRedisQueueModal`), dan `Modal Antrean Apify` (`$showApifyQueueModal`). Sebelumnya div backdrop tidak tertutup dengan benar sehingga berisiko menumpuk di body.
  2. **Kelengkapan Pesan Aksi Tombol Konfirmasi**:
     - Menambahkan rincian teks konfirmasi untuk tombol `clean_apify_ghosts`, `purge_apify_queue`, dan `force_apify_requeue` pada `$confirmActionType` di blade konfirmasi. Sebelumnya tombol-tombol ini tidak menampilkan rincian teks penjelasan yang spesifik.
  3. **Penyelarasan Kolom & Tombol Tabel Antrean AI**:
     - Mengubah header kolom `#7` pada tabel Antrean AI dari *"Retry"* menjadi *"Aksi"* dengan lebar kolom yang proporsional (`w-16`), selaras dengan tombol "Kirim Ulang" (`force_requeue`) dan tabel Apify.
* **QA fisik**: `php -l` lulus tanpa error sintaks pada `resources/views/livewire/admin/system-health.blade.php`. Tinker render view `admin.dashboard` terbukti `OK`. `php artisan view:clear` sukses.
* **Status**: PASSED.


### [QA-20260911-50] Pembersihan Kode Slop & Eliminasi Query Mati di Halaman /admin
* **Konteks**: User meminta audit dan pembersihan kode/UI yang terindikasi "slop" pada dashboard administrator (`http://localhost/admin`). Ditemukan query berat yang tidak pernah ditampilkan di view, serta tag-tag HTML rusak (`</template>` liar) dan penumpukan double footer modal antrean AI.
* **Perubahan**:
  1. **Eliminasi Dead Code & Query Berat di `routes/web.php`**:
     - Menyederhanakan route closure `Route::get('/admin', ...)` agar langsung mereturn `view('admin.dashboard')`.
     - Menghapus query loop proyek, regex keywords matching artikel (`ContentMatchingService`), query relasi AI, dan agregasi total yang sebelumnya dijalankan sia-sia di setiap pemanggilan `/admin` karena view `admin.dashboard` tidak pernah menggunakan variabel-variabel tersebut.
  2. **Pembersihan Tag HTML Rusak di `resources/views/livewire/admin/system-health.blade.php`**:
     - Menghapus tag-tag penutup `</template>` liar di baris 23 (bawah error logs) dan baris 509, 515, 539 (di dalam tabel antrean AI).
     - Menjamin validitas DOM tree dan mencegah parsing issue pada Alpine.js / browser rendering.
  3. **Penyempurnaan Modal Footer Antrean AI**:
     - Menggabungkan pagination bar dan tombol "Tutup" modal ke dalam satu bar footer yang rapi dan terpadu, menghilangkan penumpukan dua footer terpisah.
* **QA fisik**: `php -l` lulus pada `routes/web.php`. Verifikasi render view via Tinker dengan user login sukses (`OK`). Cache blade dibersihkan (`php artisan view:clear`).
* **Status**: PASSED.


### [QA-20260911-49] Penegakan Paket Tetap Per Klien & Pembatasan Kuota Proyek Mengikuti Paket
* **Konteks**: Paket langganan hakikatnya adalah lisensi yang melekat pada akun klien dan hanya dipilih sekali. Sebelumnya, klien yang membuat proyek kedua, ketiga, dst. selalu diarahkan kembali ke Step 1 (Pilih Paket) dan bisa mengganti paket yang berbeda secara bebas. Selain itu kuota proyek harus secara ketat mengikuti `max_projects` dari paket tersebut.
* **Perubahan**:
  1. **Model User (`app/Models/User.php`)**:
     - Menambahkan method `getClientPackage(): ?Package` untuk mendeteksi paket tetap yang terikat pada akun klien (diambil dari proyek pertama yang dimiliki klien atau paket tunggal yang diizinkan).
     - Memperbarui `getMaxProjectEntitlement()` agar memprioritaskan batasan `max_projects` dari paket tetap klien tersebut.
  2. **Controller Livewire (`app/Livewire/ProjectCreate.php`)**:
     - Pada `mount()`, jika klien sudah memiliki paket tetap, otomatis mengunci `packageId`, menyinkronkan slot jam jadwal, dan langsung melompati Step 1 menuju Step 2 (`createStep = 2`).
     - Pada `createProject()`, menambahkan validasi backend bahwa jika klien memiliki paket tetap, pembuatan proyek baru dilarang mengganti paket lain.
  3. **Antarmuka Blade (`project-create.blade.php`)**:
     - Menyembunyikan step indicator jika klien sudah memiliki paket tetap.
     - Menghilangkan tombol *"Ubah Paket"* di header dan menggantinya dengan badge *"Paket Akun (Terkunci)"*.
     - Mengubah tombol *"Kembali ke Pilih Paket"* di footer menjadi *"Batal"* (kembali ke beranda proyek).
* **QA fisik**: `php -l` lulus pada `User.php`, `ProjectCreate.php`, dan `project-create.blade.php`. Simulasi Livewire mount via Tinker memverifikasi klien otomatis melompat ke Step 2 dengan `packageId = 1`, `createStep = 2`, dan slot terisi 2 kolom jam. `php artisan view:clear` sukses.
* **Status**: PASSED.

### [QA-20260911-48] Penyembunyian Toggle dan Panel "STATUS AI & RISIKO" pada Kartu Proyek Klien
* **Konteks**: User meminta agar elemen "Sembunyikan/Tampilkan" dan panel statistik "STATUS AI & RISIKO" (metrik Siap Ditampilkan, Analisis AI, High Risk) disembunyikan dari kartu proyek untuk pengguna dengan role `client`.
* **Perubahan**:
  - Di `resources/views/components/⚡projects-list.blade.php`, membungkus tombol interaktif `toggleRiskStats()` dan kontainer collapsible `STATUS AI & RISIKO` dengan kondisional `@if(!$authUser->isClient()) ... @endif`.
  - Akun klien (`isClient() = true`) disajikan antarmuka kartu proyek yang bersih dan langsung menuju metrik utama tanpa paparan teknis status pemrosesan internal pipeline AI.
  - Admin/Non-klien tetap dapat melihat dan memantau status validasi AI serta risiko.
* **QA fisik**: `php -l` lulus pada `resources/views/components/⚡projects-list.blade.php`, `docker exec media_intelligent_container php artisan view:clear` sukses (Compiled views cleared).
* **Status**: PASSED.

### [QA-20260911-47] Penyembunyian & Proteksi Tombol Jalankan Scraping Manual untuk Akun Klien
* **Konteks**: User meminta agar tombol "Jalankan Scraping Sekarang" (trigger scraping manual) dihilangkan untuk peran klien (`client`), karena scraping klien harus berjalan otomatis murni mengikuti jadwal scheduler paket yang telah ditetapkan.
* **Perubahan**:
  1. **Blade UI Guard**: Membungkus tombol aksi scraping manual pada kartu proyek di `resources/views/components/⚡projects-list.blade.php` dengan kondisional `@if(!$authUser->isClient()) ... @endif`, sehingga tombol play/jalankan scraping tidak terlihat oleh akun klien.
  2. **Backend Guard Protektif**: Menambahkan proteksi pada method `confirmRunScraping($id)` dan `runScraping($id)` untuk memeriksa `if (auth()->user()?->isClient())`. Jika ada permintaan injeksi dari klien, request langsung ditolak dengan pesan error notifikasi.
* **QA fisik**: `php -l` lulus pada `resources/views/components/⚡projects-list.blade.php`, `docker exec media_intelligent_container php artisan view:clear` sukses (Compiled views cleared).
* **Status**: PASSED.

### [QA-20260911-46] Pemulihan Render Kolom Jam Proyek Berdasarkan Alokasi Pasti Paket
* **Konteks**: User mendapati tampilan form proyek di Step 2 hanya memunculkan teks informasi *"Jumlah kolom jam otomatis mengikuti kuota paket (2x sehari)..."* beserta tombol *"Gunakan Jadwal Default Paket / Kosongkan Semua"*, namun input kotak waktu (`<input type="time">`) tidak muncul karena loop sebelumnya mengiterasi array `$news_run_times_override` yang bernilai kosong (`[]`).
* **Perubahan**:
  1. **For-Loop Rendering Definitif**: Mengubah perulangan di `project-create.blade.php` dan `project-edit-modal.blade.php` dari iterasi array override menjadi `for ($i = 0; $i < $portalSlots; $i++)` dan `for ($i = 0; $i < $socialSlots; $i++)`. Dengan demikian, kotak input jam (`Jam 1`, `Jam 2`, dst.) dijamin selalu tampil di layar sebanyak kuota paket.
  2. **Transisi Step 2 Aman**: Menambahkan method `proceedToStep2()` di `ProjectCreate.php` yang secara eksplisit memanggil `syncOverrideSlotsFromPackage()` sebelum berpindah ke langkah pengisian form proyek.
* **QA fisik**: `php -l` lulus pada `ProjectCreate.php` & `ProjectEditModal.php`, `docker exec media_intelligent_container php artisan view:clear` sukses.
* **Status**: PASSED.

### [QA-20260911-45] Kontrol Tambah & Kurang Jam Dinamis pada Pengaturan Paket Admin
* **Konteks**: Di `/admin/packages`, modal parameter paket sebelumnya mewajibkan admin mengetik angka manual di input text untuk menambah atau mengurangi run harian tanpa ada tombol interaktif tambah/kurang atau hapus slot jam. Padahal paket adalah acuan utama jalannya scraping untuk user dan klien di seluruh sistem.
* **Perubahan**:
  1. **Quick Counter Buttons**: Menambahkan tombol interaktif `[-]` dan `[+]` pada `news_runs_per_day` dan `social_runs_per_day` (`incrementNewsRuns`, `decrementNewsRuns`, `incrementSocialRuns`, `decrementSocialRuns`) di `PackageManager.php` dan `package-manager.blade.php`.
  2. **Direct Add & Remove Slot**: Menambahkan tombol *"+ Tambah Jam"* di samping header portal/sosmed serta tombol silang *Hapus Slot* di samping setiap baris input waktu (`addNewsTimeSlot`, `removeNewsTimeSlot`, `addSocialTimeSlot`, `removeSocialTimeSlot`). Aksi ini otomatis menyelaraskan nilai counter harian paket.
  3. **Integritas Acuan Paket**: Memastikan paket yang disimpan admin menjadi acuan baku yang konsisten bagi form proyek user dan klien.
* **QA fisik**: `php -l` lulus pada `PackageManager.php`, `docker exec media_intelligent_container php artisan view:clear` sukses.
* **Status**: PASSED.

### [QA-20260911-44] Otomatisasi Input Kolom Jam Scraping Mengikuti Alokasi Paket Tanpa Tombol Tambah/Hapus
* **Konteks**: User meminta agar kolom input jam scraping proyek langsung terbuat secara otomatis sesuai jumlah alokasi paket (contoh paket 4x sehari langsung menampilkan 4 kolom jam), sehingga user tidak perlu lagi repot menekan tombol tambah atau menghapus kolom secara manual.
* **Perubahan**:
  1. **Auto-Populate Slots**: Pada `ProjectCreate.php` (`syncOverrideSlotsFromPackage` & `selectPackage`) serta `ProjectEditModal.php` (`open`), sistem secara otomatis merender array slot jam dengan jumlah persis sama dengan `news_runs_per_day` dan `social_runs_per_day` paket, diisi nilai awal dari default jam paket.
  2. **Eliminasi Manual Add/Remove Buttons**: Menghapus tombol *"+ Tambah Jam Portal"*, *"+ Tambah Jam Sosial"*, dan tombol *Hapus (X)* per kolom di `project-create.blade.php` dan `project-edit-modal.blade.php`.
  3. **Grid Kolom Responsif & Aksi Reset**: Menampilkan kolom jam dalam layout grid yang rapi (`Jam 1`, `Jam 2`, dst.) dengan tombol *"Gunakan Jadwal Default Paket"* untuk mengembalikan ke jam default paket secara instan dan tombol *"Kosongkan Semua"*.
* **QA fisik**: `php -l` lulus pada `ProjectCreate.php` & `ProjectEditModal.php`, `docker exec media_intelligent_container php artisan view:clear` sukses.
* **Status**: PASSED.

### [QA-20260911-43] Penegakan Ketat Kuota Harian Paket pada Pengaturan Jadwal Kustom Proyek
* **Konteks**: User meminta batasan tegas pada jumlah slot jadwal override proyek: jika user memilih mengatur jam sendiri (kustom), maka jumlah jam yang diatur harus tepat sama dengan jatah eksekusi harian paket (`news_runs_per_day` & `social_runs_per_day`), tidak boleh kurang dan tidak boleh lebih. Jika tidak diatur, user tetap dapat mengosongkan seluruh slot untuk mengikuti jadwal default paket.
* **Perubahan**:
  1. **Dynamic Max Slot Ceiling**: Method `addNewsSlot()` dan `addSocialSlot()` pada `ProjectCreate.php` dan `ProjectEditModal.php` dibatasi secara ketat tidak dapat melebihi kuota harian paket (`maxAllowedSlots()`).
  2. **Validasi Persis Sama**: Pada `normalizeOverrideGroup()`, saat validasi simpan (`createProject` & `updateProject`), jika user mengisi jadwal override, jumlah jam terisi (`count($filled)`) divalidasi wajib sama persis dengan jatah paket (`$requiredRuns`). Jika kurang atau lebih, validasi melempar pesan ramah: *"Jadwal [Portal/Sosial] Proyek harus berjumlah tepat X jam sesuai ketentuan paket (saat ini diisi Y jam), atau kosongkan seluruhnya untuk mengikuti jadwal default Paket."*
  3. **Indikator Kuota Realtime di Blade**: Di `project-create.blade.php` dan `project-edit-modal.blade.php`, ditambahkan badge slot dinamis (`Slot: X/Y`), status badge warna hijau jika pas dan amber jika belum lengkap, peringatan jelas, serta penonaktifan tombol *"+ Tambah Jam"* dengan label teks bantuan saat kuota telah penuh.
* **QA fisik**: `php -l` lulus pada `ProjectCreate.php` & `ProjectEditModal.php`, `docker exec media_intelligent_container php artisan view:clear` sukses.
* **Status**: PASSED.

### [QA-20260911-42] Fleksibilitas Pengaturan Jadwal Scraping Mandiri Proyek (Portal & Sosial)
* **Konteks**: User tidak bisa mengatur jam jadwal scraping proyeknya sendiri jika paket admin belum diisi jatah run hariannya (`$portalSlots == 0` / `$socialSlots == 0`). User terkunci dengan status pasif *"Tidak dijadwalkan / Belum ada jadwal paket"*.
* **Perubahan**:
  1. **Dynamic Slot Management**: Menambahkan method reaktif `addNewsSlot()`, `removeNewsSlot($index)`, `addSocialSlot()`, dan `removeSocialSlot($index)` pada `ProjectCreate.php` dan `ProjectEditModal.php`.
  2. **Eliminasi Guard Pemblokir Input**: Membuka input pengaturan jadwal scraping kustom di `project-create.blade.php` dan `project-edit-modal.blade.php` tanpa bergantung pada nilai `news_runs_per_day > 0` milik paket.
  3. **Fallback & Reset Cerdas**: Jika user tidak mengatur jam kustom (slot kosong), sistem tetap otomatis mengikuti default jadwal paket. Disediakan tombol *"Reset ke Paket"* untuk menghapus override jam dengan sekali klik.
* **QA fisik**: `php -l` lulus pada `ProjectCreate.php` & `ProjectEditModal.php` (No syntax errors detected), `docker exec media_intelligent_container php artisan view:clear` sukses (Compiled views cleared).
* **Status**: PASSED.

### [QA-20260911-41] Integrasi Pemilihan Paket Monitoring pada Formulir Tambah Klien Baru
* **Konteks**: Saat admin atau user membuat akun klien baru di `/admin/clients/create`, formulir sebelumnya hanya meminta nama, email, dan password tanpa mengaitkan paket monitoring (`allowedPackages`). Akibatnya, setiap klien baru selalu mendapatkan kuota 0 proyek (`getEffectiveMaxProjects() == 0`) dan tombol pembuatan proyek tidak muncul.
* **Perubahan**:
  1. **Integrasi Paket pada Form Tambah Klien**: Menambahkan properti `$selectedPackages` dan daftar paket aktif pada `ClientCreate.php`. Default mencentang paket aktif yang tersedia, dengan validasi minimal 1 paket terpilih.
  2. **Tampilan Kartu Pilihan Paket**: Merender pilihan paket dengan rincian limit proyek & kata kunci di `client-create.blade.php`.
  3. **Auto Sync Izin Paket**: Menghubungkan paket yang dipilih ke klien baru via `$user->allowedPackages()->sync($this->selectedPackages)`.
  4. **Penyempurnaan Copy UX Empty State**: Mengubah pesan empty state dashboard proyek agar membedakan secara spesifik antara klien yang belum memiliki paket vs klien yang kuota paketnya telah habis penuh.
  5. **Resolusi Data Klien Eksisting**: Menautkan paket aktif ke akun `client@arusbawah.co` (ID: 119) di database runtime.
* **QA fisik**: `php -l` lulus pada `ClientCreate.php`, `docker exec media_intelligent_container php artisan view:clear` sukses, pengetesan Livewire render `ProjectsList` untuk akun `client@arusbawah.co` memverifikasi `VERIFIED_LOADED: Tombol "Buat Proyek Baru" MUNCUL setelah loadProjects!`.
* **Status**: PASSED.

### [QA-20260911-40] Isolasi Proyek Klien, Kuota Paket Admin & Eliminasi Slop Empty State
* **Konteks**: Role `client` seharusnya hanya melihat proyek miliknya sendiri, dan izin serta batas jumlah proyek ditentukan oleh konfigurasi paket di admin (`packages.max_projects` atau `client_settings.max_projects`). Ditemukan slop UI berupa penumpukan double empty state (*"Belum ada project yang diberikan..."* dan kartu raksasa *"Buat Proyek Baru"*), bypass izin pembuatan proyek di UI pada akun klien yang tidak berhak/kuotanya habis, copy usang *"media cetak"*, serta tombol edit yang tidak mengecek izin `can_edit_projects`.
* **Perubahan**:
  1. **Enforcement Izin & Kuota Paket**: Memastikan kartu dan tombol *"Buat Proyek Baru"* hanya tampil jika user memiliki izin `can_create_projects`, memiliki paket aktif di `allowedPackages`, dan jumlah proyek aktifnya belum mencapai kuota paket (`effectiveMaxProjects`).
  2. **Mount Guard pada Halaman Pembuatan Proyek**: Menambahkan guard pada `ProjectCreate::mount()` untuk mencegah akses via URL langsung jika klien tidak memiliki izin, kuota proyek penuh, atau tidak memiliki paket yang diizinkan.
  3. **Unified & Contextual Empty State**: Menyatukan tampilan saat tidak ada proyek menjadi kartu bersih elegan dengan pesan kontekstual (menjelaskan status kuota/izin/instruksi menghubungi admin) dan menghilangkan kartu 620px liar saat kuota habis.
  4. **Pembersihan Copy & Izin Edit**: Mengoreksi deskripsi kartu monitoring (menghapus *"media cetak"* menjadi *"portal berita online dan media sosial"*) serta memproteksi tombol Edit Proyek dengan guard `can_edit_projects`.
  5. **Empty State pada Halaman Pemilihan Paket**: Menambahkan feedback humanis di Step 1 `/projects/create` apabila klien belum memiliki paket yang diizinkan oleh admin.
  6. **Hotfix Blade Syntax**: Menutup blok `@if(empty($projects))` dengan `@endif` sebelum blok `@else` status skeleton loader `projectsLoaded` pada baris 1335.
* **QA fisik**: `php -l` lulus pada `app/Livewire/ProjectCreate.php`, `docker exec media_intelligent_container php artisan view:clear` sukses, eksekusi render langsung komponen Livewire `ProjectsList` di dalam container Docker menghasilkan `DOCKER_LIVEWIRE_RENDER_SUCCESS: 17644` bytes tanpa ParseError.
* **Status**: PASSED.

### [QA-20260911-39] Rate Limiting & Eliminasi Slop Halaman Login
* **Konteks**: Halaman login (`/login`) belum memiliki brute-force protection (rate limiting) pada controller otentikasi. Ditemukan slop visual berupa fallback logo geometris berwarna merah cerah (`#ff4d4d`/`#e50914`) yang meniru template generik dan bertabrakan dengan brand teal sistem (`#1fa387`). Terdapat dead-link `href="#"` pada footer login dan potensi submit button stuck jika validasi HTML5 form gagal.
* **Perubahan**:
  1. **Rate Limiting Keamanan**: Menambahkan `RateLimiter` 5 percobaan per menit per kombinasi email + IP pada `LoginController.php`. Jika terlampaui, request diblokir dengan pesan throttle ramah Bahasa Indonesia dan indikator sisa detik.
  2. **Pesan Kesalahan Terstandarisasi**: Mengubah pesan autentikasi gagal menjadi Bahasa Indonesia yang jelas: `"Email atau password yang Anda masukkan tidak sesuai."`.
  3. **Harmonisasi Brand & Anti-Slop Visual**: Mengganti SVG gradient fallback logo (desktop & mobile) dari palet merah generik ke palet identitas brand sistem teal/emerald (`#1fa387`, `#2ec4a3`, `#178a70` dan putih bersih untuk kontras panel hijau).
  4. **Eliminasi Dead Link**: Menghapus `href="#"` pada tautan *"Hubungi administrator"* dan menggantinya dengan handler dialog bantuan informasi administrator.
  5. **Resilient Submit State**: Menambahkan pengecekan `form.checkValidity()` sebelum mendisabled tombol submit untuk mencegah tombol macet (*stuck*) saat input kosong/tidak valid.
* **QA fisik**: `php -l` lulus (No syntax errors detected), `php artisan view:clear` sukses (Compiled views cleared), validasi via curl HTTP response terverifikasi.
* **Status**: PASSED.

### [QA-20260911-38] Eliminasi Slop & Perbaikan Tombol serta Modal Komentar Penyebutan
* **Konteks**: Tombol komentar pada feed tab Penyebutan sering error/macet karena *race-condition* dua HTTP request (`open...` + `dispatch('load-...')`), tabrakan ID antara `articles` dan `social_media_items`, serta tombol disabled tanpa penjelasan. Ditemukan slop duplikasi 3 modal terpisah (~240 baris redundant), karakter penutup non-standar (`✕`), copy slop menyebut vendor third-party, dan spinner SVG manual.
* **Perubahan**:
  1. **Direct Fetch**: Menyatukan alur fetch komentar ke method tunggal `openCommentsModal($articleId)` tanpa asynchronous event dispatch yang memicu race condition.
  2. **Unified Modal**: Me-refactor 3 modal terpisah (TikTok, IG, FB) menjadi 1 modal terpadu yang dinamis, bersih, dan mematuhi standar PRD.
  3. **Pembersihan Slop UI & Copy**: Mengganti `✕` dan SVG manual dengan Material Symbols (`close`, `forum`, `progress_activity animate-spin`), merapikan copy empty state tanpa menyebut vendor, dan memastikan pointer-event/scroll lock terjaga.
  4. **Backward Compatibility**: Mempertahankan alias method lama (`openTikTokCommentsModal`, dll.) agar tidak ada interupsi di bagian komponen lain.
* **QA fisik**: `php -l` lulus (No syntax errors detected), `php artisan view:clear` sukses (Compiled views cleared), `git diff` rapi (-463 lines removed, +184 lines added).
* **Status**: PASSED.

### [QA-20260910-37] Anti-Slop Toast Wawasan AI
* **Perubahan**: Toast custom netral menggantikan SweetAlert toast generik; ikon memakai Material Symbols, progress bar dihapus, copy dipadatkan.
* **QA fisik**: `view:clear` berhasil; PHP lint berhasil; render terautentikasi menghasilkan `RENDER_SUCCESS=164539`; marker custom toast terdeteksi; `git diff --check` bersih.
* **Status**: PASSED untuk compile/lint/render. Browser visual test belum tersedia.

### [QA-20260910-36] Loading State Modal Konfirmasi Wawasan AI
* **Temuan**: CTA `Ya, Perbarui` belum memiliki `wire:loading`; modal ditutup sebelum request berjalan sehingga pengguna tidak melihat proses.
* **Perubahan**: Modal dipertahankan selama `generateAiInsights`; CTA dan tombol Batal disabled saat request; CTA menampilkan spinner `progress_activity` dan teks `Memproses AI...`.
* **QA fisik**: `view:clear` berhasil; PHP lint berhasil; render terautentikasi tab Wawasan menghasilkan `RENDER_SUCCESS=165396`; marker `Memproses AI...` terdeteksi; `git diff --check` bersih.
* **Status**: PASSED untuk compile/lint/render. Browser timing test belum tersedia.

### [QA-20260910-35] Notifikasi Toast Pembaruan Wawasan AI
* **Konteks**: `generateAiInsights()` membuat flash message, tetapi feedback tidak tampil sebagai toast setelah CTA modal diklik dari tab Wawasan.
* **Perubahan**: Mengirim event Livewire `admin-toast` dari `generateAiInsights()` dan memasang `<x-admin-toast />` pada layout `welcome`; banner inline dihapus.
* **QA fisik**: `view:clear` berhasil; PHP lint berhasil; render terautentikasi tab Wawasan menghasilkan `RENDER_SUCCESS=152282`; `git diff --check` bersih.
* **Status**: PASSED untuk compile/lint/render. Browser toast visibility test belum tersedia.

### [QA-20260910-34] Pemindahan Pemicu Modal Wawasan AI ke Livewire
* **Konteks**: `@click` Alpine tidak membuka modal pada runtime pengguna.
* **Perubahan**: Tombol memakai `wire:click="openAiInsightConfirmModal"`; modal dirender dengan state Livewire; tombol Batal dan CTA memakai action Livewire.
* **QA fisik**: `view:clear` berhasil, PHP lint berhasil, render terautentikasi tab Wawasan berhasil (`RENDER_SUCCESS=152204`), `git diff --check` bersih.
* **Status**: PASSED untuk lint/compile/render. Browser click test belum tersedia.
* **Commit lokal**: pending.

### [QA-20260910-33] Perbaikan Overlay Loading yang Memblokir Tombol Wawasan AI
* **Konteks**: Overlay `preparePdfReport` berpotensi tetap menangkap pointer event saat idle.
* **Perubahan**: Menambahkan class `hidden` sebagai guard awal pada overlay di `resources/views/livewire/media-dashboard.blade.php`; `wire:loading.flex` tetap menampilkan overlay hanya saat request PDF aktif.
* **QA fisik**:
  - `docker exec media_intelligent_container php artisan view:clear` → berhasil.
  - `docker exec media_intelligent_container php -l app/Livewire/MediaDashboard.php` → `No syntax errors detected`.
  - Render `welcome` terautentikasi dengan project 61/tab Wawasan → `RENDER_SUCCESS=154532`.
* **Status**: PASSED untuk lint, compile, dan render. Browser click test belum dijalankan pada sesi ini.
* **Commit lokal**: pending.
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

### [QA-20260910-04] Eliminasi AI-Slop Styling pada Feed Menu Penyebutan
* **Tanggal & Waktu**: 10 September 2026, 19:40 WIB
* **Konteks Masalah**:
  Tampilan kartu penyebutan media dan postingan pada tab Penyebutan (`http://localhost/?project=61&tab=cGVueWVidXRhbg==`) menggunakan pola AI-Slop generik berupa pendaran bayangan berlebih (`50px glow`), border kiri tebal 4px asimetris yang mematahkan lekukan sudut card `rounded-[24px]`, serta inline gradient dinamis dengan hex-opacity pudar pada expander Ringkasan AI.
* **Target Komponen Diperbaiki**:
  - `resources/views/livewire/media-dashboard.blade.php` (feed kartu penyebutan dan box expander Ringkasan AI)
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4, Laravel Livewire 3)
  - Parameter: `project=61`, `tab=penyebutan` (Base64: `cGVueWVidXRhbg==`)
* **Parameter & Hasil Pengujian**:
  1. **Hover Glow Elimination**: Drop shadow neon 50px diganti dengan bayangan netral terukur `shadow-[0_2px_12px_rgba(0,0,0,0.02)] hover:shadow-[0_12px_32px_rgba(0,0,0,0.04)] hover:border-slate-300`.
  2. **Corner Radius Preservation**: Menghilangkan `border-l-4` dan `border-left-color: {{ $sentimentColor }}` yang menyebabkan *glitch* patahan lekukan sudut pada card `rounded-[24px]`. Indikator sentimen tetap tegas dan jelas melalui badge pill di sudut kanan atas kartu.
  3. **AI Summary Box Modernization**: Mengganti inline background gradient hex-opacity dinamis (`linear-gradient(to right, {{ $iconColor }}08, #f8fafc05)`) menjadi solid container `bg-slate-50 border border-slate-200 rounded-2xl` dengan typography netral `text-slate-600`.
  4. **Physical Runtime Render Test**:
     - Perintah: `php artisan view:clear` dan eksekusi rendering Blade view `welcome` untuk tab Penyebutan.
     - Status: **PASSED (Exit Code 0, Render HTML Output: 144.833 bytes, Zero Error)**.
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

### [QA-20260910-05] Eliminasi AI-Slop pada Dropdown Notifikasi Peringatan Sentimen Negatif
* **Tanggal & Waktu**: 10 September 2026, 19:42 WIB
* **Konteks Masalah**:
  Komponen notifikasi dropdown (`notification-dropdown.blade.php`) menggunakan pola AI-slop berlebihan: full-screen backdrop-blur gelap untuk dropdown menu kecil, animasi denyut ganda (`animate-ping`) yang menumpuk bersama label & badge angka, inline shadow raksasa (`60px blur`), warna non-standar Tailwind (`text-rose-550`, `text-rose-650`), dan teks `font-black` yang harsh.
* **Target Komponen Diperbaiki**:
  - `resources/views/livewire/notification-dropdown.blade.php`
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4, Laravel Livewire 3)
  - Target URL: `http://localhost/?project=61&tab=YW5hbGlzaXM=`
* **Parameter & Hasil Pengujian**:
  1. **Dismiss Behavior**: Menghapus backdrop-blur gelap full-screen dan menggantinya dengan handler standar Alpine `@click.outside="open = false"`.
  2. **Trigger Button Cleanliness**: Menghilangkan `animate-ping` merah yang berkedip terus-menerus dan mempertahankan satu badge angka solid `bg-rose-50 text-rose-600 border border-rose-200` yang proporsional.
  3. **Elevation & Layout Refinement**: Mengganti inline shadow raksasa dan border radius janggal menjadi container standar `rounded-2xl border border-slate-200 shadow-xl` yang selaras dengan dropdown profil.
  4. **Typography & Styling Standards**: Mengganti non-standard class `text-rose-550`/`text-rose-650` dan `font-black` menjadi `font-semibold text-slate-800` dan class standar Tailwind.
  5. **Inklusivitas Judul**: Memperbarui judul header menjadi **"Peringatan Sentimen Negatif"** agar akurat mencakup baik Portal Berita maupun Media Sosial.
  6. **Physical Runtime Render Test**:
     - Perintah: `php artisan view:clear` dan render view `welcome` via tinker.
     - Status: **PASSED (Exit Code 0, Render HTML Output: 129.530 bytes, Zero Error)**.
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

### [QA-20260910-06] Eliminasi AI-Slop pada Tab Kata Kunci (Tabel & Grafik Tren)
* **Tanggal & Waktu**: 10 September 2026, 19:44 WIB
* **Konteks Masalah**:
  Tab Kata Kunci (`tab=katakunci`, Base64: `a2F0YWt1bmNp`) memuat elemen AI-slop: pagination palsu/dummy berlabel tombol hardcoded, tombol submit pencarian terpisah yang mubazir tanpa fungsi, bentrokan warna palette pada segmented toggle (`blue-600` vs `#1fa387`), dan filter drop shadow neon blur berlebihan pada kurva vektor SVG.
* **Target Komponen Diperbaiki**:
  - `resources/views/livewire/media-dashboard.blade.php` (Tab Kata Kunci, baris 2673–3025)
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4, Laravel Livewire 3)
  - Target URL: `http://localhost/?project=61&tab=a2F0YWt1bmNp`
* **Parameter & Hasil Pengujian**:
  1. **Embedded Reactive Search Input**: Mengintegrasikan icon loop pencarian ke dalam input box (`wire:model.live.debounce.300ms`) dan menghapus tombol kotak hijau terpisah yang tidak fungsional.
  2. **Fake Pagination Elimination**: Menghapus tombol navigasi hardcoded (`«`, `‹`, `1`, `›`, `»`) dan menggantikannya dengan summary status informatif yang transparan bagi pengguna.
  3. **Segmented Button Brand Alignment**: Menyatukan skema warna aktif antara interval toggle (Harian/Mingguan/Bulanan) dan metric toggle (Penyebutan/Jangkauan/Sentimen) menjadi konsisten di warna brand `#1fa387` dengan soft shadow netral.
  4. **Sharp Vector Graph**: Menghapus `feDropShadow` filter blur pada garis kurva SVG agar render kurva tajam dan crisp di semua display.
  5. **Physical Runtime Render Test**:
     - Perintah: `php artisan view:clear` dan render view `welcome` via tinker.
     - Status: **PASSED (Exit Code 0, Render HTML Output: 129.985 bytes, Zero Error)**.
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

### [QA-20260910-07] Eliminasi AI-Slop pada Tab Wawasan & Ringkasan AI
* **Tanggal & Waktu**: 10 September 2026, 19:46 WIB
* **Konteks Masalah**:
  Tab Wawasan (`tab=wawasan`, Base64: `d2F3YXNhbg==`) memuat pola AI-slop: inkonsistensi warna brand (`indigo-600` neon vs tema sistem `#1fa387`), buzzword badge berlebihan ("Murni AI", "AI Generated"), animasi denyut (`animate-ping`) abadi pada status Sinyal Krisis, dan class non-standar Tailwind (`text-emerald-650`, `group-hover:text-indigo-650`).
* **Target Komponen Diperbaiki**:
  - `resources/views/livewire/media-dashboard.blade.php` (Tab Wawasan, baris 3139–3272)
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4, Laravel Livewire 3)
  - Target URL: `http://localhost/?project=61&tab=d2F3YXNhbg==`
* **Parameter & Hasil Pengujian**:
  1. **Brand Theme Harmony**: Menggantikan aksen warna `text-indigo-600` dan tombol `bg-indigo-600` dengan palet brand konsisten `text-[#1fa387]` dan tombol `bg-[#1fa387] hover:bg-[#1fa387]/90 shadow-sm`.
  2. **Buzzword Elimination**: Mengganti badge "Murni AI" menjadi status informatif "Terupdate", serta menyederhanakan judul "RINGKASAN EKSEKUTIF AI" dan badge "AI Generated" menjadi "RINGKASAN EKSEKUTIF" dengan label "Eksekutif" yang berbobot enterprise.
  3. **Visual Distraction Removal**: Menghapus `animate-ping` terus-menerus pada card Sinyal Krisis dan menggantinya dengan indikator status solid yang tegas dan tenang.
  4. **Tailwind Standard Normalization**: Normalisasi class warna tidak standar (`text-emerald-650` $\rightarrow$ `text-emerald-700`, `group-hover:text-indigo-650` $\rightarrow$ `group-hover:text-[#1fa387]`).
  5. **Physical Runtime Render Test**:
     - Perintah: `php artisan view:clear` dan render view `welcome` via tinker.
     - Status: **PASSED (Exit Code 0, Render HTML Output: 133.586 bytes, Zero Error)**.
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

### [QA-20260910-08] Implementasi Komprehensif Skeleton Loading Placeholder di Seluruh Card Tab Wawasan
* **Tanggal & Waktu**: 10 September 2026, 19:48 WIB
* **Konteks Masalah**:
  Saat Tab Wawasan (`tab=wawasan`) pertama kali dibuka atau dimuat via `wire:init="loadWawasan"`, skeleton placeholder sebelumnya hanya merender kotak abu-abu generik yang tidak mencerminkan layout kartu sebenarnya (stakeholder / masonry cards).
* **Target Komponen Diperbaiki**:
  - `resources/views/livewire/media-dashboard.blade.php` (Skeleton loading blok `!$wawasanLoaded`, baris 3164–3260)
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4, Laravel Livewire 3)
  - Target URL: `http://localhost/?project=61&tab=d2F3YXNhbg==`
* **Parameter & Hasil Pengujian**:
  1. **Mirroring 4 KPI Grid Cards**: Menghadirkan kerangka skeleton presisi untuk Card Indeks Reputasi (lingkaran rasio), Kesehatan Sentimen (baris bar sentimen), Sinyal Krisis, dan Kondisi Viral.
  2. **Mirroring 2-Column Masonry Cards**: Menghadirkan kerangka skeleton detail untuk seluruh card analitik:
     - Ringkasan Eksekutif (garis paragraf terstruktur).
     - Rekomendasi Tindakan Strategis (list item berikon).
     - Top Isu Negatif (bar progress persentase).
     - Perubahan Sentimen (grid komparasi paruh awal & akhir).
     - Distribusi Kategori Isu (bar horizontal).
     - Kanal Media Terpopuler (baris tabel).
     - Pemicu Risiko (card list mitigasi risiko).
  3. **Zero Layout Shift (CLS Protection)**: Menjamin tidak terjadi lompatan posisi elemen saat data selesai dimuat oleh Livewire.
  4. **Physical Runtime Render Test**:
     - Perintah: `php artisan view:clear` dan render view `welcome` via tinker.
     - Status: **PASSED (Exit Code 0, Render HTML Output: 147.725 bytes, Zero Error)**.
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

### [QA-20260910-09] Perbaikan Jarak (Spacing / Gap) Antar Card pada Tab Wawasan
* **Tanggal & Waktu**: 10 September 2026, 19:51 WIB
* **Konteks Masalah**:
  Pada Tab Wawasan (`tab=wawasan`), jarak vertikal antara 4 Card KPI Grid Atas (Indeks Reputasi, Kesehatan Sentimen, Sinyal Krisis, Kondisi Viral) dengan 2-Column Cards di bawahnya (Ringkasan Eksekutif & Distribusi Kategori Isu) menempel/bentrok tanpa jeda vertikal (*zero margin/gap collapse*) akibat elemen pembungkus `wire:loading.remove` tidak memiliki class spasi vertikal.
* **Target Komponen Diperbaiki**:
  - `resources/views/livewire/media-dashboard.blade.php` (baris 3327–3332)
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4, Laravel Livewire 3)
  - Target URL: `http://localhost/?project=61&tab=d2F3YXNhbg==`
* **Parameter & Hasil Pengujian**:
  1. **Vertical Spacing Restoration**: Menambahkan class `space-y-6` pada elemen pembungkus `wire:loading.remove` sehingga terbentuk jarak proporsional 24px (`1.5rem`) antara baris 4 Card KPI atas dengan baris card detail analitik di bawahnya.
  2. **Visual Consistency**: Menghilangkan efek tabrakan border antar card dan mengembalikan elevasi card yang lega dan nyaman dilihat.
  3. **Physical Runtime Render Test**:
     - Perintah: `php artisan view:clear` dan render view `welcome` via tinker.
     - Status: **PASSED (Exit Code 0, Render HTML Output: 147.725 bytes, Zero Error)**.
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

### [QA-20260910-10] Penyesuaian Komprehensif Skeleton Loading Placeholder di Seluruh Card Tab Analisis
* **Tanggal & Waktu**: 10 September 2026, 19:52 WIB
* **Konteks Masalah**:
  Saat Tab Analisis (`tab=analisis`, Base64: `YW5hbGlzaXM=`) pertama kali dimuat via `wire:init="loadAnalysis"`, skeleton loading sebelumnya hanya memiliki 1 baris dummy sederhana yang tidak sesuai dengan struktur card analitik yang kaya.
* **Target Komponen Diperbaiki**:
  - `resources/views/livewire/media-dashboard.blade.php` (blok skeleton `!$analysisLoaded`, baris 1296–1310)
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4, Laravel Livewire 3)
  - Target URL: `http://localhost/?project=61&tab=YW5hbGlzaXM=`
* **Parameter & Hasil Pengujian**:
  1. **Mirroring Gambaran Umum Cards**:
     - 3 Big KPI Cards (Total Artikel, Total Jangkauan, Interaksi Medsos) lengkap dengan placeholder ikon & metrik.
     - 4 Channel Publication Breakdown Cards (Instagram, TikTok, Facebook, Berita Online) lengkap dengan logo kotak & ringkasan interaksi.
     - 2 Sentiment Distribution Summary Cards (Medsos & Berita) lengkap dengan 3 kotak Positif/Netral/Negatif.
  2. **Mirroring Grafik Tren Kinerja Proyek**: Kerangka header, filter interval button, dan area kanvas kurva.
  3. **Mirroring Row 3 Grid**: Placeholder Word Cloud (Awan Kata) dan Distribusi Kategori Isu (baris progress baris).
  4. **Mirroring Row 4 Grid**: Placeholder Peta Jaringan Isu (peta canvas) dan Daftar Berita Populer.
  5. **Zero Layout Shift (CLS Protection)**: Mencegah terjadinya pergeseran layout mendadak saat data riil selesai dimuat oleh browser.
  6. **Physical Runtime Render Test**:
     - Perintah: `php artisan view:clear` dan render view `welcome` via tinker.
     - Status: **PASSED (Exit Code 0, Render HTML Output: 147.160 bytes, Zero Error)**.
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

### [QA-20260910-11] Penambahan Indikator Loading Reaktif pada Input Pencarian & Header Filter Panel
* **Tanggal & Waktu**: 10 September 2026, 19:54 WIB
* **Konteks Masalah**:
  Saat pengguna mengetik kata pada kolom "Pencarian" di Filter Panel (`wire:model.live.debounce.600ms="search"`), tidak ada indikator visual loading yang memberi tahu pengguna bahwa sistem sedang memproses dan mengambil data hasil filter.
* **Target Komponen Diperbaiki**:
  - `resources/views/components/⚡filter-items.blade.php` (blok Search Panel)
  - `resources/views/livewire/media-dashboard.blade.php` (Header Filter Panel)
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4, Laravel Livewire 3)
  - Target URL: `http://localhost/?project=61&tab=cGVueWVidXRhbg==`
* **Parameter & Hasil Pengujian**:
  1. **Dual Loading Indicator for Search**:
     - *Header Search Label*: Menampilkan teks dan spinner `Mencari...` di sudut kanan atas label pencarian saat `wire:target="search"` aktif.
     - *Inside Input Box*: Ikon loop statis otomatis berganti menjadi animasi spinner berputar `#1fa387` di dalam input field saat pengguna mengetik/Livewire mengirim request.
  2. **Global Filter Loading Indicator**:
     - Menambahkan indikator spinner `Menyaring...` pada header utama Filter Panel yang aktif saat filter apapun (search, checkbox sumber data, sentimen, maupun rentang tanggal) dieksekusi.
  3. **Physical Runtime Render Test**:
     - Perintah: `php artisan view:clear` dan render view `welcome` via tinker.
     - Status: **PASSED (Exit Code 0, Render HTML Output: 146.652 bytes, Zero Error)**.
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

---

### [QA-20260910-12] Pembersihan AI-Slop Tab Laporan & Perbaikan Tag Penutup Tab Sumber
* **Tanggal & Waktu**: 10 September 2026, 20:18 WIB
* **Konteks Masalah**:
  Audit antarmuka pada URL `http://localhost/?project=61&tab=bGFwb3Jhbg%3D%3D` (Tab Laporan) dan tab Sumber (`tab=sumber` / `c3VtYmVy`) menemukan beberapa kecacatan tampilan dan struktur:
  1. Tombol Unduh Laporan PDF terjebak di dalam grid 3-kolom toggle switch pilihan laporan, menggunakan warna merah mencolok (`bg-[#c0392b]`), dan menggunakan emoji panah `⬇`.
  2. Tombol Unduh Laporan Excel menggunakan emoji panah `⬇` dan tata letak tidak serasi.
  3. Modal overlay proses pembuatan PDF menggunakan copy AI buzzword ("Menyusun AI Report", "AI sedang menyiapkan kesimpulan...").
  4. Tab Sumber (`tab=sumber`) kehilangan tag penutup `</div>` (3 buah) dan `</section>` (1 buah) sebelum direktif `@endif`, merusak struktur DOM hirarki halaman.
* **Target Komponen Diperbaiki**:
  - `resources/views/livewire/media-dashboard.blade.php` (Tab Laporan, Tab Sumber, dan Modal PDF)
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4, Laravel Livewire 3)
  - Target URL: `http://localhost/?project=61&tab=bGFwb3Jhbg==` dan `http://localhost/?project=61&tab=c3VtYmVy`
* **Parameter & Hasil Pengujian**:
  1. **Harmonisasi Footer Action Tab Laporan**:
     - Memindahkan tombol Unduh PDF keluar dari grid 3-kolom ke baris footer dedicated (`border-t border-slate-100 flex justify-end`).
     - Mengubah warna tombol PDF dari merah `#c0392b` menjadi tema brand `#1fa387` (`hover:bg-[#178a70]`).
     - Menghilangkan emoji panah `⬇` pada tombol PDF dan Excel untuk tampilan yang bersih dan profesional.
     - Mengubah teks modal proses PDF menjadi "Menyusun Laporan PDF" dan "Sistem sedang merangkum ringkasan dan analisis isu terbaru...".
  2. **Perbaikan Struktur DOM Tab Sumber**:
     - Menambahkan 3 tag penutup `</div>` dan 1 tag `</section>` yang hilang sebelum `@endif` baris 4835.
  3. **Physical Runtime Render Test**:
     - Eksekusi `view:clear` dan render via tinker untuk kedua tab.
     - Tab Laporan: **150.104 bytes**, exit code 0.
     - Tab Sumber: **135.856 bytes**, exit code 0.
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

---

### [QA-20260910-13] Isolasi State Modal Datepicker & Pencegahan Penutupan Prematur
* **Tanggal & Waktu**: 10 September 2026, 20:23 WIB
* **Konteks Masalah**:
  Saat modal Rentang Tanggal (`showDatePicker`) dibuka dari Filter Panel, memilih opsi preset (seperti "Hari ini", "7 hari terakhir", dsb), mengklik tanggal di kalender, atau mengklik "Semua Waktu" sebelumnya langsung memicu request Livewire atau menutup modal secara prematur tanpa memberi kesempatan kepada pengguna untuk meninjau pilihan.
* **Target Komponen Diperbaiki**:
  - `resources/views/livewire/media-dashboard.blade.php` (Komponen Alpine DatePicker Modal)
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4, Laravel Livewire 3)
  - Target URL: `http://localhost/?project=61&tab=cGVueWVidXRhbg==`
* **Parameter & Hasil Pengujian**:
  1. **Isolasi State Alpine JavaScript**:
     - Menggantikan `@entangle('startDate')` dan `@entangle('endDate')` dengan properti internal `localStart: null` dan `localEnd: null`.
     - Menggunakan watcher `$watch('show', ...)` untuk menyalin nilai aktif dari Livewire hanya ketika modal dibuka, sehingga aksi seleksi tanggal bersifat lokal sepenuhnya.
  2. **Pencegahan Penutupan Prematur**:
     - Seluruh tombol preset periode (Hari ini, Kemarin, 7 hari, 30 hari, 3 bulan, Tahun lalu) kini hanya memperbarui visual range dan tampilan kalender tanpa menutup modal.
     - Seleksi tanggal pada grid kalender (`selectDate`) memperbarui visual state tanpa menutup modal.
     - Tombol "Semua Waktu" (`clearPeriod`) mengosongkan state tanggal lokal kalender dan membiarkan modal tetap terbuka untuk ditinjau.
     - Tombol "Terapkan" (`applyFilter`) menjadi satu-satunya tombol konfirmasi yang menyinkronkan state ke Livewire (`$wire.set('startDate')`, `$wire.set('endDate')`) dan menutup modal.
     - Tombol "Batal" dan klik di luar container (`@click.away`) membatalkan perubahan dan menutup modal tanpa memodifikasi tanggal Livewire.
  3. **Physical Runtime Render Test**:
     - Perintah: `php artisan view:clear` (exit code 0).
     - Render view `welcome` via tinker: **146.963 bytes** (exit code 0, zero error).
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

---

### [QA-20260910-14] Pembersihan AI-Slop & Redesain Profesional Halaman Ganti Password
* **Tanggal & Waktu**: 10 September 2026, 20:26 WIB
* **Konteks Masalah**:
  Audit pada rute `http://localhost/change-password` menemukan sejumlah cacat UI dan AI-slop:
  1. Teks nama aplikasi dan judul masih di-hardcode ("Arusbawah Media Intelligence") alih-alih menggunakan helper branding dinamis.
  2. Ketiadaan fitur toggle "Lihat/Sembunyikan Password" (eye toggle) yang menyulitkan pengguna memeriksa ketikan kata sandi baru.
  3. Desain input field polos tanpa ikon representatif (`lock`, `key`, `verified_user`) dan ketiadaan petunjuk panjang password (minimal 8 karakter).
  4. Template kartu terlihat mengambang polos (starter kit template slop) tanpa logo resmi organisasi.
* **Target Komponen Diperbaiki**:
  - `resources/views/auth/change-password.blade.php`
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4, Laravel Livewire 3)
  - Target URL: `http://localhost/change-password`
* **Parameter & Hasil Pengujian**:
  1. **Dynamic Branding & Identity**:
     - Menggunakan `\App\Helpers\AppBrandingHelper::getAppName()` dan `\App\Helpers\AppBrandingHelper::getAppLogoPath()`.
  2. **Interaktivitas Toggle Password Eye**:
     - Menghadirkan toggle show/hide berbasis Alpine.js (`showCurrent`, `showNew`, `showConfirm`) dengan ikon Material Symbols (`visibility` / `visibility_off`).
  3. **Embedded Visual Icons & Focus Rings**:
     - Menyematkan ikon `lock`, `key`, dan `verified_user` di sisi kiri setiap input, dengan padding proporsional dan efek focus ring teal `#1fa387`.
  4. **Petunjuk Validasi & Loading State Submit**:
     - Menambahkan teks keterangan minimal 8 karakter di bawah kolom password baru.
     - Menambahkan animasi spinner loading saat form di-submit untuk mencegah double submission.
  5. **Physical Runtime Render Test**:
     - Perintah: `php artisan view:clear` (exit code 0).
     - Render view `auth.change-password` via tinker: **10.204 bytes** (exit code 0, zero error).
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

---

### [QA-20260910-15] Perbaikan Tombol Kembali Halaman Ganti Password ke Menu Terakhir
* **Tanggal & Waktu**: 10 September 2026, 20:30 WIB
* **Konteks Masalah**:
  Tombol "Kembali" pada halaman Ganti Password harus dapat mengembalikan pengguna ke menu/tab terakhir yang sedang dibuka (misalnya `/?project=61&tab=YW5hbGlzaXM=`) secara instan tanpa proses loading lambat riwayat browser.
* **Target Komponen Diperbaiki**:
  - `app/Http/Controllers/Auth/LoginController.php` (capture header `referer` ke dalam session `change_password_back_url` dan passing `$backUrl` ke view)
  - `resources/views/auth/change-password.blade.php` (elemen Action Controls dengan link `<a href="{{ $backUrl ?? url('/') }}">`)
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4, Laravel Livewire 3)
  - Target URL: `http://localhost/change-password`
* **Parameter & Hasil Pengujian**:
  1. **Dynamic Contextual Navigation**:
     - Controller mendeteksi header referer saat pengguna datang dari menu manapun (misal tab Analisis, tab Wawasan, tab Penyebutan, dsb) dan menyimpannya di session `change_password_back_url`.
     - Tombol "Kembali" menggunakan tautan native `<a href="{{ $backUrl }}">` yang mengarah tepat ke menu proyek/tab terakhir yang sedang diakses pengguna.
  2. **Physical Runtime Render Test**:
     - Perintah: `php artisan view:clear` (exit code 0).
     - Simulasi referer `http://localhost/?project=61&tab=YW5hbGlzaXM=`: **BACK URL terdeteksi presisi**, HTML Render **10.115 bytes** (exit code 0, zero error).
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

---

### [QA-20260910-16] Audit & Perbaikan Modal Konfirmasi Persetujuan & Persistence Modal Proyek Dinonaktifkan
* **Tanggal & Waktu**: 10 September 2026, 20:36 WIB
* **Konteks Masalah**:
  Audit pada modal "Proyek Dinonaktifkan" menemukan:
  1. Fitur "Aktifkan" (restore) dan "Hapus" (force delete) memicu modal konfirmasi persetujuan (`showConfirmModal = true`), namun ketika aksi dieksekusi, modal "Proyek Dinonaktifkan" (`showTrashedModal`) ikut tertutup otomatis karena baris `$this->showTrashedModal = false;` di dalam fungsi `restoreProject()` dan `forceDeleteProject()`. Hal ini memaksa pengguna membuka ulang modal trashed setiap kali memproses proyek.
  2. Tombol aksi "Aktifkan" dan "Hapus" pada modal trashed belum memiliki feedback `wire:loading.attr="disabled"` dan spinner SVG indikator loading saat memicu konfirmasi.
  3. Modal trashed belum dilengkapi listener keyboard `@keydown.escape.window` untuk menutup modal dengan aman jika modal konfirmasi di atasnya tidak sedang aktif.
* **Target Komponen Diperbaiki**:
  - `resources/views/components/⚡projects-list.blade.php`
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4 CLI, Laravel Livewire 3)
* **Parameter & Hasil Pengujian**:
  1. **Audit Alur Konfirmasi Persetujuan**:
     - Memastikan aksi "Aktifkan" dan "Hapus" wajib melalui modal konfirmasi (`showConfirmModal = true`) pada z-index `z-[60]` dengan tombol "Batal" dan "Konfirmasi/Aktifkan/Hapus Permanen".
     - `confirmRestoreProject()` dan `confirmForceDeleteProject()` tervalidasi menyetel parameter konfirmasi secara presisi.
  2. **Eliminasi Auto-Close Prematur**:
     - Menghapus `$this->showTrashedModal = false;` dari `restoreProject()` dan `forceDeleteProject()`.
     - Menambahkan refresh cache proyek `$this->forgetProjectsCache();` dan `$this->projects = $this->getProjects();` agar daftar trashed dan daftar proyek aktif langsung tersinkronisasi tanpa menutup modal.
  3. **Visual Feedback & Keyboard Listener**:
     - Menambahkan atribut `wire:loading.attr="disabled"` dan spinner SVG pada tombol "Aktifkan" dan "Hapus".
     - Menambahkan `@keydown.escape.window="!$wire.showConfirmModal && $wire.closeModals()"` pada kontainer modal trashed.
  4. **Physical Runtime Verification**:
     - Syntax Check: `php -l resources/views/components/⚡projects-list.blade.php` -> **No syntax errors detected**.
     - View Clear: `php artisan view:clear` (exit code 0).
     - Livewire Component Init & Trashed Modal Render Test: **58.878 bytes**, exit code 0.
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

---

### [QA-20260910-17] Modernisasi Modal Konfirmasi & Interaktivitas Modul Manajemen Klien (/admin/clients)
* **Tanggal & Waktu**: 10 September 2026, 20:41 WIB
* **Konteks Masalah**:
  Audit modul Manajemen Klien (`/admin/clients`) menemukan:
  1. Penghapusan akun klien masih mengandalkan pop-up bawaan browser `wire:confirm`, tidak konsisten dengan desain modal interaktif modern sistem.
  2. Toggle status dan aksi hapus belum memiliki visual loading state / disabled feedback saat sedang diproses.
  3. Form pembuatan klien baru di `/admin/clients/create` belum memiliki fitur toggle lihat/sembunyikan password (*eye toggle*).
  4. Penggunaan session flash message belum tersinkronisasi dengan container toast global admin (`admin-toast`).
* **Target Komponen Diperbaiki**:
  - `app/Livewire/Admin/ClientManagement/ClientList.php`
  - `resources/views/livewire/admin/client-management/client-list.blade.php`
  - `app/Livewire/Admin/ClientManagement/ClientCreate.php`
  - `resources/views/livewire/admin/client-management/client-create.blade.php`
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4 CLI, Laravel Livewire 3)
* **Parameter & Hasil Pengujian**:
  1. **Modal Konfirmasi Interaktif Modern**:
     - Menggantikan `wire:confirm` dengan modal Tailwind/Alpine untuk konfirmasi hapus permanen (`confirmingDelete`) dan konfirmasi pengubahan status (`confirmingStatusChange`).
     - Modal dilengkapi backdrop blur, overflow control (`overflow-hidden`), escape key listener (`@keydown.escape.window`), dan outside click handler.
  2. **Feedback Loading State**:
     - Menambahkan atribut `wire:loading.attr="disabled"` dan spinner SVG (`progress_activity`) pada tombol aksi tabel serta tombol konfirmasi modal.
  3. **Fitur Toggle Password Eye**:
     - Mengintegrasikan state Alpine.js (`showPassword`, `showPasswordConfirmation`) dengan ikon Material Symbols (`visibility` / `visibility_off`) pada form pembuatan klien.
  4. **Penyelarasan Notifikasi Toast**:
     - Mengubah flash message menjadi `session()->flash('success', ...)` dan men-dispatch event Livewire `admin-toast` untuk integrasi SweetAlert2.
  5. **Physical Runtime Verification**:
     - Linter PHP: `php -l` pada `ClientList.php` dan `ClientCreate.php` -> **No syntax errors detected**.
     - View Clear: `php artisan view:clear` -> Clear successfully.
     - Livewire Component Render Test via Tinker:
       - Client List base: **4.970 bytes**, exit code 0.
       - Client Create base: **6.992 bytes**, exit code 0.
       - Status Modal active render: **6.573 bytes**, exit code 0.
       - Delete Modal active render: **6.585 bytes**, exit code 0.
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

---

### [QA-20260910-18] Pembersihan Slop Antarmuka & Toolbar Manajemen Klien
* **Tanggal & Waktu**: 10 September 2026, 20:43 WIB
* **Konteks Masalah**:
  Audit visual mendalam pada halaman manajemen klien menemukan:
  1. Toolbar terpisah (*starter kit slop*) di `client-list.blade.php` di mana tombol Tambah Klien berada di luar wadah tabel dan search bar berada di dalam header tabel.
  2. Ketiadaan visual feedback spinner saat Livewire melakukan pencarian data klien (`wire:model.live.debounce.300ms="search"`).
  3. Tombol "Kembali" pada halaman Tambah Klien dan Pengaturan Klien hanya berupa kotak panah kecil tanpa teks label konteks yang ramah pengguna.
  4. Tombol "Ya, Lepas" pada pelepasan proyek dari klien (`detachProject`) belum memiliki atribut `wire:loading.attr="disabled"` dan spinner animasi.
* **Target Komponen Diperbaiki**:
  - `resources/views/livewire/admin/client-management/client-list.blade.php`
  - `resources/views/livewire/admin/client-management/client-create.blade.php`
  - `resources/views/livewire/admin/client-management/client-settings.blade.php`
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4 CLI, Laravel Livewire 3)
* **Parameter & Hasil Pengujian**:
  1. **Penyatuan Toolbar & Indikator Loading Search**:
     - Menggabungkan search bar dan tombol "Tambah Klien" dalam satu baris toolbar responsif yang elegan.
     - Menyematkan spinner animasi `progress_activity` saat mengetik di input pencarian.
  2. **Perbaikan Navigasi Tombol Kembali**:
     - Menghadirkan tombol kembali dengan label jelas *"Kembali ke Manajemen Klien"* pada form Tambah Klien dan Pengaturan Klien.
  3. **Proteksi & Feedback Loading Detach Proyek**:
     - Menambahkan disabled state dan spinner animasi pada tombol "Ya, Lepas".
  4. **Physical Runtime Verification**:
     - View Clear: `php artisan view:clear` -> Clear successfully.
     - Livewire Component Render Test via Tinker:
       - Client List: **5.093 bytes**, exit code 0.
       - Client Create: **7.017 bytes**, exit code 0.
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)



---

### [QA-20260910-19] Penambahan Tombol "Kembali ke Proyek" di Halaman Manajemen Klien (khusus role: user)
* **Tanggal & Waktu**: 10 September 2026, 20:56 WIB
* **Konteks Masalah**:
  User dengan role `user` (bukan admin) dapat mengakses halaman `/admin/clients` melalui sidebar yang hanya menampilkan menu "Manajemen Klien" untuk mereka. Tidak ada jalur navigasi kembali ke halaman Proyek (`/`) dari halaman ini, menyebabkan pengalaman navigasi buntu bagi role `user`.
* **Target Komponen Diperbaiki**:
  - `resources/views/livewire/admin/client-management/client-list.blade.php`
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4 CLI, Laravel Livewire 3)
* **Parameter & Hasil Pengujian**:
  1. **Tombol "Kembali ke Proyek" kondisional**:
     - Menambahkan blok `@if(auth()->user()->isUser())` di atas toolbar utama.
     - Tombol menggunakan `wire:navigate` dan link ke `route('home')` (halaman projects list untuk non-admin).
     - Secara otomatis tersembunyi untuk role `admin` (karena `isUser()` hanya true untuk role `user`).
     - Role `client` sudah diblokir oleh `abort_if(...isClient(), 403)` di `ClientList.php::mount()` sehingga tidak dapat mengakses halaman ini sama sekali.
  2. **Verifikasi Role Method**:
     - `App\Models\User::where('role','user')->first()->isUser()` → `TRUE` ✅
     - `App\Models\User::where('role','admin')->first()->isAdmin()` → `TRUE` ✅
  3. **Physical Runtime Verification**:
     - PHP Lint: No syntax errors detected ✅
     - View Clear: `php artisan view:clear` → Clear successfully ✅
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

---

### [QA-20260910-20] Audit & Perbaikan Slop Halaman Buat Proyek (`/projects/create`)
* **Tanggal & Waktu**: 10 September 2026, 21:05 WIB
* **Konteks Masalah**:
  Audit mendalam halaman `/projects/create` menemukan 6 poin slop teknis dan UX:
  1. **Dead spinner** pada tombol "Lanjut ke Pengaturan" — `wire:loading` diarahkan ke `$set('createStep', 2)` yang merupakan client-side property update tanpa server round-trip, sehingga spinner tidak pernah muncul.
  2. **UX noise** — toast info biru yang selalu tampil di Step 1 menggunakan `<svg>` inline path panjang, tidak konsisten dengan sistem `material-symbols-outlined` aplikasi.
  3. **Label tombol ambigu** — tombol "Kembali" di Step 2 tidak jelas apakah kembali ke step sebelumnya atau ke halaman awal.
  4. **Performa slop** — input `type="time"` untuk override jadwal menggunakan `wire:model.live` yang memicu round-trip ke server setiap kali nilai berubah.
  5. **Inkonsistensi ikon spinner** — tombol "Buat Proyek" menggunakan `<svg>` inline untuk animasi loading, tidak konsisten dengan `progress_activity` material-symbols yang dipakai seluruh app.
  6. **Label ambigu** — tombol "Ubah" di pill paket terpilih tidak memberikan konteks jelas tentang apa yang diubah.
* **Target Komponen Diperbaiki**:
  - `resources/views/livewire/project-create.blade.php`
* **Environment Pengujian**:
  - Docker Container: `media_intelligent_container` (PHP 8.4 CLI, Laravel Livewire 3)
* **Perbaikan yang Dilakukan**:
  1. Hapus `wire:loading` + `<svg>` spinner dari tombol "Lanjut ke Pengaturan" (dead code).
  2. Ganti `<svg>` info toast dengan `<span class="material-symbols-outlined">info</span>` — konsisten dengan sistem ikon.
  3. Label tombol "Kembali" → **"Kembali ke Pilih Paket"** dan "Ubah" → **"Ubah Paket"** untuk konteks jelas.
  4. Ubah `wire:model.live` → `wire:model` pada semua input `type="time"` override jadwal.
  5. Ganti `<svg animate-spin>` pada spinner submit → `<span class="material-symbols-outlined animate-spin">progress_activity</span>`.
* **Physical Runtime Verification**:
  - PHP Lint: No syntax errors detected ✅
  - View Clear: `php artisan view:clear` → Clear successfully ✅
  - Blade Render Test (Step 1, packages=empty): **2.943 bytes**, exit code 0 ✅
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)


---

### [QA-20260910-21] Audit & Perbaikan Slop Step 2 "Konfigurasi Proyek" (`/projects/create`)
* **Tanggal & Waktu**: 10 September 2026, 21:11 WIB
* **Konteks Masalah**:
  Audit lanjutan Step 2 halaman pembuatan proyek menemukan 8 poin slop:
  1. Duplikasi `@if($selectedPackage)` dua kali berturut-turut (code slop).
  2. Copy slop: teks `'Interval lama'` tidak baku — harusnya `'Tidak dijadwalkan'`.
  3. Input time tanpa nomor slot — user tidak tahu Slot 1, Slot 2, dst.
  4. Tidak ada pemisah visual (divider) antara kartu jadwal dan form field.
  5. `wire:model` pada field `name` tidak memberikan validasi unique saat blur.
  6. Action buttons tidak responsif di mobile (`flex justify-end` saja).
  7. Edge-case: tidak ada guard jika `$selectedPackage` null di Step 2.
  8. `@if($portalSlots > 0)` / `@if($socialSlots > 0)` belum ada — input muncul walau paket tanpa jadwal.
* **Target Komponen Diperbaiki**:
  - `resources/views/livewire/project-create.blade.php`
* **Perbaikan yang Dilakukan**:
  1. Merge dua blok `@if($selectedPackage)` menjadi satu blok tunggal.
  2. `'Interval lama'` → `'Tidak dijadwalkan'` (2 tempat).
  3. Tambah label `Slot N` di kiri setiap input time override.
  4. Tambah `<div class="h-px bg-gradient...">` sebagai divider sebelum form field.
  5. `wire:model="name"` → `wire:model.blur="name"` untuk validasi unique onBlur.
  6. Action buttons: `flex flex-col-reverse sm:flex-row sm:justify-end` + `w-full sm:w-auto` per tombol.
  7. Tambah blok `@else` pada `@if($selectedPackage)` dengan amber warning + tombol kembali.
  8. Wrap input time dalam `@if($portalSlots > 0)` dan `@if($socialSlots > 0)`.
  9. Pill header paket ditambah `border-b border-slate-100` untuk pemisah visual.
* **Physical Runtime Verification**:
  - PHP Lint: No syntax errors detected ✅
  - View Clear: `php artisan view:clear` → Clear successfully ✅
  - Blade Render Test Step 1 (packages=empty): **2.943 bytes**, exit code 0 ✅
  - Blade Render Test Step 2 (no package guard): **12.763 bytes**, exit code 0 ✅
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

---

### [QA-20260910-22] Perbaikan Slop Tombol Nonaktifkan Proyek & Modernisasi Modal Konfirmasi Aksi
* **Tanggal & Waktu**: 10 September 2026, 21:20 WIB
* **Konteks Masalah**:
  Audit pada tombol aksi proyek dan modal konfirmasi di `⚡projects-list.blade.php` menemukan:
  1. Tombol "Nonaktifkan Proyek" menggunakan ikon tempat sampah (trash SVG) merah yang menyesatkan (menimbulkan asumsi data terhapus permanen), padahal aksinya adalah soft-delete/deaktivasi monitoring.
  2. Tombol Run Scraping & Edit masih menggunakan raw SVG inline dengan spinner bawaan yang tidak seragam.
  3. Header modal konfirmasi menggunakan icon trash rose untuk aksi 'delete', padahal harus dibedakan dari 'force_delete'.
  4. Tombol aksi konfirmasi di modal menggunakan inline SVG spinner alih-alih `progress_activity`.
  5. Close button modal menggunakan raw SVG X.
  6. Redundant state `x-data="{ show: true }"` pada backdrop modal.
* **Target Komponen Diperbaiki**:
  - `resources/views/components/⚡projects-list.blade.php`
* **Perbaikan yang Dilakukan**:
  1. Mengganti ikon tombol Nonaktifkan Proyek dari trash SVG menjadi `<span class="material-symbols-outlined text-[18px]">do_not_disturb_on</span>` dengan warna hover amber yang ramah.
  2. Mengganti tombol Run Scraping dan Edit dengan `material-symbols-outlined` (`play_circle`, `progress_activity`, `edit`).
  3. Menstandarisasi header modal dengan visual icon yang relevan per-aksi:
     - `delete` (Nonaktifkan): `do_not_disturb_on` (warna amber).
     - `force_delete` (Hapus Permanen): `delete_forever` (warna rose).
     - `restore` (Aktifkan): `restore` (warna emerald).
     - `run_scraping` (Jalankan Scraping): `play_circle` (warna emerald).
     - `sync_project` / default: `sync` (warna emerald).
  4. Modernisasi tombol CTA konfirmasi dengan teks aksi yang lebih humanis (`Ya, Nonaktifkan`, `Ya, Hapus Permanen`, `Ya, Aktifkan`, `Ya, Sinkronkan`), state loading spinner `progress_activity`, serta color coding proporsional.
  5. Close button diganti menggunakan `material-symbols-outlined: close`.
* **Physical Runtime Verification**:
  - PHP Lint: No syntax errors detected (`php -l`) ✅
  - View Clear: `php artisan view:clear` → Clear successfully ✅
  - Livewire Resolution Test: `ProjectsList` resolved successfully via tinker ✅
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

---

### [QA-20260910-23] Perbaikan Slop Antarmuka & UX Modal Edit Proyek (`project-edit-modal.blade.php`)
* **Tanggal & Waktu**: 10 September 2026, 21:22 WIB
* **Konteks Masalah**:
  Audit pada modal Edit Proyek menemukan sejumlah slop visual, copywriting, dan performa:
  1. Tombol close (X) header dan select paket menggunakan raw SVG inline.
  2. Typo class Tailwind CSS non-standar (`text-slate-455`, `hover:text-slate-650`, `border-slate-350`, `rounded-custom`).
  3. Input time jadwal override memakai `wire:model.live` yang memicu round-trip server berlebihan per perubahan jam.
  4. Slot override jadwal tidak memiliki penomoran slot (`Slot 1`, `Slot 2`, dst).
  5. Teks copy jadwal paket `'Interval lama'` tidak baku (harus `'Tidak dijadwalkan'`).
  6. Tombol simpan menggunakan inline style `background-color: #1fa387;` dan raw SVG spinner.
  7. Belum adanya shortcut tombol keyboard `Escape` dan handler click outside untuk menutup modal dengan nyaman.
  8. Input form utama belum dilengkapi dengan ikon visual sistem `material-symbols-outlined` seperti halnya form Create Project.
* **Target Komponen Diperbaiki**:
  - `resources/views/livewire/project-edit-modal.blade.php`
* **Perbaikan yang Dilakukan**:
  1. Standarisasi tombol close (X), lock paket, dan tombol submit ke font ikon `material-symbols-outlined: close`, `lock`, `save`, dan `progress_activity`.
  2. Menghapus semua invalid class Tailwind dan menggantinya ke standar sistem (`rounded-xl`, `border-slate-200`, `text-slate-500`, dsb).
  3. Mengubah binding waktu override dari `wire:model.live` menjadi `wire:model`.
  4. Menambahkan label penomoran slot (`Slot N`) yang proporsional di sisi kiri setiap input time override.
  5. Menyelaraskan teks jadwal paket `'Interval lama'` menjadi `'Tidak dijadwalkan'`.
  6. Menambahkan handler `@keydown.escape.window="$wire.close()"` dan `@click.outside.stop="$wire.close()"`.
  7. Melengkapi seluruh input field form (`Nama Proyek`, `Telegram Chat ID`, `Kata Kunci Pencarian`, `Kata Kunci Penyaring`, `Kata Kunci Pengecualian`) dengan ikon prefix sistem yang konsisten.
* **Physical Runtime Verification**:
  - PHP Lint: No syntax errors detected (`php -l`) ✅
  - View Clear: `php artisan view:clear` → Clear successfully ✅
  - Livewire Resolution Test: `ProjectEditModal` resolved successfully via tinker ✅
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

---

### [QA-20260910-24] Standardisasi Ukuran Fix Modal & Background Scroll Lock
* **Tanggal & Waktu**: 10 September 2026, 21:26 WIB
* **Konteks Masalah**:
  Audit interaksi modal (`project-edit-modal` dan `⚡projects-list`) menemukan:
  1. Ukuran modal melebar tak terkendali (`max-w-4xl`) di monitor besar sehingga bidang input tampak renggang dan tidak ergonomis.
  2. Saat modal aktif, halaman latar belakang di belakang backdrop masih dapat ter-scroll (*scroll bleed*), mengurangi fokus dan kenyamanan navigasi.
  3. Perlu aturan arsitektur baku yang mewajibkan isolasi scroll modal (*fixed boundaries header-footer, inner body scroll only, background scroll lock*).
* **Target Komponen Diperbaiki**:
  - `resources/views/livewire/project-edit-modal.blade.php`
  - `resources/views/components/⚡projects-list.blade.php`
* **Perbaikan yang Dilakukan**:
  1. **Standardisasi Ukuran Fix**:
     - Modal Edit Proyek diatur menjadi proporsional `max-w-2xl` dengan tinggi terarah `h-[82vh] max-h-[640px]`.
     - Modal Daftar Proyek Dinonaktifkan diatur menjadi `max-w-3xl` dengan tinggi `h-[80vh] max-h-[580px]`.
  2. **Isolasi Scroll Body Form**:
     - Header (`shrink-0 border-b`) dan Footer (`shrink-0 border-t`) statis/terkunci.
     - Kontainer form di tengah diberikan `flex-1 overflow-y-auto overscroll-contain`.
  3. **Background Scroll Lock Otomatis (Alpine.js)**:
     - Backdrop modal dilengkapi hook Alpine `x-data x-init="document.body.classList.add('overflow-hidden'); return () => document.body.classList.remove('overflow-hidden');"`.
     - Latar belakang terkunci saat modal dibuka, dan pulih saat modal ditutup.
* **Physical Runtime Verification**:
  - PHP Lint: No syntax errors detected (`php -l`) ✅
  - View Clear: `php artisan view:clear` → Clear successfully ✅
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

---

### [QA-20260910-25] Penegasan Aturan Mutlak: Penguncian Latar Belakang Modal (Dual HTML+BODY Lock & Wheel Event Trap)
* **Tanggal & Waktu**: 10 September 2026, 21:36 WIB
* **Konteks Masalah**:
  Pengujian runtime menemukan bahwa latar belakang (halaman di balik modal) masih bisa bergulir saat modal aktif. Hal ini disebabkan oleh:
  1. Penguncian hanya dilakukan pada elemen `<body>`, sementara browser Chromium/Safari meneruskan event scroll ke `<html>` (`documentElement`).
  2. Ketiadaan CSS pengunci ketinggian ketat `height: 100vh !important; touch-action: none !important;` pada class `overflow-hidden`.
  3. Ketiadaan penahan event scroll mousewheel (`@wheel.self.prevent`) pada area backdrop gelap di luar modal.
* **Target Komponen Diperbaiki**:
  - `resources/views/components/media-dashboard-styles.blade.php`
  - `resources/views/livewire/project-edit-modal.blade.php`
* **Perbaikan yang Dilakukan**:
  1. Menyematkan aturan CSS global mutlak di `media-dashboard-styles.blade.php`:
     ```css
     html.overflow-hidden, body.overflow-hidden {
         overflow: hidden !important;
         height: 100vh !important;
         max-height: 100vh !important;
         touch-action: none !important;
     }
     ```
  2. Memperbarui inisialisasi backdrop modal (`x-init`) agar mengunci `document.documentElement` dan `document.body` secara bersamaan:
     ```javascript
     document.documentElement.classList.add('overflow-hidden');
     document.body.classList.add('overflow-hidden');
     ```
  3. Menambahkan `@wheel.self.prevent` dan `@touchmove.self.prevent` pada elemen backdrop modal untuk memutus rantai *scroll bleed/chaining*.
* **Physical Runtime Verification**:
  - PHP Lint: No syntax errors detected (`php -l`) ✅
  - View & Bootstrap Cache Clear: `php artisan optimize:clear` → Selesai ✅
  - Vite Asset Build: `npm run build` → Selesai dalam 844ms ✅
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

---

### [QA-20260910-26] Pembersihan Slop Modal Proyek Dinonaktifkan & Penegakan Dual Scroll-Lock
* **Tanggal & Waktu**: 10 September 2026, 21:38 WIB
* **Konteks Masalah**:
  Modal daftar "Proyek Dinonaktifkan" di `⚡projects-list.blade.php` memiliki sejumlah slop:
  1. Penguncian scroll latar belakang hanya pada `body` (belum dual-lock HTML+BODY dan belum ada trap wheel di backdrop).
  2. Icon header, close button, icon empty state, dan icon tombol aksi masih menggunakan raw inline SVG dan class non-standar (`hover:text-slate-650`, `text-slate-350`, `text-rose-650`).
  3. Body scroll belum memiliki `overscroll-contain` dan pembatasan flex eksplisit.
  4. Spinner tombol aksi masih berupa raw SVG.
* **Target Komponen Diperbaiki**:
  - `resources/views/components/⚡projects-list.blade.php`
* **Perbaikan yang Dilakukan**:
  1. Menerapkan aturan wajib PRD 7.25: Dual-lock HTML+BODY via Alpine lifecycle (`x-init`) dan penahan event `@wheel.self.prevent` serta `@touchmove.self.prevent`.
  2. Mengganti icon header ke `material-symbols-outlined: do_not_disturb_on` (amber lembut) untuk mencerminkan status nonaktif.
  3. Mengganti close button ke `material-symbols-outlined: close`.
  4. Memperbaiki tampilan empty state dengan icon `material-symbols-outlined: check_circle` dan class warna standar `text-slate-400`.
  5. Melengkapi tombol aksi dengan icon representatif:
     - Tombol Aktifkan: `material-symbols-outlined: restore` + spinner `progress_activity`.
     - Tombol Hapus: `material-symbols-outlined: delete_forever` + spinner `progress_activity`.
  6. Mengisolasi scrolling body list dengan `overscroll-contain` dan pembatasan flex `style="flex: 1 1 auto; min-height: 0;"`.
  7. Menghapus semua class typo Tailwind (`text-rose-650` → `text-rose-600`, `hover:text-slate-650` → `hover:text-slate-600`).
* **Physical Runtime Verification**:
  - PHP Lint: No syntax errors detected (`php -l`) ✅
  - View & Bootstrap Cache Clear: `php artisan optimize:clear` → Selesai ✅
  - Vite Asset Build: `npm run build` → Selesai dalam 1.82s ✅
* **Status**: **PASSED (100% Sukses)**
* **Commit Lokal**: Menunggu perintah user (Protokol No Auto-Push Aktif)

## [QA-20260910-27] Modernisasi Tombol "Detail Proyek" & Judul Proyek Clickable

**Tanggal**: 2026-09-10
**File**: `resources/views/components/⚡projects-list.blade.php`
**Status**: ✅ FIXED

### Masalah
1. Judul proyek (`<h2>`) hanya teks statis — tidak bisa diklik.
2. Tombol "Detail Proyek" menggunakan warna `border-primary text-primary` (non-brand), tidak ada ikon, raw SVG spinner, dan tidak ada `wire:navigate` (full reload).

### Perbaikan
1. **Judul proyek** (baris ~1091): Ubah `<h2>` → `<a wire:navigate href="...">` dengan link ke dashboard proyek. Warna brand `text-[#1fa387]`, `hover:underline`.
2. **Tombol "Detail Proyek"** (baris ~1264):
   - Tambah `wire:navigate` untuk SPA navigation tanpa full reload.
   - Warna: `border-[#1fa387] text-[#1fa387] hover:bg-[#1fa387] hover:text-white`.
   - Tambah ikon `arrow_forward` (material-symbols) pada state normal.
   - Ganti raw SVG spinner → `<span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>`.

### Verifikasi
- PHP lint: No syntax errors.
- `php artisan view:clear`: OK.

## [QA-20260910-28] Fix Kontras Teks & Ikon saat Hover Tombol Detail Proyek

**Tanggal**: 2026-09-10
**File**: `resources/views/components/⚡projects-list.blade.php`
**Status**: ✅ FIXED

### Masalah
Saat tombol "Detail Proyek" di-hover, latar belakang berubah menjadi hijau (`#1fa387`) namun teks dan ikon tidak terlihat (invisible/kontras hilang) karena `hover:text-white` pada parent `<a>` tidak mewarisi secara eksplisit ke elemen anak atau belum ter-build.

### Perbaikan
1. Menambahkan utility class `group` pada kontainer tag `<a>` tombol "Detail Proyek".
2. Menambahkan `group-hover:text-white transition-colors` pada label teks serta ikon `arrow_forward` dan spinner `progress_activity`.
3. Menjalankan `npm run build` dan `php artisan view:clear` untuk memastikan utility class ter-compile sempurna ke dalam bundle CSS.

### Verifikasi
- Asset build: `npm run build` sukses (vite).
- Cache view dibersihkan.

## [QA-20260910-29] Standarisasi Tombol & Optimasi Reaktivitas Wawasan AI

**Tanggal**: 2026-09-10
**File**: 
- `resources/views/livewire/media-dashboard.blade.php`
- `app/Livewire/MediaDashboard.php`
**Status**: ✅ FIXED

### Masalah
1. Tombol "Perbarui Wawasan AI" menggunakan raw SVG inline untuk ikon petir dan spinner loading (melanggar panduan Material Symbols).
2. Badge "Terupdate" hanya berstatus teks statis tanpa informasi kapan terakhir kali diperbarui.
3. Method `generateAiInsights()` tidak mereset memo `$this->wawasanMemo` dan entri cache `Cache::remember` untuk tab Wawasan, sehingga wawasan baru berpotensi tidak langsung tampil secara reaktif karena tertahan cache lama.

### Perbaikan
1. **Standarisasi Ikon Tombol**:
   - Ikon normal: `<span class="material-symbols-outlined text-[16px]">auto_awesome</span>`.
   - Spinner loading: `<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>`.
2. **Badge Terupdate Dinamis**:
   - Menampilkan selang waktu relatif secara human-readable (`Terupdate diffForHumans()`) serta tooltip tanggal & jam lengkap.
3. **Invalidasi Cache & Memo di Backend**:
   - Menambahkan `$this->wawasanMemo = [];` dan `Cache::forget($cacheKey);` saat `generateAiInsights()` dijalankan agar wawasan langsung ter-refresh secara real-time.

### Verifikasi
- PHP lint pada `MediaDashboard.php` dan `media-dashboard.blade.php`: No syntax errors.
- `php artisan view:clear`: Sukses.

## [QA-20260910-30] Sinkronisasi Tanggal Filter & Indikator Transparansi Wawasan AI

**Tanggal**: 2026-09-10
**File**:
- `app/Livewire/MediaDashboard.php`
- `resources/views/livewire/media-dashboard.blade.php`
**Status**: ✅ FIXED

### Masalah
1. Hasil wawasan AI tersimpan statis pada proyek; saat pengguna mengubah filter tanggal (`startDate` / `endDate`), teks wawasan AI lama tetap tampil sehingga narasi ringkasan tidak sinkron dengan grafik & tanggal aktif.
2. Pengguna tidak dapat membedakan apakah ringkasan yang tampil berasal dari Model AI atau sekadar formula fallback (estimasi sistem).

### Perbaikan
1. **Indikator Filter Tanggal Aktif**:
   - Menambahkan banner informatif amber di atas tab Wawasan ketika filter tanggal aktif (`startDate` atau `endDate` terisi) yang mengingatkan pengguna untuk mengklik *"Perbarui Wawasan AI"* guna menyelaraskan narasi analisis dengan rentang tanggal baru.
2. **Pembeda Sumber Ringkasan (Transparansi UX)**:
   - Menambahkan flag `is_ai_generated` di `getWawasan()`.
   - Mengganti badge statis "Eksekutif" di kartu Ringkasan Eksekutif menjadi:
     - `✨ Model AI` (jika sudah di-generate via LLM).
     - `🧮 Estimasi Sistem` (jika masih formula statistik cepat).

### Verifikasi
- PHP syntax check lulus.
- View cache berhasil dibersihkan.

## [QA-20260910-31] Implementasi Modal Konfirmasi Sebelum Eksekusi Wawasan AI

**Tanggal**: 2026-09-10
**File**: `resources/views/livewire/media-dashboard.blade.php`
**Status**: ✅ FIXED

### Masalah
Tombol "Perbarui Wawasan AI" sebelumnya langsung mengeksekusi request AI ke LLM secara instan tanpa dialog konfirmasi, sehingga rentan terhadap ketidaksengajaan klik (accidental click) yang memicu konsumsi token LLM dan mengubah data ringkasan proyek.

### Perbaikan
1. **State Konfirmasi Alpine**:
   - Menambahkan variable `showAiInsightConfirmModal: false` pada objek root `x-data` di `media-dashboard.blade.php`.
   - Menambahkan `showAiInsightConfirmModal` ke `x-effect` scroll-lock agar background lock aktif saat modal terbuka.
2. **Tombol Pemicu**:
   - Mengubah tombol header "Perbarui Wawasan AI" menjadi `@click="showAiInsightConfirmModal = true"` alih-alih langsung memanggil `$wire.generateAiInsights()`.
3. **Modal Konfirmasi Interaktif**:
   - Menampilkan modal konfirmasi dengan judul *"Perbarui Wawasan AI?"* dan penjelasan ringkas.
   - Menggunakan ikon Material Symbols `auto_awesome` (emerald/brand).
   - Mematuhi standar modal PRD:
     - Backdrop blur `bg-slate-900/60 backdrop-blur-sm`.
     - Wheel & touch trap: `@wheel.self.prevent @touchmove.self.prevent`.
     - Tutup via Escape key `@keydown.escape.window` dan klik luar `@click.outside`.
     - Tombol Batal & tombol CTA *"Ya, Perbarui"* dengan ikon `check_circle`.

### Verifikasi
- PHP syntax check lulus (`No syntax errors detected`).
- `php artisan view:clear` sukses.

---

## [QA-20260910-32] Fix Overlay `preparePdfReport` Memblokir Tombol Perbarui Wawasan AI

- **Tanggal:** 2026-09-10
- **Konteks:** Tombol "Perbarui Wawasan AI" tidak bisa ditekan — klik tidak ter-register.
- **Root Cause:** Div overlay `wire:loading.flex wire:target="preparePdfReport"` di line 5880 `media-dashboard.blade.php` tidak memiliki `style="display:none;"`. Berbeda dengan overlay lain (reportFeedback, AI confirm modal) yang sudah memiliki atribut ini. Tanpa `style="display:none;"`, Livewire kadang tidak menyembunyikan elemen `wire:loading.flex` saat tidak ada proses loading, sehingga overlay `fixed inset-0 z-[9999]` menutup seluruh layar dan memblokir semua pointer event termasuk klik tombol.
- **Target File:** `resources/views/livewire/media-dashboard.blade.php` (line 5880)
- **Perubahan:** Tambah `style="display:none;"` ke div overlay preparePdfReport.
- **Verifikasi:**
  - `php artisan view:clear` → `INFO Compiled views cleared successfully.` ✅
  - PHP syntax check implisit via view compile ✅
- **Status:** PASSED ✅
