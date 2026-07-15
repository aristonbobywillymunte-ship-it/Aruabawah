# Project Data Rules

Status: LOCKED

## Aturan Utama

1. Menghapus project tidak menghapus data sumber.
   - Artikel portal tetap disimpan.
   - Data Instagram tetap disimpan.
   - Data Facebook tetap disimpan.
   - Data TikTok tetap disimpan.

2. Project bukan pemilik tunggal data.
   - Project hanya menjadi konteks monitoring dan filter tampilan.
   - Data mentah tetap berada di bank data aplikasi.

3. Keyword project adalah acuan pencarian.
   - Keyword dipakai untuk mencari data dari portal berita, Google News, Instagram, Facebook, dan TikTok.

4. Detail project menampilkan hasil berdasarkan keyword.
   - Sistem mencocokkan keyword project terhadap data yang sudah tersimpan di database.
   - Data yang cocok ditampilkan di detail project.

5. Jangan cascade delete data sumber saat project dihapus.
   - Yang boleh berubah hanya relasi atau konteks project.
   - Data mentah tidak boleh ikut hilang.

6. Data yang sama boleh dipakai beberapa project.
   - Satu artikel atau satu item sosial media boleh relevan untuk lebih dari satu project.
   - Pemakaian ulang ditentukan oleh keyword masing-masing project.

## Ringkasan Sederhana

- Hapus project ≠ hapus artikel/sosmed.
- Keyword project = dasar pencarian.
- Detail project = hasil pencocokan keyword dari database.
