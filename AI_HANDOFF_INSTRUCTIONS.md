# 🚨 PROTOKOL WAJIB SERAH TERIMA AI (AI HANDOFF PROTOCOL)

> **DOKUMEN INI WAJIB DIBACA DAN DIPATUHI OLEH SETIAP MODEL AI / CODING AGENT BARU SEBELUM MENJALANKAN PERINTAH ATAU MENGUBAH KODE APAPUN.**
> Dokumen ini adalah panduan navigasi cepat agar AI tidak mengalami disorientasi, salah direktori, atau berhalusinasi.

---

## 1. Lingkungan Kerja & Direktori Aktif (PENTING!)
* **Lokasi Repositori**: `/Users/unity/Documents/proyek baru/` (Bukan folder lain, bukan server remote).
* **Docker Container Lokal**: `media_intelligent_container` (Workspace container: `/var/web/`).
* **Database & Queue**: PostgreSQL (Database `media_intelligent`), Redis (`redis-ai` untuk antrean AI).
* **Framework**: Laravel 10/11 (PHP 8.4) + Livewire 3 + TailwindCSS.

---

## 2. Urutan Membaca File Wajib (Execution Order)
Sebelum menjawab atau mengeksekusi perintah user, AI **WAJIB** membaca file berikut secara berurutan:
1. [**`PRD.md`**](file:///Users/unity/Documents/proyek%20baru/PRD.md) $\rightarrow$ **Source of Truth Utama**: Memuat arsitektur sistem, status fitur, aturan Apify, pipeline AI, menu yang terlibat, dan riwayat progress terkini.
2. [**`QA_LOG.md`**](file:///Users/unity/Documents/proyek%20baru/QA_LOG.md) $\rightarrow$ **Buku Catatan QA Mandiri**: Memuat seluruh riwayat pengetesan fisik terperinci, skenario uji, actual output, dan status kelulusan.
3. [**`AI_CONTEXT.md`**](file:///Users/unity/Documents/proyek%20baru/AI_CONTEXT.md) $\rightarrow$ Aturan teknis detail scraper portal, Google News decoder, scheduler, dan rem biaya Apify.
4. [**`NEW_AI_HANDOFF.md`**](file:///Users/unity/Documents/proyek%20baru/NEW_AI_HANDOFF.md) $\rightarrow$ Detail arsitektur UI/UX mobile, perbaikan Livewire, dan riwayat bug fix.

---

## 3. Skill Wajib yang Terpasang di Repo
Repositori ini telah mengintegrasikan modul skill resmi di `.ai/skills/`:
1. **Caveman Mode** ([`.ai/skills/caveman/SKILL.md`](file:///Users/unity/Documents/proyek%20baru/.ai/skills/caveman/SKILL.md)):
   * **Mandat**: Komunikasi wajib ringkas, padat, teknis (*no filler, no pleasantries*), dan langsung eksekusi tool tanpa narasi pengumuman yang bertele-tele.
2. **Taste-Skill** ([`.ai/skills/taste-skill/SKILL.md`](file:///Users/unity/Documents/proyek%20baru/.ai/skills/taste-skill/SKILL.md)):
   * **Mandat**: Wajib diterapkan pada setiap tugas antarmuka / UI frontend. Standar *anti-slop* (larangan gradien ungu default AI, larangan generic glassmorphism klise, pahami *design read* dan *layout dials* sebelum mengedit Blade/CSS).

---

## 4. Status Terkini Sistem (Checkpoint Terakhir)
* **Scraping Medsos**:
  - Dikendalikan oleh paket proyek (`/admin/packages`).
  - Alokasi RAM (`?memory=...`), rem biaya (`?maxTotalChargeUsd=...`), dan batas item per kata kunci terbukti 100% mematuhi konfigurasi paket (Terverifikasi di PRD Bab 7).
* **Pipeline AI Medsos (Comment-First)**:
  - Postingan sosmed menunda analisis AI (`comments_checked = false`) sampai worker komentar selesai menarik komentar publik, agar AI menilai sentimen dengan konteks komentar netizen yang utuh.
* **Portal Berita**:
  - Menggunakan Google News URL decoder dan cooldown 720 menit untuk menghindari pembacaan URL lama berulang.

---

## 5. Standar Alur Kerja Profesional AI (The 5-Stage Professional Standard)
Setiap AI yang bekerja pada repositori ini **WAJIB MENGIKUTI ALUR KERJA 5 TAHAP SECARA BERURUTAN**:

```
[Tahap 1: Context Ingestion] -> [Tahap 2: Root Cause Analysis] -> [Tahap 3: Surgical Fix] -> [Tahap 4: Physical QA] -> [Tahap 5: Dual Logging & Local Commit]
```

1. **Tahap 1: Context Ingestion (Baca Sebelum Sentuh Kode)**
   - Wajib membaca `AI_HANDOFF_INSTRUCTIONS.md`, `PRD.md`, dan `QA_LOG.md` sebelum menulis satu baris kode pun.
   - Pahami batasan, model data, dan pantangan yang berlaku.

2. **Tahap 2: Root Cause Analysis & Reproduction (Investigasi & Buktikan Error)**
   - Cari file menggunakan `grep_search` / `find_by_name` / `view_file`.
   - Buktikan error secara nyata (*reproduce*) di container Docker (`media_intelligent_container`) untuk memperoleh stack trace/bukti kegagalan konkret.

3. **Tahap 3: Surgical Fix (Perbaikan Presisi / Minimal Diff)**
   - Terapkan perbaikan terfokus (minimal diff) tanpa merusak kode lain.
   - Pastikan sintaks PHP valid dan bersihkan cache view jika menyentuh Blade (`php artisan view:clear`).

4. **Tahap 4: Physical QA & Verification (Pengujian Nyata di Container)**
   - **DILARANG menyatakan "selesai" tanpa pengujian fisik**.
   - Jalankan simulasi atau eksekusi nyata di dalam container (PHP CLI / Tinker / Artisan test) hingga menghasilkan *exit code 0*.
   - Uji skenario positif (*normal case*) dan skenario batas (*edge case / empty state*).

5. **Tahap 5: Dual Logging & Safe Local Commit (Dokumentasi & Commit Lokal)**
   - Catat detail pengujian lengkap di `QA_LOG.md` (ID, file target, perintah, actual output, status PASSED).
   - Sinkronkan ringkasan di `PRD.md` Bab 6 (Log Progress) dan Bab 7 (Laporan QA).
   - Lakukan `git add` & `git commit` di lokal.
   - **STOP (DILARANG GIT PUSH OTOMATIS)**: Laporkan hasil ke user dan tunggu perintah eksplisit jika ingin di-push.

---

## 6. Larangan Keras & Kewajiban Mutlak (Non-Negotiable Guardrails)
1. **Dilarang Mengubah Database Schema** tanpa instruksi eksplisit user.
2. **Dilarang Berhalusinasi**: Jangan berasumsi file ada atau tidak ada tanpa menjalankan tool `view_file` atau `grep_search`.
3. **WAJIB MELAKUKAN QA FISIK SETELAH SETIAP PERBAIKAN**: Dilarang hanya mengklaim selesai tanpa bukti output eksekusi di container Docker `media_intelligent_container`.
4. **WAJIB MENCATAT HASIL QA KE PRD BAB 7 & BUKU QA MANDIRI (`QA_LOG.md`)**: Seluruh hasil pengetesan, skenario uji, parameter, dan status kelulusan (PASSED/FAILED) wajib didokumentasikan di `PRD.md` Bab 7 dan dicatat rinci pada `QA_LOG.md`.
5. **WAJIB MEMPERBARUI LOG PROGRESS (PRD BAB 6)**: Setelah QA selesai dan dicatat, AI wajib memperbarui kronologi di `PRD.md` Bagian 6 sebelum mengakhiri sesi.
6. **DILARANG KERAS GIT PUSH OTOMATIS (CUKUP COMMIT LOKAL)**:
   - AI **HANYA BOLEH MELAKUKAN GIT COMMIT** di repositori lokal.
   - **DILARANG MELAKUKAN `git push` SECARA MANDIRI/OTOMATIS**.
   - Tindakan `git push` **WAJIB MENUNGGU PERINTAH EKSPLISIT** dari user.


