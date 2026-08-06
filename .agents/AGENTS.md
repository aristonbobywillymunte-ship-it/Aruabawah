# Aturan Kerja AI (Workspace Rules)

Daftar file di bawah ini wajib dibaca dan diikuti oleh setiap AI yang bekerja di proyek ini.

## File yang Wajib Dibaca Sebelum Mulai Bekerja
1. **[AI_CONTEXT.md](file:///Users/unity/Documents/proyek%20baru/AI_CONTEXT.md)**
   * Berisi arsitektur sistem, perilaku sistem, serta core files proyek.
2. **[.agents/AGENTS.md](file:///Users/unity/Documents/proyek%20baru/.agents/AGENTS.md)**
   * Berisi aturan kerja, standarisasi kode, dan instruksi spesifik proyek ini.

## File yang Wajib Diupdate Setelah Selesai Bekerja
Setiap kali selesai melakukan modifikasi penting atau tugas besar:
1. **[AI_CONTEXT.md](file:///Users/unity/Documents/proyek%20baru/AI_CONTEXT.md)** (Jika ada perubahan perilaku sistem, penambahan library, atau modifikasi alur kerja).
2. **[.agents/AGENTS.md](file:///Users/unity/Documents/proyek%20baru/.agents/AGENTS.md)** (Jika ada penambahan aturan baru atau perubahan panduan penulisan kode).
3. **[PROJECT_PROGRESS.md](file:///Users/unity/Documents/proyek%20baru/PROJECT_PROGRESS.md)** (Setiap selesai mengerjakan fitur atau tugas besar untuk mencatat log progress pengerjaan).

## File yang Wajib Diupdate Terkait QA / Audit / Hasil Uji
Jika pengerjaan melibatkan testing, verifikasi fungsionalitas, audit performa, atau audit database:
1. **[docs/qa/FULL_BACKEND_QA_REPORT.md](file:///Users/unity/Documents/proyek%20baru/docs/qa/FULL_BACKEND_QA_REPORT.md)**
2. **[docs/qa/PRODUCTION_READINESS_QA_REPORT.md](file:///Users/unity/Documents/proyek%20baru/docs/qa/PRODUCTION_READINESS_QA_REPORT.md)**
3. Dokumen QA spesifik lainnya di dalam folder `docs/qa/` jika relevan.

---

## Aturan Singkat Pengembang AI:
* **Ubah perilaku sistem:** Update `AI_CONTEXT.md` dan `AGENTS.md`
* **Selesai kerja besar:** Update `PROJECT_PROGRESS.md`
* **Hasil audit/tes:** Update file QA terkait di folder `docs/qa/`

## Aturan Sosial Media Apify
* Gunakan batas maksimal 50 item per run untuk Facebook, Instagram, dan TikTok.
* Hitung 50 sebagai total hasil satu run per proyek, bukan per keyword.
* Jangan buat run baru kalau actor masih cooldown atau run sebelumnya belum selesai.
* Kalau total hasil sudah mencapai 50, hentikan run Apify agar token tidak terus terpakai; catat hasilnya sebagai selesai/sukses di sistem internal, bukan gagal.
* `maximum_cost_per_run_usd` adalah batas biaya resmi yang dikirim ke Apify sebagai `maxTotalChargeUsd`. `cost_reference` hanya referensi/estimasi, jangan dipakai sebagai batas biaya run.
* Setelah dataset Apify diambil, sistem wajib memotong jumlah item yang diproses maksimal sesuai limit actor agar data yang masuk DB, AI, dan notifikasi tidak melebihi batas.
* Jika Apify berhenti karena `maxTotalChargeUsd` tetapi dataset sudah ada, jangan tandai sebagai gagal fatal. Simpan data yang sudah terkumpul, catat sebagai selesai sebagian, lalu lanjutkan pipeline normal.
* Untuk Instagram Comment Scraper (`apify/instagram-comment-scraper`), sistem wajib mendukung pengeditan input JSON secara manual oleh admin di UI Manajemen Scraper Medsos. Jangan overwrite atau timpa payload manual ini saat menyimpan pengaturan.
* Jika run Apify tidak memberi status akhir dalam 15 menit, worker harus abort aman, cek dataset, lalu:
  * dataset ada: simpan data dan catat sukses sebagian;
  * dataset kosong: catat `retry_wait`, beri `next_retry_at`, dan jangan membuat job baru sampai waktu tunggu lewat.
* Modal admin Apify wajib menampilkan ringkasan singkat per actor supaya admin paham aturan isi payload: Facebook `searchQueries` array + `maxPosts` per keyword, Instagram `search` string dipisah koma + `searchLimit`, TikTok `keywords` array + `maxItems` dibagi sistem per keyword.
* `maximum_cost_per_run_usd` diperlakukan sebagai batas biaya run (`maxTotalChargeUsd`) dan bukan biaya aktual.
* Log audit payload sosial media harus seragam di `social-media.log` untuk Facebook, Instagram, dan TikTok. Catat selalu payload final runtime yang benar-benar dikirim ke Apify, bukan hanya ringkasan scheduler.
 
## Aturan Tampilan Scan Portal
* Label kartu proyek untuk portal harus menunjukkan waktu **Scan Portal** terakhir, bukan sekadar waktu artikel/relasi proyek terakhir dibuat.
* Sumber scan terakhir adalah log `portal-manual.log` event `[Portal] Project keyword processed.`.
* Jika scan berjalan tetapi hasilnya hanya `reused`, label tetap boleh maju karena sistem memang sudah memeriksa portal.
* Jangan memalsukan `updated_at` artikel atau pivot `project_articles` hanya untuk membuat UI terlihat baru.
 
## Aturan Scheduler dan Antrean Scraping
* Interval scheduler hanya berarti “waktunya cek”, bukan “wajib kirim job baru”.
* Jangan membuat job portal/Apify baru jika proses sebelumnya masih berjalan, queue belum kosong, dispatch state masih `queued/processing`, atau actor masih `retry_wait/cooldown`.
* Gunakan `App\Services\SchedulerQueueGuard` untuk mengecek kondisi sebelum dispatch.
* Portal memakai lock `news:run-active` plus deteksi proses `scraping:run-news`; Apify memakai `apify_dispatch_states` dan ukuran queue Redis.
* Ketika menyeleksi kolom proyek di query scheduler (misalnya pada `RunNewsPortalScraping` atau command terkait), pastikan wajib menyertakan kolom kata kunci dan topik seperti `topics`, `context_keywords`, `exclude_keywords`, dan `sources`. Menghapus atau melupakan kolom ini di query `select()` akan menyebabkan sistem mendeteksi kata kunci kosong dan melompati seluruh proyek secara keliru.
* Urutan scheduler harus mengutamakan project yang belum pernah diproses, lalu project yang paling lama tidak diproses, lalu `created_at`/`id`.
* Portal memakai `NewsProjectScrapePriorityService`; Apify/sosmed memakai `SocialProjectScrapePriorityService`.
* Jika scheduler skip, tulis log yang jelas agar admin tahu proses dilewati karena pekerjaan sebelumnya belum selesai.

## Aturan Tab Wawasan
* Wawasan harus memakai hasil AI/data proyek asli, bukan angka dummy.
* Semua blok wawasan harus mengikuti filter aktif user.
* Jika menambah indikator baru, pastikan indikator menjawab kebutuhan keputusan: apa isu utama, apa pemicu risiko, apakah sentimen berubah, dan respons apa yang perlu dilakukan.
* Jangan membuat klaim “krisis” atau “aman” tanpa dukungan `risk_level`, `sentiment`, atau data agregat yang jelas.

## Aturan Project Baru
* Setelah project dibuat atau keyword project diubah, jalankan pencocokan data lama secara otomatis lewat `ContentMatchingService::matchExistingContentForProject()`.
* Jangan scraping ulang hanya untuk menampilkan data lama di project baru.
* Project detail memakai relasi pivot `project_articles` dan `project_social_media_items`; pastikan relasi dibuat otomatis dari keyword.

## Aturan Kepercayaan Hasil Pencarian
* Google News, portal manual, dan Apify sosial media sudah melakukan pencarian memakai keyword project.
* Jangan wajibkan semua kata keyword project muncul ulang di judul/deskripsi/konten/caption untuk menyimpan kandidat hasil pencarian.
* Tetap jaga filter dasar: URL/post ID valid, konten/caption tidak kosong, bukan duplikat, bukan konten terlalu pendek, relasi project benar, dan limit/biaya actor dipatuhi.
* Jangan mengembalikan filter keyword ketat kecuali user secara eksplisit meminta.

## Aturan Google News Resolver dan Log
* Google News harus diubah dari URL wrapper `news.google.com/rss/articles/...` menjadi URL media asli melalui `GoogleNewsUrlDecoderService`.
* Production memakai helper Python `/opt/google-news-venv/bin/python` dengan dependency `googlenewsdecoder==0.1.7`.
* Kalau Google News banyak `rejected` karena URL masih `news.google.com`, audit decoder/dependency dulu, bukan langsung mengubah filter kualitas.
* Di halaman Log Sistem, JSON `"error":0` tidak boleh dianggap gagal. Yang gagal hanya level error/warning atau nilai error positif.
