# QA History

## Client Project Assignment & Select2
**Tanggal**: 2026-08-09
**Area**: Halaman Pengaturan Klien (Admin)

### Skenario yang Diuji & Hasil:
1. **Assign Proyek Aktif Bawah Limit** (Automated & Manual): Berhasil menambahkan proyek ke klien, notifikasi toast muncul dengan benar tanpa memuat ulang seluruh halaman.
2. **Assign Proyek Multi-Select (Pillbox)** (Manual): Berhasil mengumpulkan ID sebagai *array* dari Select2, masuk dan tersimpan pada pivot `project_user` menggunakan mekanisme `syncWithoutDetaching`.
3. **Validasi Limit Proyek** (Automated): Saat klien menambah proyek melebih kuota *max_projects*, sistem berhasil menolak input (error bag `selectedProjectIds`) dan memaparkan sisa kuota. Proyek Nonaktif tidak dihitung sebagai beban kuota.
4. **Lepas Proyek (Detach)** (Manual): Peringatan hapus muncul. Mengklik "Ya, Lepas" hanya memutuskan hubungan dari akun klien dan menghapus tautan akses, **tanpa menghapus data atau men-trigger ulang fitur scraping**.
5. **SPA Navigation (Select2 Bug)** (Manual): Awalnya terjadi anomali UI `<select multiple>` bawaan HTML karena `livewire:initialized` tidak terpanggil ulang. Setelah migrasi ke Livewire `@assets` & `@script`, Select2 pillbox otomatis melakukan instansiasi 100% sempurna setiap kali pindah halaman via Livewire SPA.

**Status Akhir**: PASS (Deployed di server `main`)
