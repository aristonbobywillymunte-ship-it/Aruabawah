# APIFY-FINANCIAL-ZERO-REASON-1 Handoff

Status: **COMPLETED**

## Tujuan
Memperbaiki halaman **Laporan Keuangan Apify** agar biaya Apify tampil benar **per proyek/per run**, menampilkan **Batas Biaya/Run dari Paket**, dan menjelaskan alasan jika `actual_cost_usd = 0` atau `items_collected = 0`.

## Ringkasan Implementasi
1. **Source of Truth Biaya dan Batas:**
   - Kolom "Biaya Aktual" murni berasal dari `apify_dispatch_states.actual_cost_usd`.
   - Kolom baru "Batas/Run" secara dinamis dihitung dari relasi `package_actors.cost_per_run_usd` via `project_id -> package_id` dan `actor_id`.
2. **Helper Mapping Status (`financialRunStatus`):**
   Telah dibuat logika kondisional untuk membaca $0 cost, $0 items, dan `last_error_message` untuk mengeluarkan status UI yang manusiawi:
   - **Gagal**: Semua Token Habis, Kuota Apify Habis, Timeout, Gagal Mengambil Hasil, atau Gagal Umum.
   - **Selesai Sebagian / Batas Biaya**: Cost Limit tercapai.
   - **Tidak ada hasil**: Actual Cost $0 dan Items 0 (tanpa error).
   - **Berhasil**: Proses sukses sepenuhnya.
3. **Pembaruan UI/Blade:**
   - Menambahkan kolom `Batas/Run`.
   - Merubah teks status biasa menjadi format *badge* warna-warni (hijau, kuning, merah) dilengkapi pesan penjelasan kecil (~120 karakter).
4. **Targeted Tests:**
   - 7 Skenario telah ditulis di `AdminApifyFinancialReportTest` menggunakan DB Factories untuk memastikan pemisahan data per-proyek berhasil dan mapping status text muncul dengan benar.

## Fakta Perubahan
- **Migration**: TIDAK (Perhitungan limit bersifat *additive join* pada saat *runtime* tanpa merusak historis secara hardcode; perbaikan di masa depan dianjurkan menggunakan snapshot).
- **Scraping Behavior**: TIDAK BERUBAH.
- **Queue Behavior**: TIDAK BERUBAH.
- **Queue Restart**: TIDAK DIPERLUKAN (Hanya perubahan *Livewire* dan *Blade UI*).
- **Token/Secret Exposed**: TIDAK ADA.

Semua perubahan kode siap digunakan di repositori produksi.
