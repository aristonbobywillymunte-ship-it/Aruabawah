# Agent Guidelines & Workflow Rules — Media Intelligence

Dokumen ini adalah pedoman kerja operasional wajib bagi AI Agent yang bekerja pada repositori ini.

---

## 1. Pemanfaatan Skill Wajib & Anti-Slop (Mandatory Skills & Anti-Slop)
Sebelum dan selama menjalankan tugas, AI Agent **wajib memanfaatkan dan mengaktifkan Skill yang relevan**:
1. **Skill Caveman (Anti-Slop & Efisiensi Komunikasi)**:
   - **Tujuan**: Memotong seluruh basa-basi, filler words, redundansi, dan narasi "AI slop" tanpa mengurangi substansi teknis dan ketepatan kode.
   - **Aturan Eksekusi**:
     - Singkat, padat, langsung pada sasaran teknis (`[komponen/isu] [tindakan] [alasan]. [hasil/solusi].`).
     - Hapus kata pengantar basa-basi ("Tentu, saya akan...", "Baik, mari kita analisa...").
     - Hapus narasi pemanggilan tool yang berulang-ulang.
     - Pertahankan akurasi teknis 100%: kode, parameter, nama method, dan rincian bug harus presisi.
2. **Skill UI/UX (UI-UX Pro Max / Generative UI)**:
   - Jika tugas berhubungan dengan UI/UX, desain tampilan, perbaikan antarmuka, tata letak, hierarki tombol, atau modal: Agent wajib membaca dan mengimplementasikan panduan dari skill UI/UX terkait.
3. **Kepatuhan Terhadap Instruksi Skill**:
   - Seluruh instruksi dan best practices yang tercantum pada berkas `SKILL.md` masing-masing skill wajib dipatuhi secara ketat tanpa kompromi.

---

## 2. Alur Kerja Wajib (Mandatory Workflow)
Setiap kali AI Agent menerima tugas analisa, perbaikan, modifikasi, atau audit:
1. **Baca PRD Sebelum Mulai**:
   - Agent wajib membaca dan memahami aturan yang tercantum di `PRD.md`.
2. **Kewajiban Audit Deteksi Slop (Mandatory Slop Check)**:
   - **Setiap analisa WAJIB menyertakan pemeriksaan slop menyeluruh**:
     - **Dead Code & Logic Mati**: Meneliti ada tidaknya variabel, parameter, atau query berat yang dipanggil tetapi tidak pernah digunakan atau ditampilkan pada view.
     - **Tombol Tanpa Proteksi Loading**: Memeriksa seluruh tombol interaktif/aksi apakah sudah memiliki `wire:loading.attr="disabled"` dan indikator visual proses (spinner SVG). Tombol yang bisa di-klik berkali-kali (*double submit hazard*) dikategorikan sebagai slop.
     - **Tag HTML Bocor / Rusak**: Memeriksa tag div liar, template tidak tertutup, atau ketidakseimbangan tag kontainer (`div count open != close`).
     - **Ketidaksesuaian Kolom Tabel**: Memeriksa nilai `colspan` pada empty state tabel agar presisi dengan jumlah header kolom.
     - **Konflik DOM Morphing**: Memeriksa ketiadaan pembungkus `<template x-teleport="body">` di dalam komponen Livewire 3 yang merusak rendering backdrop modal.
3. **Kepatuhan Aturan UI / Modal**:
   - **Strict Scroll-Lock**: Setiap kali modal aktif di layar, background (`body` dan `html`) dilarang bisa di-scroll. Hanya body modal yang boleh di-scroll (`overflow-y-auto overscroll-contain`).
   - Gunakan hook resmi:
     ```javascript
     x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }"
     ```
   - **Backdrop Dismiss**: Setiap backdrop modal wajib menyediakan event penutup saat diklik di luar area kartu (`wire:click.self="closeModal"`).
   - **Hindari `<template x-teleport="body">` pada Livewire 3**: Render modal secara native di root level komponen untuk mencegah konflik DOM morphing collision yang merusak render backdrop.
   - **Dynamic & Unique `wire:key`**: Setiap modal wajib memiliki atribut `wire:key` yang unik dan dinamis.
4. **Eksekusi & QA Mandiri (Quality Assurance)**:
   - Verifikasi sintaks PHP: `php -l <path-ke-file>`.
   - Verifikasi keseimbangan kontainer/tag HTML (tidak boleh ada div atau template bocor).
   - Bersihkan cache view: `docker exec media_intelligent_container php artisan view:clear`.
5. **Pencatatan Wajib Pasca-Pekerjaan**:
   - **Catat ke PRD**: Tambahkan bab perubahan baru di `PRD.md`.
   - **Catat ke QA Log**: Tambahkan entri pengujian di `QA_LOG.md` dengan format `[QA-YYYYMMDD-XX]`, konteks, rincian perubahan, hasil pengujian fisik, dan status.

---

## 3. Integritas Data & Aturan Database
- **No Dummy Data**: Dilarang membuat data palsu, dummy seeder, atau mock seeder di database production.
- `database/seeders/DatabaseSeeder.php` harus tetap bersih/kosong kecuali diminta secara eksplisit oleh user.
- Data artikel, postingan, dan komentar harus berasal murni dari pipeline scraping nyata.
- Perintah reset data artikel yang didukung adalah `monitoring:purge-articles`.

---

## 4. Batasan Akses Role (Role Boundaries)
- **Role Client**:
  - Dilarang menampilkan opsi tombol jalankan scraping manual (scraping klien otomatis murni via jadwal scheduler paket).
  - Panel teknis "STATUS AI & RISIKO" disembunyikan untuk akun klien.
  - Paket langganan klien terkunci pada paket awal yang dipilih (tidak boleh berganti paket bebas saat membuat proyek tambahan).
- **Role Admin**:
  - Memiliki akses penuh ke panel konfigurasi scraper, keuangan, audit pipeline, manajemen pengguna, dan system health.
