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
2. [**`AI_CONTEXT.md`**](file:///Users/unity/Documents/proyek%20baru/AI_CONTEXT.md) $\rightarrow$ Aturan teknis detail scraper portal, Google News decoder, scheduler, dan rem biaya Apify.
3. [**`NEW_AI_HANDOFF.md`**](file:///Users/unity/Documents/proyek%20baru/NEW_AI_HANDOFF.md) $\rightarrow$ Detail arsitektur UI/UX mobile, perbaikan Livewire, dan riwayat bug fix.

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

## 5. Larangan Keras & Kewajiban Mutlak (Non-Negotiable Guardrails)
1. **Dilarang Mengubah Database Schema** tanpa instruksi eksplisit user.
2. **Dilarang Berhalusinasi**: Jangan berasumsi file ada atau tidak ada tanpa menjalankan tool `view_file` atau `grep_search`.
3. **WAJIB MELAKUKAN QA SETELAH SETIAP PERBAIKAN**:
   - Setiap AI yang melakukan perbaikan kode/fitur **DILARANG HANYA MENGKLAIM SELESAI**.
   - Wajib menjalankan verifikasi fisik langsung (PHP linting `php -l`, simulasi eksekusi terminal, atau verifikasi di dalam docker container `media_intelligent_container`).
4. **WAJIB MENCATAT HASIL QA KE PRD BAB 7**:
   - Seluruh hasil pengetesan, skenario uji, parameter, dan status kelulusan (PASSED/FAILED) **wajib didokumentasikan di `PRD.md` Bab 7 (Laporan Hasil Verifikasi QA)**.
5. **WAJIB MEMPERBARUI LOG PROGRESS (BAB 6)**:
   - Setelah QA selesai dan dicatat, AI wajib memperbarui kronologi di `PRD.md` Bagian 6 sebelum mengakhiri sesi/merespon user.
6. **DILARANG KERAS GIT PUSH OTOMATIS (CUKUP COMMIT LOKAL)**:
   - AI **HANYA BOLEH MELAKUKAN GIT COMMIT** di repositori lokal.
   - **DILARANG MELAKUKAN `git push` SECARA MANDIRI/OTOMATIS**.
   - Tindakan `git push` **WAJIB MENUNGGU PERINTAH EKSPLISIT** dari user.


