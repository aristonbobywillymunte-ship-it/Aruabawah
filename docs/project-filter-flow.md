# Alur Filter Proyek

Dokumen ini menjelaskan alur terbaru pemetaan konten ke project.

## Prinsip Utama

- Filter project adalah sumber kebenaran utama.
- `topics` / kata kunci utama menentukan apakah item layak masuk.
- `context_keywords` sekarang dianggap filter tambahan yang tidak mengunci hasil utama.
- `exclude_keywords` menggugurkan item jika ada salah satu kata yang cocok.

## Alur Kerja

1. Data masuk dari scraper atau sumber berita.
2. Sistem membentuk teks pencocokan dari konten item.
3. Sistem mengecek filter project.
4. Jika lolos:
   - item di-link ke project
   - item tampil di dashboard project
5. Jika tidak lolos:
   - item tidak di-link ke project tersebut

## Tentang Match Lama

Istilah lama seperti `matchLink` hanya merujuk pada proses linking konten ke project.
Dalam implementasi sekarang, linking itu harus dianggap sebagai turunan dari filter project,
bukan logika utama yang berdiri sendiri.

## Dampak ke Dashboard

- Jika filter terlalu ketat, data project bisa berkurang.
- Jika filter cocok, item lama bisa diresync dan muncul lagi di dashboard.
- Untuk social media, pencocokan tetap mengikuti aturan project, bukan sekadar jumlah data mentah yang masuk.
- Jalur tampil dan resync sekarang tidak lagi menahan data hanya karena `context_keywords`.

## Catatan Terkini

- Ringkasan card proyek di halaman daftar sekarang memakai hitung global berbasis filter project.
- Saat project disimpan atau diedit, sistem perlu melakukan resync agar data lama yang tidak cocok ikut dilepas.
- Logika lama yang hanya bergantung pada relasi pivot harus dianggap legacy dan tidak dipakai sebagai sumber utama tampilan.
- Runtime utama sekarang membaca global `articles` dan `social_media_items` lalu mencocokkannya langsung ke filter project.
- `project_articles` dan `project_social_media_items` hanya tersisa di jalur legacy / maintenance, bukan sumber kebenaran dashboard.
