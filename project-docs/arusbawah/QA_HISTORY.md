# QA History

## Client Project Assignment & Select2
**Tanggal**: 2026-08-09
**Area**: Halaman Pengaturan Klien (Admin)

### Skenario yang Diuji & Hasil:
1. **Assign Proyek Aktif Bawah Limit** (Automated & Manual): Berhasil menambahkan proyek ke klien, notifikasi toast muncul dengan benar tanpa memuat ulang seluruh halaman.
2. **Assign Proyek Multi-Select (Pillbox)** (Manual): Berhasil mengumpulkan ID sebagai *array* dari Select2, masuk dan tersimpan pada pivot `project_user` menggunakan mekanisme `syncWithoutDetaching`.
3. **Validasi Limit Proyek** (Automated): Saat klien menambah proyek melebih kuota *max_projects*, sistem berhasil menolak input (error bag `selectedProjectIds`) dan memaparkan sisa kuota. Proyek Nonaktif tidak dihitung sebagai beban kuota.
4. **Lepas Proyek (Detach)** (Manual): Peringatan hapus muncul. Mengklik "Ya, Lepas" hanya memutuskan hubungan dari akun klien dan menghapus tautan akses, **tanpa menghapus data atau men-trigger ulang fitur scraping**.
5. **SPA Navigation & UX (Select2 Bug)** (Manual): Awalnya terjadi anomali UI `<select multiple>` bawaan HTML karena `livewire:initialized` tidak terpanggil ulang. Setelah migrasi ke Livewire 4 `@assets` & `@script` dan memperbaiki pemanggilan `$wire.$set` serta scope Select2 menjadi `$($wire.$el)`, Select2 pillbox otomatis melakukan instansiasi 100% sempurna (*idempotent*) setiap kali pindah halaman via Livewire SPA. Dropdown ter-reset otomatis setiap assign/detach berhasil. Perataan vertikal untuk ikon dan *placeholder* juga diatasi dengan perombakan CSS *flexbox* bawaan komponen.
6. **Simpan Pengaturan UX** (Manual): Mengubah alur simpan `ClientSettings.php` dari refresh sepihak menjadi navigasi SPA `wire:navigate` ke halaman daftar klien, yang dibarengi dengan munculnya `admin-toast` notifikasi sukses, serta melengkapi tombol simpan dengan status `wire:loading` ("Menyimpan...").

**Status Akhir**: PASS (Deployed di server `main`)
