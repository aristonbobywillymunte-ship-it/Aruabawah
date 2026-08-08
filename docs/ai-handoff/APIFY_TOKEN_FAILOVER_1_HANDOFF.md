# APIFY-TOKEN-FAILOVER-1 Handoff

Status: **COMPLETED**

## Tujuan
Memperbaiki mekanisme rotasi token (failover) pada Apify saat batas pemakaian (monthly usage hard limit) tercapai, agar sistem tidak mengalami infinite loop atau re-dispatch yang berulang tak terkendali.

## Ringkasan Implementasi
1. **Refactoring `ApifySetting`**: 
   - Menghapus method usang `rotateToNextToken()` yang memaksakan perpindahan token dan memicu _dispatch_ eksternal.
   - Menambahkan method pembantu untuk mendapatkan token dan status berdasarkan indeks: `getTokenByIndex()`, `getConnectionStatusByIndex()`.
   - Menambahkan method krusial `getNextEligibleTokenIndex(array $excludedIndexes)` yang bertugas mencari token berikutnya (0..3) yang terisi dan berstatus "ready" / "connected" dengan mengabaikan token yang sudah pernah dicoba dalam loop yang sama.

2. **Refactoring `ApifyScrapingJob@handle`**:
   - Membuang pemanggilan `self::dispatch()` di dalam blok penanganan kegagalan kuota.
   - Mengubah struktur pengiriman _request_ (mulai dari resolusi token hingga pemanggilan `Http::withToken`) ke dalam sebuah perulangan *while-loop* dengan batasan iterasi maksimal 4 kali (sejumlah total slot token yang ada).
   - Jika `getNextEligibleTokenIndex()` mengembalikan `null`, artinya semua cadangan yang memungkinkan telah dicoba (atau memang tidak ada cadangan). Proses akan dihentikan seketika dengan log dan status error ditandai sebagai `APIFY_ALL_TOKENS_EXHAUSTED`.
   - Jika _request_ gagal spesifik karena kredensial / limit, _loop_ memanggil `continue` untuk berputar ke iterasi berikutnya dan menguji token cadangan tanpa membebani sistem antrean (Queue). 

3. **Penambahan Test Cases (`ApifyTokenFailoverTest`)**:
   - Total 8 skenario pengujian baru telah ditulis, meliputi:
     1. Gagal main quota → berhasil pindah ke backup 1.
     2. Gagal main & backup 1 → berhasil pindah ke backup 2.
     3. Otomatis mengabaikan backup dengan status tidak "connected".
     4. Jika hanya main token tersedia dan gagal → STOP tanpa re-dispatch.
     5. Jika semua token exhaustion terdeteksi → Set status APIFY_ALL_TOKENS_EXHAUSTED.
     6. Error HTTP standar (misal 5xx / timeout) → gagal secara wajar, **tidak** merotasi token.
     7. Token baru yang sukses, posisinya diperbarui menjadi *active token* permanen.
     8. Kredensial token (string api_token) dijamin tidak bocor ke _error log_.

## Perhatian Khusus
Karena ini mengubah alur kerja *Worker* secara langsung di level *Queue Job*, **pastikan merestart queue worker** (misal dengan `php artisan queue:restart`) setelah kode ini dideploy ke lingkungan *production*, agar *worker daemon* menggunakan memori script PHP yang baru.
