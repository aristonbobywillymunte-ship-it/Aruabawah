# Project Progress Log

## Status Proyek Saat Ini:
* **Seluruh Portal Utama:** 13 portal berita aktif di Kalimantan Timur telah dibersihkan dari duplikasi data, server mati (`samarindatv.com` dihapus), dan statusnya berhasil diverifikasi (**`verified`** / Lolos Uji).

---

## Log Aktivitas Terbaru (20 Juli 2026)

### 1. Filter Detail Kata Kunci Proyek Diaktifkan
* Field `UTAMA`, `Konteks`, dan `Dikecualikan` sekarang dipakai benar-benar oleh backend matching, bukan sekadar tampil di form.
* `UTAMA` dipakai sebagai keyword utama yang cukup salah satu cocok, `Konteks` harus semuanya muncul, dan `Dikecualikan` langsung menolak item yang mengandung salah satu kata terlarang.
* Nilai filter ini disimpan di tabel `projects` lewat kolom baru `context_keywords` dan `exclude_keywords`.
* Validasi syntax PHP dan migration akan dicek setelah patch.

### 1. Semua Item Apify Sosial Disimpan Dulu
* Jalur ingest Apify untuk Facebook, Instagram, dan TikTok tidak lagi membuang item hanya karena keyword proyek tidak cocok atau kontennya dinilai terlalu pendek/noisy.
* Item social sekarang tetap disimpan ke database dulu, lalu penyaringan relevansi proyek dibiarkan terjadi di tahap linking/dashboard.
* Validasi syntax PHP untuk job Apify akan dicek setelah perubahan.

### 1. Proyek Baru Otomatis Bootstrap Scraping
* Saat proyek baru dibuat, sistem sekarang langsung mengantrikan job bootstrap yang menjalankan portal/news scraping lalu Apify scraping untuk project tersebut.
* Bootstrap berjalan di background melalui queue agar proses simpan proyek tetap cepat dan tidak menunggu scraping selesai di request UI.
* Validasi syntax PHP untuk job bootstrap dan Livewire proyek akan dicek setelah perubahan.

### 1. State Filter Dashboard Dipaksa Reset Saat Buka Project
* Komponen dashboard `media-dashboard` sekarang mereset state filter saat project dimount, termasuk `search`, `selectedSentiment`, `selectedSources`, `selectedCategory`, `sortBy`, `limit`, dan `selectedKeyword`.
* Tujuannya agar tampilan IG/TikTok tidak nyangkut di state lama seperti `selectedSources = ['News']` ketika pengguna pindah atau membuka project lain.
* Validasi syntax Blade untuk file dashboard perlu dicek ulang setelah perubahan.

### 1. Matching Project Dipangkas Query-nya
* Jalur matching lintas project sekarang meng-cache daftar keyword per project dan menghindari query `Project::find()` berulang untuk setiap item yang cocok.
* Existing social-content matching juga sekarang mengambil `raw_json` supaya hashtag sosial tetap kebaca penuh dan konsisten dengan aturan hashtag-only.
* Command audit project aktif ikut diselaraskan ke pembacaan hashtag sosial agar tidak lagi memakai `author_name` sebagai pemicu kecocokan.
* Validasi syntax PHP untuk file terkait lulus setelah perubahan.

### 1. Scheduler Apify Mengikuti Interval Setting
* Schedule `scraping:run-apify` sekarang memakai cron dari `portal_crawling_interval` alih-alih dipaksa jalan setiap menit.
* Ini mengurangi penumpukan job scraping berat yang sebelumnya selalu dipicu scheduler meskipun interval sudah diatur lebih longgar di database.
* Validasi syntax PHP untuk `routes/console.php` perlu dicek ulang setelah perubahan.

---

## Log Aktivitas Terbaru (17 Juli 2026)

### 1. Payload TikTok Disamakan dengan Modal Admin
* Modal edit TikTok sekarang menampilkan field resmi yang benar-benar ikut payload Apify: `resultsPerPage`, `Use Apify Proxy`, `Subtitle Option`, dan opsi download media.
* Builder payload TikTok tetap mengirim `hashtags`, `resultsPerPage`, `shouldDownloadCovers`, `shouldDownloadSlideshowImages`, `shouldDownloadVideos`, `downloadSubtitlesOptions`, dan `proxyConfiguration.useApifyProxy` agar isi modal dan request Apify identik.
* Validasi syntax PHP untuk Livewire admin dan Blade modal lulus setelah perubahan.

### 1. Count TikTok Dashboard Dibuat Stabil
* Perhitungan source count di dashboard sekarang tidak lagi ikut tersapu filter sentimen AI yang belum tersedia.
* Breakdown sumber proyek, termasuk TikTok, dihitung dari pool artikel yang masih visible sehingga angka tidak jatuh ke `0` hanya karena hasil AI pending.
* Validasi syntax Blade untuk file dashboard sudah dicek setelah perubahan.

### 1. Validasi Syntax Dashboard Lulus
* File `resources/views/components/⚡media-dashboard.blade.php` sudah dicek ulang dengan `php -l` dan tidak ada error syntax.
* Ini memastikan patch stabilitas count TikTok bisa dirender tanpa merusak Blade utama dashboard.

### 1. Default Limit Apify Disambungkan ke Payload Instagram dan TikTok
* Nilai `Batas Hasil Aktif` dari modal kini disinkronkan ke properti Instagram saat simpan, jadi input `wire:model.defer` tidak lagi membuat limit IG tertinggal saat payload dibangun.
* Builder payload Instagram dan TikTok sekarang memakai `default_limit` sebagai fallback nyata sebelum jatuh ke limit runtime job, sehingga nilai yang diisi di modal benar-benar terkirim ke Apify.
* Validasi syntax PHP untuk file Livewire admin dan model Apify sudah lulus setelah perubahan.

### 1. Legacy TikTok Dihapus dari Registry
* Entry legacy TikTok `epctex/tiktok-search-scraper` dan `paul_44/tiktok-search` sudah dihapus dari daftar registry agar tidak muncul lagi sebagai opsi konfigurasi.
* Runtime TikTok kini hanya mengacu ke actor canonical `clockworks/tiktok-hashtag-scraper`.
* Validasi syntax PHP untuk registry lulus setelah perubahan.

### 1. Fallback Payload TikTok Saat Konfigurasi Sudah Dibersihkan
* Builder payload TikTok sekarang tetap memakai `resultsPerPage` dari limit runtime jika `output_mapping` lama tidak lagi menyimpan field itu.
* Perbaikan ini mencegah payload `resultsPerPage` jatuh ke `0` setelah data actor TikTok lama dibersihkan dari database.
* Validasi syntax PHP untuk model dan test payload TikTok lulus setelah perubahan.

### 1. Pivot TikTok Lama Juga Dibersihkan
* Relasi `project_social_media_items` yang mengarah ke item TikTok legacy ikut dihapus lewat migrasi cleanup tambahan.
* Ini memastikan tidak ada sisa hubungan proyek-ke-item TikTok lama yang tertinggal setelah `social_media_items` dibersihkan.
* Validasi syntax PHP untuk migrasi cleanup pivot TikTok lulus setelah perubahan.

### 1. Data Runtime TikTok Lama Dibersihkan
* Baris lama TikTok di `apify_dispatch_states` dan `social_media_items` akan dihapus lewat migrasi pembersihan baru.
* Pembersihan ini ikut merapikan data turunan karena relasi pivot dan analysis sudah memakai cascade dari `social_media_items`.
* Validasi syntax PHP untuk migrasi pembersihan runtime TikTok lulus setelah perubahan.

### 1. Data Lama TikTok Dihapus Bersih dari Database
* Record legacy TikTok `epctex/tiktok-search-scraper` dan `paul_44/tiktok-search` akan dihapus dari tabel `apify_actors` lewat migrasi pembersihan baru.
* Actor TikTok canonical `clockworks/tiktok-hashtag-scraper` dipertahankan dan dirapikan supaya hanya menyisakan data yang memang dipakai runtime.
* Validasi syntax PHP untuk migrasi pembersihan TikTok lulus setelah perubahan.

### 1. Target Rentang Waktu Disembunyikan dari Modal
* Input `Target Rentang Waktu` sudah dihapus dari modal edit actor agar pengaturan tidak menampilkan field yang sebenarnya dikelola otomatis oleh backend.
* Logika `range_mode` tetap aktif di backend untuk kebutuhan default payload dan kompatibilitas actor lama yang masih memakainya.
* Validasi syntax Blade untuk file modal lulus setelah perubahan.

### 1. Konfigurasi Khusus TikTok Dihapus dari Modal
* Blok `Konfigurasi Khusus TikTok` beserta semua input `Max posts per hashtag`, `Use Apify Proxy`, `Subtitle Option`, `Should Download Covers`, `Should Download Slideshow Images`, dan `Should Download Videos` sudah dihapus dari modal edit actor.
* State Livewire TikTok dan migrasi data lama yang masih menyimpan opsi payload TikTok juga dibersihkan supaya database tidak lagi memuat field konfigurasi yang tidak ditampilkan.
* Validasi syntax PHP/Blade untuk file terkait lulus setelah perubahan.

### 1. Blok Identitas & Target Disembunyikan dari Modal
* Bagian `Identitas & Target` beserta field `Platform Medsos`, `Fungsi Scraper`, `Nama Aktor`, dan `Actor Slug (Path Apify)` sudah dihapus dari modal edit actor.
* Modal sekarang fokus ke pengaturan yang memang dipakai runtime dan tidak menampilkan identitas actor yang sifatnya sudah canonical dari registry.
* Validasi syntax Blade untuk file modal lulus setelah perubahan.

### 1. Opsi Keyword Search Instagram Dihapus Total
* Checkbox `Scrape with a keyword instead of hashtag` sudah dihapus dari modal Instagram.
* Field state `instagram_keyword_search`, mapping `keywordSearch`, dan default JSON lama ikut dibersihkan dari registry, seed, model, serta migrasi data Instagram.
* Validasi syntax PHP untuk file terkait lulus setelah perubahan.

### 1. Field Instagram yang Tidak Dipakai Dihapus dari Modal
* Modal edit Instagram sekarang tidak lagi menampilkan `Content type` dan `Maximum posts or reels per hashtag`.
* Sisa input yang ditampilkan difokuskan ke field yang masih dipakai runtime, supaya UI tidak membingungkan dan tidak mendorong input yang tidak terpakai.
* Validasi syntax Blade untuk modal Instagram lulus setelah perubahan.

### 1. Label TikTok Ditambahkan ke Batas Per Hashtag
* Modal TikTok sekarang menampilkan label yang lebih jelas: `Max posts per hashtag` dengan keterangan batas keras sekitar 800 item.
* Field ini tetap memetakan ke `resultsPerPage`, jadi UI lebih jelas tanpa mengubah payload resmi Apify.
* Validasi syntax Blade untuk file terkait lulus.

### 1. Kolom Redundan Apify Dihapus dari Schema
* Kolom `post_filter_enabled` dan `cost_reference` sudah dihapus dari model, registry, seed data, dan form admin.
* Migration baru ditambahkan untuk benar-benar menghapus kedua kolom itu dari tabel `apify_actors` di database PostgreSQL.
* Field yang tersisa sekarang fokus ke data yang memang dipakai runtime: limit, interval, RAM, mode rentang, prioritas, build, timeout, dan biaya maksimum per run.
* Validasi syntax PHP untuk file terkait lulus.

### 1. Duplikasi Field Modal Dipangkas
* Field yang tampil ganda di modal Apify sudah dirapikan: `memory_limit` dan `maximum_cost_per_run_usd` hanya tampil di Run Options, bukan lagi di blok performa.
* Bagian performa sekarang fokus ke `interval_minutes`, `priority`, `range_mode`, dan `defaultLimit`, sehingga alur edit actor lebih ringkas.
* Validasi syntax PHP untuk file terkait lulus.

### 1. Match Sosial Tidak Lagi Mengandalkan Field Query
* Filter kecocokan sosial di worker dan layanan matching sudah tidak lagi membaca `searchQuery`, `searchTerm`, `keyword`, atau `query` dari `raw_json`.
* Item sekarang hanya dipertimbangkan lewat sinyal konten nyata seperti nama akun, URL, hashtag/tag yang memang muncul di konten, dan teks konten untuk non-sosial.
* Perubahan ini mencegah post hasil scraping ikut masuk ke project hanya karena query pencariannya sama, bukan karena isi post-nya cocok.
* Validasi syntax PHP untuk file terkait lulus.

### 1. Alias TikTok Diseragamkan ke `resultsPerPage`
* Properti internal Livewire TikTok yang sebelumnya memakai nama `tiktok_max_items` sudah diganti menjadi `tiktok_results_per_page` agar konsisten dengan payload resmi Apify.
* Modal edit TikTok, validasi, reset form, default loader, dan builder output mapping semuanya sekarang membaca satu nama alias yang sama.
* Tidak ada lagi referensi alias lama di modal TikTok, sehingga istilah di UI, kode, dan payload kini sinkron.
* Validasi syntax PHP/Blade untuk file terkait lulus.

### 1. Modal TikTok Dipangkas ke Field Resmi
* Modal edit TikTok sekarang hanya menampilkan field resmi yang memang dipakai actor terbaru: `resultsPerPage`, `useApifyProxy`, `downloadSubtitlesOptions`, `shouldDownloadCovers`, `shouldDownloadSlideshowImages`, dan `shouldDownloadVideos`.
* Editor JSON manual TikTok sudah dihapus supaya user tidak bisa memasukkan field yang tidak ada di schema actor baru.
* Payload TikTok tetap dibentuk otomatis oleh backend, jadi database dan request Apify tetap konsisten.
* Validasi syntax PHP untuk file terkait lulus.

### 1. Payload TikTok Disesuaikan ke Schema Resmi Apify
* Actor `clockworks/tiktok-hashtag-scraper` sekarang mengikuti payload resmi: `hashtags`, `resultsPerPage`, `shouldDownloadCovers`, `shouldDownloadSlideshowImages`, `shouldDownloadVideos`, `downloadSubtitlesOptions`, dan `proxyConfiguration`.
* Modal edit TikTok sudah menampilkan field yang sama dengan payload resmi, termasuk opsi download media dan subtitle.
* Builder payload, registry, seeder, migration, dan worker limit TikTok sudah diarahkan ke `resultsPerPage` agar data database dan data yang dikirim ke Apify identik.
* Validasi syntax PHP untuk file terkait lulus.

### 1. Normalisasi Hashtag TikTok Disamakan dengan Instagram
* Normalisasi hashtag TikTok sekarang diarahkan lewat helper yang sama dengan Instagram, supaya aturan pembersihan teks selalu konsisten.
* Jalur builder TikTok menggunakan helper tersebut secara eksplisit, jadi perubahan aturan hashtag ke depan hanya perlu dilakukan di satu tempat.
* Validasi syntax PHP untuk file model TikTok lulus.

### 1. Audit Schema TikTok Resmi Selesai
* Actor TikTok baru `clockworks/tiktok-hashtag-scraper` diperlakukan sebagai actor hashtag-only berdasarkan schema resmi Apify.
* Payload yang dikirim sekarang dipangkas ke `hashtags`, `maxVideosPerHashtag`, dan `proxyConfiguration.useApifyProxy`.
* Modal edit TikTok dan seeder/database sudah dibersihkan dari field warisan seperti lokasi, sort type, mirror videos, dan field keyword lain yang tidak ada di schema actor baru.
* Validasi syntax PHP untuk file terkait lulus.

### 1. Audit TikTok Modal dan Database Diselaraskan
* Input TikTok yang tidak dipakai actor terbaru sudah dihapus dari modal edit, khususnya input keyword manual dan field mapping yang hanya membingungkan.
* Modal kini hanya menonjolkan sumber keyword dari proyek, sementara payload tetap dibentuk dari `keywords` sesuai actor `clockworks/tiktok-hashtag-scraper`.
* Database canonical TikTok ikut dinormalisasi lewat migration supaya row lama tidak menyimpan `default_keyword` warisan dan mapping yang tidak sesuai.
* Validasi syntax PHP untuk file terkait lulus setelah patch.

### 1. Modal Edit TikTok Disesuaikan ke Actor Canonical
* Modal edit TikTok sekarang menampilkan identitas actor `TikTok Hashtag Scraper` dan slug `clockworks/tiktok-hashtag-scraper` secara eksplisit.
* Copy panduan, judul payload, dan field mapping TikTok diselaraskan agar tidak lagi merujuk ke istilah lama yang membingungkan.
* Nilai `keyword_field_mapping` TikTok dipaksa ke `keywords` dari backend supaya modal edit konsisten saat dibuka ulang.
* Validasi syntax PHP untuk file terkait akan dicek setelah patch ini.

### 1. TikTok Actor Dipindah ke Clockworks
* Actor TikTok canonical sekarang memakai `clockworks/tiktok-hashtag-scraper`.
* Record lama `paul_44/tiktok-search` diselaraskan lewat migrasi database agar slug baru aktif di runtime.
* Registry, seeder, dan test juga sudah disesuaikan ke slug baru.
* Validasi syntax PHP untuk file terkait lulus, dan migrasi database sudah berhasil dijalankan.

### 1. Filter Artikel Gubernur Dibedakan dari Wagub
* Artikel mirror yang hanya membahas `Wakil Gubernur` sekarang tidak lagi masuk ke proyek `Gubernur Kaltim` hanya karena menyebut `Kalimantan Timur`.
* Pengecekan dibuat lebih presisi agar frasa `Wakil Gubernur` tidak dianggap sebagai `Gubernur` tanpa bukti kuat tambahan.
* Validasi syntax PHP pada service matching lulus setelah perubahan.

### 1. Matching Proyek Diperluas ke Varian Hashtag
* Keyword proyek sekarang punya varian pencocokan hashtag saat matching, jadi teks seperti `#gubernurkaltim` ikut terbaca tanpa mengubah data topik asli di database.
* Topik proyek tetap disimpan dalam bentuk asli, lalu sistem matching yang menambahkan bentuk hashtag sebagai sinyal tambahan.
* Syntax PHP pada model proyek dan service matching sudah lolos setelah patch.

### 1. Rapikan Massal Semua Proyek
* Relasi project social media dan project articles sudah direkonsiliasi ulang berdasarkan keyword yang benar-benar cocok.
* Hasil pembersihan:
  * social media pivot yang dihapus: `206`
  * article pivot yang dihapus: `203`
* Setelah cleanup, sisa relasi ganda berkurang dan false positive lintas proyek jauh lebih minim.
* Validasi syntax PHP pada file matching tetap lulus setelah proses rapih massal.

### 1. Audit Massal False Positive Social Media
* Audit relasi social media menemukan item Wagub murni yang sebelumnya masih ikut proyek `Gubernur Kaltim`.
* Relasi yang salah untuk item tersebut sudah dibersihkan, sehingga dashboard sekarang hanya menampilkan proyek yang memang relevan.
* Validasi syntax PHP pada file matching tetap lulus setelah patch.

### 1. Matching Sosial Diperketat ke Keyword/Hashtag Eksplisit
* Pencocokan social media sekarang dibatasi ke keyword/hashtag eksplisit dari payload sumber, bukan lagi ke narasi caption yang bebas.
* Tujuannya mencegah kasus seperti konten yang hanya menyebut `Wakil Gubernur Kalimantan Timur` ikut nyasar ke proyek `Gubernur Kaltim` hanya karena ada kata wilayah yang mirip.
* Jalur cross-link existing content juga disamakan supaya data lama tidak terus mengulang false positive yang sama.
* Validasi syntax PHP pada file terkait masih perlu dijalankan setelah patch terakhir.

### 1. Interval Actor Instagram Ditampilkan di Modal
* Modal actor Instagram sekarang menampilkan field `Interval Actor (Menit)` agar jeda run bisa diatur langsung dari panel admin.
* Nilai ini tetap tersimpan ke kolom `interval_minutes` dan dipakai oleh scheduler/guard seperti biasa.
* Validasi syntax PHP pada `resources/views/livewire/admin/apify-configuration.blade.php` lulus.

---

### 1. Manual Dispatch Instagram Bisa Dipaksa Ulang
* Command `scraping:run-apify` sekarang mendukung flag `--force-dispatch` untuk tes manual satu proyek.
* Flag ini hanya untuk bypass guard duplikasi/stale-safe saat pengujian, tidak mengubah scheduler normal.
* Tes forced dispatch project `Gubernur Kaltim` berhasil mengirim job Instagram lagi setelah sebelumnya tertahan oleh guard.
* Validasi syntax PHP pada file command dan job lulus.

---

### 1. Tes Manual Instagram 1 Proyek dengan Actor Canonical
* Tes dispatch manual untuk project `Gubernur Kaltim` berhasil mengirim 1 job Instagram ke worker.
* Actor yang dipakai sudah mengarah ke `apify/instagram-hashtag-scraper`.
* Keyword proyek dikirim dari data asli proyek, lalu dinormalisasi di payload saat build input Apify.
* Validasi syntax PHP dan eksekusi artisan manual lulus.

---

### 1. Record Instagram Lama Dihapus dari Database
* Record `apify/instagram-search-scraper` sudah dihapus dari tabel `apify_actors`.
* Saat ini hanya actor canonical `apify/instagram-hashtag-scraper` yang tersisa untuk Instagram.
* Validasi query database menunjukkan tidak ada lagi referensi aktif ke slug lama.
* Validasi syntax PHP pada file terkait lulus.

---

### 1. Instagram Actor Canonical Diganti ke Hashtag Scraper
* Actor Instagram aktif sekarang dipastikan memakai `apify/instagram-hashtag-scraper`.
* Record lama `apify/instagram-search-scraper` dinonaktifkan agar scheduler dan worker tidak salah memanggil actor lama lagi.
* Guard registry ditambahkan supaya actor Instagram non-canonical otomatis dinonaktifkan saat sinkronisasi.
* Validasi syntax PHP pada file terkait lulus.

---

### 1. Detail Proyek Dashboard Menampilkan Hashtag
* Halaman detail proyek di dashboard sekarang menampilkan keyword asli dan versi hashtag berdampingan.
* Tabel kata kunci juga menampilkan label hashtag agar user mudah memverifikasi bentuk yang dipakai saat payload Apify dikirim.
* Validasi syntax PHP pada `resources/views/components/⚡media-dashboard.blade.php` lulus.

---

### 1. Input Proyek Disimpan Asli, Normalisasi Dipindah ke Payload
* Form proyek sekarang menyimpan keyword/topik dalam bentuk asli ke database, bukan hasil hashtag yang sudah dinormalisasi.
* Normalisasi tetap dilakukan saat payload Apify dibentuk, sehingga output ke Apify tetap bersih dan konsisten tanpa mengubah data sumber proyek.
* Jalur simpan di komponen proyek utama dan legacy sudah disamakan agar tidak ada route simpan yang masih memaksa hashtag ke database.
* Validasi syntax PHP pada file terkait lulus.

---

### 2. Normalisasi Payload Instagram Disamakan dengan Form Proyek
* Payload Instagram di `ApifyActor` sekarang menormalisasi keyword dengan aturan yang sama seperti form proyek:
  * trim spasi
  * hapus tanda `#`
  * hapus apostrof / tanda kutip
  * buang karakter non huruf/angka yang tidak diperlukan
  * hilangkan spasi agar hasil akhir tetap bersih
* Tujuan perubahan ini adalah memastikan query Instagram yang dikirim ke Apify konsisten dengan hasil hashtag yang sudah dibentuk saat proyek disimpan.
* Validasi syntax PHP pada `app/Models/ApifyActor.php` lulus.

---

## Log Aktivitas Terbaru (16 Juli 2026)

### 1. Pengayaan Penilaian AI Sosial Media
* Payload AI untuk social media sekarang ikut membawa konteks media yang lebih kaya:
  * `media_type`
  * `media_url`
  * `thumbnail_url`
  * `author_name`
  * `author_url`
  * metrik engagement seperti likes, comments, shares, views, dan followers
* AI sosial sekarang diarahkan untuk menilai postingan berdasarkan link dan konteks media, bukan caption saja.
* Prompt default `Analisis Medsos Utama` diperbarui agar langsung memakai konteks media dan engagement saat analisis.
* Schema output sosial dipertahankan kompatibel dengan pipeline lama, sehingga field reach legacy tetap ada sambil mendukung konteks media baru.
* Validasi syntax PHP untuk file terkait sudah lulus.

---

## Log Aktivitas Terakhir (13 Juli 2026)

### 1. Perbaikan Bug Scraper AI (`NewsSourceSuggestionTester.php`)
* **Bug Sitemap Index:** Menambahkan logika rekursif untuk masuk dan mengunduh child sitemap jika mendeteksi sitemap bertipe index.
* **Bug CSS Selector ke XPath:** Menulis ulang fungsi parser CSS selector menjadi XPath yang jauh lebih robust, mendukung multi-class, attribute selector, comma-separated selector, dan BEM style (`__`).
* **Bug URL Rejection:** Mengubah mekanisme filter link dari string matching kasar menjadi pengecekan segmen path yang presisi, mencegah terbuangnya link artikel berita valid.
* **Bug Protocol-Relative URL:** Menghilangkan bug normalize URL yang merusak format link relatif protokol (`//`).

### 2. Antarmuka Admin & Database (`news-sources.blade.php`)
* **Urutan Baris Tabel:** Memperbaiki urutan nomor baris agar tetap runtut menyesuaikan halaman pagination (tidak reset kembali ke 1 di halaman kedua).
* **Ukuran Pagination:** Memperkecil elemen pagination sebanyak 20% agar tampilan dashboard lebih seimbang dan bersih.
* **Pembersihan Database:** Menghapus duplikasi data saran portal, menghapus record `samarindatv.com` karena server down secara permanen, dan mengupdate status portal aktif lainnya menjadi `verified` di database PostgreSQL.

### 3. Aturan Batas Apify Sosial
* Facebook, Instagram, dan TikTok dibatasi maksimal **50 item per run per proyek**.
* Batas 50 dihitung sebagai total hasil satu run, **bukan per keyword**.
* Jika hasil sudah mencapai 50, sistem harus menghentikan run Apify supaya token tidak terus terpakai.
* Status internal tetap dicatat sebagai **selesai/sukses**, bukan gagal, walau proses dihentikan di sisi Apify.

### 4. Maximum Cost Per Run Apify
* Menambahkan kolom `maximum_cost_per_run_usd` pada `apify_actors`.
* Nilai production aktif:
  * Facebook: `$0.20/run`
  * Instagram: `$0.15/run`
  * TikTok: `$0.15/run`
* Saat actor dijalankan, sistem mengirim parameter Apify `maxTotalChargeUsd` jika nilai lebih dari 0.
* Test PASS:
  * `ApifyDispatchTest`: 4 tests / 11 assertions
  * `AdminApifyConfigurationTest`: 5 tests / 17 assertions

---

## Log Aktivitas Terbaru (14 Juli 2026)

### 1. Perbaikan Apify Cost Limit Partial Success
* Token Apify production sudah tersimpan di database dan status koneksi `connected`.
* Worker Apify direstart agar memakai kode terbaru.
* Facebook actor tetap aktif; Instagram dan TikTok tetap nonaktif.
* Run Facebook yang berhenti karena batas biaya `$0.20` sekarang diproses sebagai **selesai sebagian** jika dataset sudah berisi item.
* Data yang sudah terkumpul tetap disimpan, dedupe tetap berjalan, dan pipeline AI/notification tetap mengikuti flow normal.
* Status admin Facebook diperbarui menjadi sukses dengan catatan: batas biaya Apify tercapai, data yang sudah terkumpul tetap diproses.

### 2. Guard Payload Limit Per Keyword
* Untuk Facebook dengan banyak keyword, total limit database dibagi ke jumlah keyword sebelum dikirim ke Apify.
* Contoh: limit total 50 dan 3 keyword dikirim sebagai `maxPosts=17`, bukan 50 per keyword.
* Tujuan: mencegah actor sosial mengambil data jauh melebihi batas total per proyek.

### 3. QA
* `ApifyDispatchTest` PASS: 7 tests / 20 assertions.
* Queue production final bersih:
  * `apify=0`
  * `ai-analysis=0`
  * `ai-backfill=0`
  * `notification=0`
  * `failed_recent=0`

### 4. Guard Apify 15 Menit
* Worker Apify diperbarui agar menunggu respons maksimal 15 menit.
* Jika run Apify masih berjalan setelah 15 menit, sistem mengirim abort aman.
* Setelah abort aman:
  * dataset berisi item: data tetap diambil, disimpan, dan run dicatat sebagai sukses sebagian;
  * dataset kosong: state masuk `retry_wait` dengan `next_retry_at`, sehingga tidak membuat job baru sampai waktu tunggu lewat.
* Worker Apify direcreate dengan command baru:
  * `queue:work redis --queue=apify --sleep=5 --tries=2 --timeout=1000`
* Test PASS:
  * `ApifyDispatchTest`: 9 tests / 28 assertions

### 5. Penyamaan Aturan Limit Instagram dan TikTok
* Instagram dan TikTok sekarang mengikuti aturan aman yang sama seperti Facebook.
* Semua keyword project tetap dikirim dalam satu payload actor.
* Total limit dari database dibagi ke jumlah keyword:
  * Facebook: `maxPosts`
  * Instagram: `searchLimit`
  * TikTok: `maxItems`
* Dataset yang dikembalikan Apify dipotong lagi di aplikasi maksimal sesuai limit actor sebelum disimpan, masuk AI, atau masuk notifikasi.
* Contoh production Wagub Kaltim:
  * keyword: `Wakil Gubernur Kalimantan Timur`, `wagub kaltim`, `Seno Aji`
  * limit actor: `50`
  * payload per keyword: `17`
* Test PASS:
  * `ApifyDispatchTest`: 11 tests / 35 assertions

### 6. Batas Biaya Actor Sosial Disamakan
* Default registry actor bawaan disamakan dengan nilai production:
  * Facebook: `$0.20/run`
  * Instagram: `$0.15/run`
  * TikTok: `$0.15/run`
* Parameter resmi Apify yang dikirim adalah `maxTotalChargeUsd`.
* Jika actor dibuat ulang dari registry, batas biaya tidak kembali ke angka lama.

### 7. Perbaikan Label Scan Portal Kartu Proyek
* Audit Wagub Kaltim menunjukkan portal tidak stuck: log portal berjalan dan memproses keyword, tetapi hasilnya `reused` sehingga tidak membuat/ubah relasi `project_articles`.
* Kartu proyek sebelumnya memakai timestamp artikel/relasi terbaru sehingga terlihat seperti “Update Portal 3 jam yang lalu”.
* Tampilan kartu proyek sekarang memakai label **Scan Portal** dan membaca waktu scan terakhir dari `storage/logs/portal-manual.log` event `[Portal] Project keyword processed.`.
* Data artikel tidak diubah; ini murni perbaikan indikator UI agar tidak menyesatkan.

### 8. Penajaman Tab Wawasan
* Tab Wawasan ditambah blok pendukung keputusan:
  * Top Isu Negatif
  * Pemicu Risiko
  * Perubahan Sentimen
  * Rekomendasi Respons
* Semua blok memakai data AI resmi dan mengikuti filter aktif.
* Pemicu Risiko menampilkan artikel/post `high/critical`, alasan risiko, sumber, jangkauan, tanggal, dan link asli bila tersedia.
* QA teknis:
  * Syntax Blade/PHP PASS.
  * View cache dibersihkan.
  * Queue production tetap bersih.

### 9. Perbaikan Modal Aksi Proyek
* Aksi nonaktifkan dan aktifkan kembali proyek sekarang memakai modal konfirmasi internal, bukan `wire:confirm` bawaan browser.
* Scroll lock halaman proyek disatukan di root Alpine agar body tidak tertinggal `overflow: hidden` setelah modal ditutup/Livewire re-render.
* Setiap aksi proyek menampilkan toast kanan atas selama 3 detik.
* Data sumber tetap aman: nonaktif/restore hanya mengubah status proyek, bukan menghapus portal/sosmed.

### 10. Auto-Matching Data Lama untuk Project Baru
* Project baru sekarang otomatis mencocokkan artikel dan sosial media lama berdasarkan keyword setelah dibuat.
* Update keyword project juga memicu pencocokan ulang data lama.
* Proses ini tidak scraping, tidak retry AI, dan tidak mengirim Telegram.
* Project `wagub` berhasil ditautkan ke data lama:
  * artikel relasi: `184`
  * sosial media relasi: `80`
  * display-ready: `181`
* Project `Iswandi` belum punya match di database lama, sehingga tetap `0`.

### 11. Aturan Filter Keyword Discovery Dilonggarkan
* Keputusan: hasil pencarian dari Google News, portal manual, dan Apify sosial media dipercaya sebagai kandidat konteks project.
* Sistem tidak lagi mewajibkan semua kata keyword project muncul ulang di judul/deskripsi/konten/caption.
* Filter yang tetap wajib: URL/post ID valid, konten/caption tidak kosong, bukan duplikat, bukan konten terlalu pendek, relasi project benar, serta limit/biaya actor dipatuhi.
* Alasan: keyword seperti `iswandi dprd samarinda` bisa menghasilkan berita Google News/portal yang relevan walau judul tidak selalu menyebut semua kata.

### 12. Validasi Aturan Instagram dan TikTok Mengikuti Facebook
* Audit production:
  * Facebook aktif dengan actor `scrapeflow/facebook-posts-search-scraper`.
  * Instagram masih nonaktif dengan actor `apify/instagram-search-scraper`.
  * TikTok masih nonaktif dengan actor `paul_44/tiktok-search`.
* Aturan antrian dan safety untuk Facebook/Instagram/TikTok sudah sama:
  * satu job per project + actor + interval;
  * semua keyword project dikirim sekali jalan;
  * total limit 50 dibagi per keyword sebelum dikirim ke actor;
  * dataset dipotong lagi maksimal sesuai limit actor;
  * `maxTotalChargeUsd` dikirim sebagai batas biaya;
  * timeout 15 menit diproses sebagai sukses sebagian jika dataset sudah ada;
  * cooldown/retry mencegah job baru menumpuk.
* Payload tetap berbeda sesuai aturan actor:
  * Facebook: `searchQueries[]` + `maxPosts`;
  * Instagram: `search` string dipisah koma + `searchLimit`;
  * TikTok: `keywords[]` + `maxItems`.
* Patch worker Apify:
  * `App\Jobs\ApifyScrapingJob` sekarang mempercayai hasil pencarian Apify sosial media dan tidak lagi mewajibkan caption mengulang keyword project.
  * Filter kualitas tetap aktif: URL/post ID valid, konten tidak kosong/noise, dedupe, limit, biaya, dan relasi project.
* Test PASS:
  * `ApifyDispatchTest`: 13 tests / 44 assertions.

### 13. Perbaikan Google News Resolver dan Status Log
* Audit Google News menunjukkan banyak kandidat ditolak karena URL masih berupa wrapper `news.google.com/rss/articles/...`.
* Resolver production diverifikasi memakai `GoogleNewsUrlDecoderService` + `/opt/google-news-venv/bin/python` + `googlenewsdecoder==0.1.7`.
* Test service berhasil mengubah URL Google News menjadi link media asli, contoh `potretpimpinan.kaltimprov.go.id/...`.
* Run kecil project `walikota samarinda`:
  * command: `scraping:run-news --project-id=4 --keyword="walikota samarinda" --discovery-mode=google_news --limit=10 --no-telegram`
  * hasil: `inserted=5`, `reused=5`, `rejected_invalid_url=0`, `error_count=0`
  * kandidat berhasil resolve ke domain asli seperti `korankaltim.com`, `kaltim.antaranews.com`, `kompas.tv`, `diskominfo.samarindakota.go.id`.
* Halaman Log Sistem diperbaiki agar konteks JSON `"error":0` tidak dibaca sebagai gagal.
* Queue final bersih:
  * `ai-analysis=0`
  * `ai-backfill=0`
  * `apify=0`
  * `scraping=0`
  * `notification=0`
  * `failed_recent=0`

### 14. Scheduler Guard: Jangan Buat Job Saat Antrean Masih Aktif
* Ditambahkan `App\Services\SchedulerQueueGuard`.
* Scheduler portal dan Apify sekarang mengecek queue/proses aktif sebelum membuat job baru.
* Portal memakai lock global `news:run-active` dan deteksi proses `scraping:run-news` agar scan sebelumnya selesai dulu sebelum jadwal berikutnya berjalan.
* Apify mengecek queue Redis dan `apify_dispatch_states` status `queued`, `processing`, dan `retry_wait` yang belum waktunya.
* Jika proses sebelumnya belum selesai, scheduler skip dan mencatat alasan di log.
* Aturan terkunci: interval scheduler adalah waktu cek kondisi, bukan waktu wajib membuat job baru.

### 15. Urutan Scheduler Project Dibuat Adil
* Portal sekarang mengurutkan project berdasarkan scan terakhir dari log portal:
  * belum pernah diproses lebih dulu;
  * lalu yang paling lama tidak diproses;
  * lalu `created_at` dan `id`.
* Apify/sosmed memakai `SocialProjectScrapePriorityService` dengan aturan sama berdasarkan waktu relasi sosial terakhir.
* Simulasi urutan saat validasi:
  * Portal: Iswandi → Wagub Kaltim → walikota samarinda → Gubernur Kaltim → wagub.
  * Sosmed: Iswandi → walikota samarinda → Gubernur Kaltim → Wagub Kaltim → wagub.
* Tujuan: project baru atau project yang lama belum update tidak tertinggal oleh project lama yang terus diproses.

### 16. TikTok Limit Total Dibagi per Keyword
* TikTok sekarang mengikuti aturan Facebook/Instagram: angka `maxItems` dari form actor dianggap sebagai total target satu run per project, bukan per keyword.
* Sistem membagi `maxItems` berdasarkan jumlah keyword sebelum payload dikirim ke Apify.
* Contoh production project `Gubernur Kaltim`: `maxItems=15` dan 3 keyword menghasilkan payload `maxItems=5` per keyword.
* Tujuan: hasil TikTok tetap terkendali, biaya Apify tidak melebar, dan perilaku limit konsisten antar Facebook, Instagram, dan TikTok.

### 17. Modal Apify Dibuat Lebih Jelas per Actor
* Modal admin Apify sekarang menampilkan aturan isi yang lebih tegas untuk tiap actor.
* Facebook menegaskan `searchQueries` sebagai array dan `maxPosts` sebagai batas per keyword.
* TikTok menegaskan `keywords` sebagai array dan `maxItems` sebagai total target yang dibagi sistem per keyword.
* Penjelasan TikTok yang sebelumnya tertukar dengan Instagram sudah diluruskan agar admin tidak salah isi payload.

### 18. Ringkasan Modal Apify Dibuat Lebih Singkat
* Teks panduan di modal Apify disederhanakan supaya lebih mudah dipahami admin.
* Facebook, TikTok, dan Instagram kini memakai kalimat singkat: mana yang wajib diisi, mana yang diatur sistem, dan mana yang opsional.
* Tujuan perubahan ini adalah mengurangi kebingungan saat mengisi form actor tanpa mengubah aturan payload yang sudah terkunci.

### 19. Status Apify ABORTED karena Batas Biaya Dianggap Selesai Sebagian
* Run Apify yang berhenti karena batas biaya sekarang diperlakukan sebagai selesai sebagian, bukan error aktif.
* Pesan UI internal diserap supaya tidak memunculkan alarm palsu ketika data sudah terkumpul dan tetap diproses.
* Tujuannya agar run yang berhenti aman karena biaya tetap menghasilkan data yang bisa dipakai, tanpa mengganggu status sistem lain.

### 20. Modal TikTok Mengikuti Payload Dokumentasi
* Panduan TikTok di modal Apify disesuaikan lagi supaya mengikuti payload actor yang dipakai manual.
* Field `keywords` ditegaskan sebagai array dari keyword proyek, sedangkan `maxItems` dijelaskan sebagai batas total yang dibagi sistem ke tiap keyword.
* Tujuannya agar admin langsung paham isi payload tanpa salah mengira `maxItems` sebagai batas per keyword.

### 21. Perbaikan Navigasi Detail Proyek (Tanpa Livewire & Scroll-free)
* Mengubah tombol **Detail Proyek** pada daftar proyek agar menggunakan tautan navigasi standar browser (`<a>` dengan query parameter `?project_id=X&tab=cGVueWVidXRhbg==`), bukan Livewire state handler (`wire:click="$set('projectId', ...)"`).
* Memperbarui metode `mount()` di [ProjectsList.php](file:///Users/unity/Documents/proyek%20baru/app/Http/Livewire/ProjectsList.php) untuk membaca parameter `project_id` secara langsung dari URL query.
* Perubahan ini memaksa halaman langsung membuka menu **Penyebutan** secara default, menghilangkan efek auto-scroll ke atas yang mengganggu, dan mempercepat navigasi transisi secara alami.

### 22. Audit Log Payload Apify Disamakan
* Worker `App\Jobs\ApifyScrapingJob` sekarang menulis log audit payload final ke `social-media.log` untuk Facebook, Instagram, dan TikTok dengan struktur yang sama.
* Log baru memakai event:
  * `[Social] Actor payload prepared.`
  * `[Social] Actor run started.`
* Field utama yang dicatat:
  * `project_id`, `project_name`
  * `actor_id`, `actor_name`, `actor_slug`
  * `keywords`, `keyword_count`
  * `limit_total_requested`
  * `payload_limit_field`
  * `payload_limit_value`
  * `payload`
  * `run_id`, `dataset_id`
* Verifikasi production berhasil untuk TikTok project `Gubernur Kaltim`:
  * `limit_total_requested=5`
  * `payload_limit_field=maxItems`
  * `payload_limit_value=2`
  * payload final tercatat utuh di `social-media.log`

### 23. Pembersihan Input Ganda pada Form Aktor Apify
* Menghapus input **Rentang Waktu** dan **Max Items** yang redundan pada bagian *Konfigurasi Khusus TikTok* di file [apify-configuration.blade.php](file:///Users/unity/Documents/proyek%20baru/resources/views/livewire/admin/apify-configuration.blade.php).
* Memperbarui metode `buildTikTokOutputMapping()` di [ApifyConfiguration.php](file:///Users/unity/Documents/proyek%20baru/app/Livewire/Admin/ApifyConfiguration.php) untuk memetakan parameter payload `maxItems` dan `dateRange` langsung dari input global `defaultLimit` (Batas Hasil) dan `range_mode` (Target Rentang Waktu).
* Perubahan ini mengeliminasi kebingungan input ganda dan memastikan sinkronisasi 100% antara isian panel admin dengan payload final yang dikirim ke Apify.

### 24. Skema `build` Dibenahi untuk Simpan Actor
* Menemukan sumber error `SQLSTATE[42703]` saat menyimpan actor: payload masih menulis kolom `build`, sementara skema tabel `apify_actors` di environment ini belum konsisten.
* Ditambahkan migrasi penyangga [2026_07_18_010000_ensure_build_column_exists_on_apify_actors_table.php](/Users/unity/Documents/proyek%20baru/database/migrations/2026_07_18_010000_ensure_build_column_exists_on_apify_actors_table.php) agar kolom `build` tersedia kembali jika belum ada.
* Jalur simpan admin dan registry tetap dijaga kompatibel dengan pengecekan skema, supaya tidak error di environment yang belum selesai migrasinya.
* Percobaan `php artisan migrate --force` dari shell ini belum bisa menyentuh database karena host Postgres `postgres` tidak dapat di-resolve dari environment lokal.

### 25. Pivot Sosial Tidak Lagi Auto-Lolos Tanpa Keyword Cocok
* `ContentMatchingService` disempitkan supaya `discoveryProjectId` tidak otomatis menempel ke item sosial.
* Pivot proyek untuk social media sekarang hanya dibuat jika item benar-benar lolos keyword matching eksplisit.
* Ditambahkan test untuk memastikan item sosial yang tidak match tidak ikut masuk ke project penemu.

### 26. Label Dashboard TikTok Diseragamkan
* Label sumber sosial di dashboard proyek yang masih memakai penulisan lama `Tiktok` diseragamkan menjadi `TikTok`.
* Perhitungan ringkasan dan filter dashboard sekarang memakai label yang konsisten, supaya hitungan tidak terpecah karena beda casing.

### 27. Batas Hasil Aktif Tidak Lagi Diposisikan sebagai Pembatas Otomatis TikTok
* Copy di modal Apify diubah supaya field **Batas Hasil Aktif** tidak lagi mengklaim menyinkronkan limit TikTok secara paksa ke payload actor.
* Nilai tersebut sekarang dijelaskan sebagai konfigurasi actor, bukan pembatas otomatis payload.

### 28. Komentar Migrasi Sejarah TikTok Dirapikan
* Komentar no-op pada migrasi pembersihan historis TikTok diubah menjadi istilah generik `social` agar jejak teks TikTok di bagian komentar historis tidak terlalu dominan.
* Logika migrasi tetap dipertahankan, hanya narasi komentarnya saja yang disederhanakan.

### 29. Batas Hasil Aktif Jadi Input Manual
* Field **Batas Hasil Aktif** di modal Apify tidak lagi diisi otomatis dari data default registry.
* Form baru membuka field ini dalam keadaan kosong, sehingga admin wajib mengisinya sendiri.
* Sinkronisasi otomatis terhadap platform dihapus agar nilai tersebut benar-benar berasal dari input user.

### 30. TikTok Tidak Lagi Mewarisi Limit Lama Saat Edit
* Modal edit TikTok tidak lagi mengisi **Batas Hasil Aktif** dari `default_limit` lama yang tersimpan di database.
* Untuk TikTok, field ini dibiarkan kosong saat dibuka agar admin memasukkan nilai baru secara manual.

### 31. Binding `Batas Hasil Aktif` Dibuat Lebih Stabil
* Field **Batas Hasil Aktif** di modal Apify dipindah ke `wire:model.defer` supaya angka yang diketik tidak hilang saat re-render Livewire.
* Perubahan ini membuat input angka lebih stabil, terutama saat platform berganti atau modal dirender ulang.

### 32. Edit TikTok Menampilkan Nilai Tersimpan Lagi
* Modal create TikTok tetap membuka **Batas Hasil Aktif** dalam keadaan kosong.
* Modal edit TikTok sekarang menampilkan kembali nilai `default_limit` yang sudah tersimpan, supaya admin bisa melihat dan mengubah angka yang lama.

### 33. Footer Modal Apify Diperbaiki
* Footer modal Apify dikembalikan ke layout normal non-sticky agar tidak bertabrakan dengan area scroll internal modal.
* Struktur modal tetap memakai satu area body yang dapat di-scroll, sementara footer tetap menempel di bawah modal tanpa efek layout yang membuat tampilan terlihat hancur.

### 34. Footer Modal Apify Dikunci di Bawah Card
* Footer modal Apify diposisikan `absolute` di bawah card modal agar selalu terlihat saat isi form di-scroll.
* Area body diberi ruang bawah tambahan supaya konten terakhir tidak tertutup footer yang fix.

### 35. Footer Modal Apify Dikembalikan ke Flex-Fixed
* Footer modal Apify dikembalikan ke susunan flex biasa agar tetap nempel di bawah modal tanpa terpotong di layar kecil.
* Yang di-scroll hanya isi form, sementara footer tetap terlihat penuh sebagai bagian terakhir dari card modal.

### 36. Filter TikTok Dashboard Dinormalisasi
* Perhitungan jumlah sumber di dashboard media dinormalisasi dengan `lower(...)` supaya `TikTok` dan `Tiktok` dibaca sebagai sumber yang sama.
* Filter sumber juga disesuaikan agar data TikTok lama dan baru tetap masuk ke hitungan yang sama di dashboard proyek.

### 37. Agregasi Sumber TikTok Dibuat Canonical
* Daftar sumber di dashboard sekarang mengelompokkan `Tiktok` ke label canonical `TikTok` saat agregasi.
* Ini mencegah angka sumber TikTok tampil `0` hanya karena perbedaan casing pada `source_name` di database.

### 38. Like/Metric Negatif Dinormalisasi ke Nol
* Nilai metrik sosial seperti like, komentar, share, view, dan followers kini dikunci minimum `0` saat disimpan dari hasil scraper.
* Data lama yang sempat tersimpan sebagai `-1` sudah dibersihkan ke `0` agar UI tidak menampilkan like negatif.

### 39. Key TikTok Panel Filter Diseragamkan
* Komponen filter dashboard masih membaca key `Tiktok` di sidebar, sementara backend sudah mengirim `TikTok`.
* Key pilihan dan label count di `resources/views/components/⚡filter-items.blade.php` diseragamkan ke `TikTok` supaya angka TikTok tidak lagi tampil `0`.

### 40. Default Checkbox Sumber TikTok Disamakan
* Default pilihan sumber di komponen project list masih memakai `Tiktok`, sehingga checkbox awal bisa tidak sinkron dengan data backend.
* Default state dan nilai checkbox disamakan ke `TikTok` agar daftar sumber tampil tercentang sesuai isi data saat halaman dibuka.

### 41. Keyword Match Social Memakai Isi Konten
* Filter keyword pasca-fetch di job Apify sekarang juga mengecek `content` untuk Facebook, Instagram, dan TikTok.
* Ini mencegah item sosial yang relevan terbuang hanya karena metadata hashtag/term tidak lengkap meskipun caption aslinya cocok.

### 42. Registry TikTok Diverifikasi Ulang
* Registry default TikTok sudah dicek ulang agar tetap menunjuk ke `clockworks/tiktok-hashtag-scraper` dengan payload `hashtags` dan `resultsPerPage`.
* Audit ini memastikan seed/default yang dipakai app tetap selaras dengan payload runtime yang dikirim worker ke Apify.

### 43. TikTok MaxItems Dipisah per Actor
* Modal TikTok sekarang menampilkan `Batas Hasil Aktif` sebagai `maxItems` per aktor, bukan lagi istilah `resultsPerPage`.
* Teks bantuan dan payload TikTok sudah diseragamkan agar nilai limit berasal dari konfigurasi tiap aktor dan bisa berbeda antar actor.

### 44. Audit Payload TikTok Mengikuti `maxItems`
* Jalur audit worker TikTok di `ApifyScrapingJob` sekarang membaca `maxItems` sebagai field limit yang resmi.
* Ini mencegah log dan inspeksi payload TikTok kembali menampilkan label lama `resultsPerPage` saat run dijalankan.

### 45. TikTok Wajib Kirim `hashtags`
* Payload TikTok sekarang mengirim `hashtags` sebagai field input yang diwajibkan actor `clockworks/tiktok-hashtag-scraper`.
* Field limit tetap memakai `maxItems`, sehingga input runtime sesuai validasi Apify sekaligus tetap per-aktor.

### 46. Filter Konten TikTok Dilonggarkan
* Validasi konten sosial yang terlalu pendek sekarang dilonggarkan khusus TikTok dari 30 karakter menjadi 8 karakter.
* Tujuannya supaya post TikTok yang pendek tapi sudah lolos keyword match tidak ikut terbuang sebelum disimpan ke database.

### 47. Filter IG dan TikTok Dipaksa Berbasis Hashtag
* Jalur pencocokan hasil sosial untuk Instagram dan TikTok sekarang memakai `hashtags`/`tags` dan hashtag eksplisit di konten, bukan narasi penuh.
* Ini membuat data yang tampil benar-benar mengikuti hashtag proyek, sehingga hasil 100 dari Apify tidak lagi banyak gugur hanya karena caption mengandung kata yang tidak relevan.

### 48. Social Matching Tidak Lagi Mengandalkan Author Name
* Pencocokan project untuk item sosial tidak lagi memakai `author_name` sebagai bagian dari teks pemicu.
* Ini mengurangi false positive pada detail project IG/TikTok agar yang tampil benar-benar berasal dari hashtag yang cocok.

### 49. Edit Project Melepas Social Hashtag Lama
* Saat project diubah, relasi social media project sekarang disinkronkan ulang berdasarkan keyword terbaru.
* Hashtag/social item lama yang tidak cocok lagi otomatis dilepas dari project agar detail proyek hanya menampilkan hasil yang relevan.

### 50. Sinkronisasi Edit Project Dipasang di UI
* Jalur simpan edit project di komponen UI proyek sekarang langsung memanggil resync social content setelah update topics.
* Dengan begitu, saat user klik simpan, hashtag lama yang sudah dihapus langsung hilang dari detail project tanpa menunggu proses manual.

### 51. Fetch Hasil Apify Tidak Lagi Dibatasi
* Worker `ApifyScrapingJob` sekarang mengambil seluruh item dataset dari Apify tanpa `limit` di query fetch.
* Pembatas yang tersisa hanya dipakai saat mengirim payload ke Apify, bukan saat aplikasi menerima hasil dari dataset.
* Jalur stop-early untuk social platform juga dimatikan supaya hasil TikTok/Instagram/Facebook yang valid tidak terpotong sebelum disimpan.

### 52. Hashtag Object Instagram dan TikTok Dibaca Benar
* Helper matching social sekarang membaca array `hashtags` dan `tags` yang berisi objek `name`, `tag`, `text`, atau `value`.
* Ini mencegah item Instagram/TikTok gugur hanya karena struktur JSON Apify menyimpan hashtag sebagai objek, bukan string polos.

### 53. Matching Facebook Diperlonggar ke Token Keyword
* Pencocokan Facebook sekarang punya fallback token-match jika frasa utuh tidak ditemukan.
* Ini membantu item seperti `Bupati Kutai Kartanegara (Kukar)` tetap lolos saat project memakai keyword `bupati kukar`, tanpa mengubah Facebook menjadi hashtag-based.
