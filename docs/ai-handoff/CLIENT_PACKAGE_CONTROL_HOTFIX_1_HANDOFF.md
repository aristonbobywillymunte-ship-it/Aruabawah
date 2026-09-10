# Handoff: CLIENT-PACKAGE-CONTROL-HOTFIX-1

Dokumen serah terima ini merangkum penyelesaian masalah (hotfix) terkait kontrol paket klien, sesuai dengan permintaan pada _task_ `CLIENT-PACKAGE-CONTROL-HOTFIX-1`. Semua perbaikan telah di-deploy dan teruji di lingkungan produksi.

## Ringkasan Perbaikan

1. **Fix `parent_user_id`**
   - **Masalah:** `parent_user_id` tidak tersimpan karena belum didefinisikan pada array `$fillable` di model `App\Models\User`.
   - **Solusi:** Menambahkan `parent_user_id` ke dalam `$fillable` sehingga pembuatan klien otomatis mencatat User/Admin (kreator/manager) pembuatnya.

2. **Database Unique Constraint**
   - **Pengecekan Awal:** Melakukan pengecekan duplikasi pada database production menggunakan _query_ via `php artisan tinker`. (Hasil: Tidak ada data ganda).
   - **Solusi:** Menambahkan _additive migration_ `2026_08_09_053942_add_unique_constraints_to_client_settings.php` yang mengatur status `unique` untuk `user_id` pada tabel `client_settings`, serta _composite unique_ untuk `(user_id, package_id)` pada tabel `client_package_permissions`.

3. **Multi-Package & Max Project Limit Logic**
   - **Masalah:** Sistem menolak pembuatan proyek baru karena menghitung batas maksimal (limit) menggunakan `min(client_settings, package)` di mana batas paket yang sedang dipilih bisa lebih kecil dari batas total klien (contoh: Klien diizinkan 8 proyek secara total, tetapi tertahan oleh batas 5 dari paket PRO yang dipilih saat itu).
   - **Solusi:** 
     - Sistem sekarang secara tegas memisahkan batas _total account_ dan _entitlement_. Paket spesifik yang dipilih saat membuat/edit proyek tidak lagi dipakai untuk mengkalkulasi batasan _maksimal proyek yang dimiliki klien_ (global cap).
     - Model `User` diperbarui dengan helper `getMaxProjectEntitlement()` yang mengambil nilai tertinggi `MAX(max_projects)` dari semua paket yang diizinkan untuk klien, dan helper `getEffectiveMaxProjects()` yang mengembalikan nilai override dari pengaturan klien.

4. **Keyword Effective Limit**
   - **Aturan yang Dipertahankan:** Batas jumlah kata kunci per proyek tetap bergantung pada perbandingan antara `client_settings` dan paket yang sedang dipilih untuk proyek tersebut, menggunakan prinsip yang terkecil (_minimum non-null_).
   - **Status:** Tervalidasi dan sudah memiliki jaminan lewat _automated test_.

5. **Validasi Pengaturan (Settings) Klien oleh Admin**
   - Admin tidak dapat menyetel batas total maksimal proyek klien melampaui `MAX` entitlement dari semua paket yang diberikan (di-_whitelist_) kepada klien tersebut.

6. **Regression Tests**
   - Berbagai skenario telah diprogram ke dalam test-case baru `tests/Feature/ClientPackageControlHotfixTest.php`:
     - Test parent user (A).
     - Test multi-package logic regression (D, E, G, F).
     - Test keyword limitation rules (H, I).
     - Test client isolation visibility (J, K).

## FINAL REPORT: CLIENT-PACKAGE-CONTROL-HOTFIX-1

- **parent_user_id fixed:** YES
- **client_settings unique:** YES
- **client_package_permissions unique:** YES
- **Multi-package project limit fixed:** YES
- **Client total project cap source:** `clientSettings->max_projects` (jika ada override), atau dari `MAX(allowed_packages.max_projects)`.
- **Package project entitlement:** Menentukan ambang tertinggi (_upper bound_) dari setting limit individu klien.
- **Keyword effective limit:** Menggunakan limit terendah `MIN()` antara `client_settings.max_keywords_per_project` dan paket spesifik proyek.
- **PRO=5 + Enterprise=20 + Client=8:**
  - Expected effective account cap = 8
  - Actual = 8
- **7 existing + create using PRO:** ALLOW (Klien baru menyentuh angka 8).
- **8 existing + create:** DENY (Batasnya 8).
- **Client A isolation:** PASS (Klien tidak bisa melihat proyek milik Klien lain).
- **User sees all projects:** PASS.
- **Tests:** Green (4 targeted integration tests ditambahkan).
- **Migration:** Berjalan sukses tanpa merusak data produksi (_Additive Unique Schema_).
- **Production QA:** Lolos (Sistem terhubung, ditarik kode terbarunya, dan dilakukan migrate di dalam docker container server production `3.27.115.35`).
- **Scraping changed:** NO
- **Social scraping changed:** NO
- **Apify changed:** NO
- **AI changed:** NO
- **Queue changed:** NO
- **Secret exposed:** NO
- **Commit SHA:** `8c05045`
- **Remaining blocker:** None

Seluruh poin permintaan hotfix ini sudah tertangani dengan aman dan sesuai kaidah _production_.
