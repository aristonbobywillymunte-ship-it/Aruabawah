# Legacy Schema Map

Status: REFERENSI / WARISAN

Dokumen ini memetakan tabel dan jalur lama yang masih ada di database atau kode,
namun bukan lagi sumber utama untuk tampilan dashboard.

## Prinsip

1. Data global adalah sumber kebenaran utama.
2. Filter project menentukan data mana yang tampil.
3. Tabel legacy boleh tetap ada untuk maintenance, resync, atau kompatibilitas.
4. Relasi lama tidak boleh dianggap sebagai satu-satunya penentu hasil tampil.

## Tabel Legacy / Warisan

### `project_articles`
- Status: warisan schema.
- Fungsi lama: relasi artikel ke project.
- Pemakaian sekarang:
  - masih dipakai sebagian command maintenance/rescrape,
  - bukan sumber utama tampilan dashboard/report.

### `project_social_media_items`
- Status: warisan schema.
- Fungsi lama: relasi item sosial ke project.
- Pemakaian sekarang:
  - masih mungkin muncul di migrasi atau cleanup lama,
  - bukan sumber utama tampilan dashboard/report.

### `candidate_links`
- Status: operasional lama yang masih relevan.
- Fungsi: menampung kandidat sebelum disimpan sebagai artikel / item sosial.
- Pemakaian sekarang: masih dipakai pipeline ingestion.

### `scraping_items`
- Status: operasional.
- Fungsi: staging item hasil scraping.
- Pemakaian sekarang: masih dipakai pipeline scraping.

## Jalur Aktif

### Dashboard Project
- Membaca `articles` dan `social_media_items`.
- Mencocokkan isi global terhadap filter project.
- Menampilkan semua item yang cocok, walau item tersebut juga cocok untuk project lain.

### Report / Export
- Mengambil data dari tabel global.
- Menggunakan filter project yang sama dengan dashboard.

### Resync / Audit
- Dipakai untuk merapikan data lama atau mengecek konsistensi.
- Boleh membaca tabel legacy, tetapi tidak boleh menjadikannya sumber utama hasil.

## File Yang Masih Menyebut Tabel Legacy

- `app/Console/Commands/MarkIncompleteForRescrape.php`
- `app/Console/Commands/RunNeedsRescrape.php`
- `app/Console/Commands/SyncSQLiteToPostgres.php`
- migrasi cleanup/backfill lama di `database/migrations/*`

## Catatan Praktis

- Kalau satu artikel cocok untuk dua project, artikel itu harus bisa tampil di dua project saat query filter dijalankan.
- Jangan menambah logika baru yang menganggap pivot sebagai satu-satunya sumber tampil.
- Kalau mau menambah maintenance command, beri label jelas bahwa command tersebut hanya untuk warisan schema.
