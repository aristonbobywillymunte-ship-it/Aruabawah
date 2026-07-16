# ARUSBAWAH Media Intelligence

ARUSBAWAH Media Intelligence adalah aplikasi dashboard pemantauan media berbasis proyek yang digunakan untuk mengumpulkan, menampilkan, dan menganalisis informasi dari portal berita dan media sosial.

Sistem ini dirancang sebagai pusat kontrol untuk memantau isu, sentimen publik, topik populer, jangkauan, serta risiko dari sebuah proyek secara terpusat.

## Ringkasan

- Memantau beberapa proyek dalam satu dashboard.
- Menampilkan ringkasan data media secara visual dan terstruktur.
- Mendukung analisis sentimen, jangkauan, dan risiko.
- Menyediakan pengaturan scraping, sumber berita, AI provider, dan admin tools.
- Mendukung pelaporan dan pemantauan operasional secara berkelanjutan.

## Fitur Utama

### 1. Daftar Proyek

Halaman daftar proyek menampilkan seluruh proyek yang tersedia di sistem. Pengguna dapat:

- melihat kartu ringkasan proyek
- membuat proyek baru
- membuka detail proyek
- mengedit proyek
- menonaktifkan atau menghapus proyek
- melihat daftar proyek yang telah dihapus

Setiap kartu proyek menampilkan informasi seperti:

- nama proyek
- waktu pembuatan
- status pembaruan data portal
- status pembaruan data media sosial
- jumlah artikel siap ditampilkan
- total jangkauan
- persentase positif
- persentase negatif
- status AI dan risiko
- topik populer

### 2. Pembuatan Proyek Baru

Sistem menyediakan kartu dan formulir untuk menambahkan proyek baru. Proyek baru digunakan untuk mengatur pemantauan media online, media cetak, dan media sosial.

Komponen umum yang tersedia:

- nama proyek
- kata kunci utama
- pengaturan lanjutan

### 3. Proyek Dihapus

Sistem memiliki panel khusus untuk menampilkan proyek yang telah dinonaktifkan. Dari panel ini pengguna dapat:

- mengaktifkan kembali proyek
- menghapus proyek secara permanen
- menutup panel

### 4. Menu Analisis

Menu analisis menampilkan ringkasan data untuk proyek aktif. Informasi yang disajikan meliputi:

- total artikel ditemukan
- total jangkauan
- interaksi media sosial
- sentimen berita
- sentimen media sosial
- status AI
- risiko

Menu ini juga menyediakan filter aktif dan rentang tanggal.

### 5. Menu Penyebutan

Menu penyebutan digunakan untuk menampilkan daftar atau analisis penyebutan yang terkait dengan proyek. Fitur ini membantu pengguna menelusuri isu, nama, atau kata kunci yang sering muncul dalam data media.

### 6. Menu Kata Kunci

Menu ini digunakan untuk mengelola dan memantau kata kunci utama proyek. Kata kunci menjadi dasar pengumpulan data dari sumber media yang dipilih.

### 7. Menu Wawasan

Menu wawasan menyajikan insight yang dihasilkan dari data monitoring. Fitur ini membantu pengguna memahami pola, tren, dan tema dominan dalam hasil pemantauan.

### 8. Menu Konten

Menu konten digunakan untuk meninjau isi artikel, posting, atau konten lain yang berhasil dikumpulkan dari sumber media.

### 9. Menu Sumber

Menu sumber dipakai untuk mengelola dan meninjau sumber data, seperti portal berita dan kanal media sosial.

### 10. Menu Laporan

Menu laporan digunakan untuk menghasilkan rekapitulasi atau ringkasan hasil monitoring dalam bentuk yang lebih formal.

## Fitur Administratif

Aplikasi ini memiliki modul administrasi untuk pengelolaan sistem, antara lain:

- dashboard admin
- kelola user
- pengaturan Apify
- AI Provider
- Scraping Settings
- Branding Aplikasi
- Pipeline Monitor
- Manajemen Sumber Berita
- AI Prompt Templates
- Telegram Settings
- Log Sistem
- Database
- Maintenance

## Pengaturan Scraping

Pengaturan scraping digunakan untuk mengontrol proses pengambilan data dari sumber media. Parameter yang umum tersedia mencakup:

- interval crawling
- limit crawler
- rentang waktu pengambilan data
- HTTP timeout
- retry limit
- delay antar proses

Pengaturan ini penting untuk menjaga stabilitas proses crawling dan menghindari beban berlebih pada sumber data.

## Analisis AI dan Risiko

Sistem menampilkan metrik khusus untuk mendukung analisis kualitas data dan risiko, seperti:

- Siap Ditampilkan
- Analisis AI
- High Risk

Panel `STATUS AI & RISIKO` dapat ditampilkan atau disembunyikan sesuai kebutuhan pengguna. State tampil/sembunyi dibuat per proyek agar lebih stabil dan tidak saling memengaruhi.

## Topik Populer

Setiap proyek menampilkan topik populer yang dihasilkan dari hasil monitoring. Informasi ini membantu pengguna melihat tema yang paling sering muncul dalam percakapan publik dan pemberitaan.

## Karakter UI

Antarmuka aplikasi dibuat sebagai dashboard operasional yang padat informasi namun tetap mudah dipindai. Perhatian khusus diberikan pada:

- konsistensi ukuran kartu
- perilaku hover dan kursor
- jarak antar elemen
- animasi show/hide yang halus
- navigasi antarmenu yang konsisten

## Navigasi

Navigasi antarmenu menggunakan route Laravel biasa agar perpindahan halaman lebih konsisten dan mudah dipelihara. Pendekatan ini juga membantu menjaga perilaku navigasi tetap stabil di berbagai halaman proyek.

## Teknologi

Proyek ini dibangun dengan stack berikut:

- Laravel
- Livewire
- Alpine.js
- Tailwind CSS
- PHP 8.3
- MySQL/PostgreSQL sesuai konfigurasi environment

Selain itu, proyek juga menggunakan beberapa paket pendukung untuk:

- queue dan background job
- PDF generation
- spreadsheet export
- realtime/event support

## Struktur Umum Aplikasi

Secara umum, alur aplikasi dapat diringkas sebagai berikut:

1. pengguna login ke sistem
2. pengguna memilih atau membuat proyek
3. sistem mengumpulkan data dari sumber yang dikonfigurasi
4. data diproses menjadi ringkasan, sentimen, risiko, dan insight
5. hasilnya ditampilkan melalui dashboard analitik dan halaman admin

## Status Pengembangan

Repository ini sedang dikembangkan secara aktif. Beberapa bagian UI dan navigasi telah disesuaikan agar:

- lebih konsisten
- lebih nyaman digunakan
- lebih stabil saat berpindah halaman
- lebih mudah dipelihara

## Catatan Penggunaan

- Pastikan environment `.env` sudah terisi sesuai kebutuhan.
- Jalankan migrasi dan seed jika diperlukan.
- Gunakan `npm run dev` untuk mode pengembangan frontend.
- Gunakan queue worker untuk proses background jika fitur scraping atau job aktif.

## Lisensi

Proyek ini digunakan untuk kebutuhan internal ARUSBAWAH Media Intelligence.
