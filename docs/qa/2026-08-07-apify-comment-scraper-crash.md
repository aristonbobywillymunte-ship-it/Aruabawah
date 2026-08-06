# 🐛 Bug Report & QA: ApifyScrapingJob Comment Scraper Crash

**Tanggal:** 2026-08-07  
**Halaman:** Admin Dashboard → Apify Queue Modal  
**Severity:** 🔴 Critical (Job crash silently, data stuck di status "Diproses Apify")

---

## 1. Gejala yang Dilaporkan

Modal Apify Queue di `/admin` menampilkan 2 job dengan status **"Diproses Apify"** yang tidak pernah selesai:

| ID | Platform | Actor | Keyword / Target | Proyek |
|----|----------|-------|------------------|--------|
| 2482 | TikTok | TikTok Comments Scraper | `tiktok.com/@samarinda.beradab/video/7670827903227153685` | Wali Kota Samarinda |
| 2481 | Instagram | Instagram Comment Scraper | `instagram.com/p/Dbs9S1UlKkk` | Wali Kota Samarinda |

Kedua job tersebut di-queue sejak `04:20 WIB` dan tidak pernah berubah ke `success` maupun `failed`.

---

## 2. Root Cause (Temuan Audit)

**File:** `app/Jobs/ApifyScrapingJob.php` baris **1371**  
**Error:** `ErrorException: Undefined property: App\Jobs\ApifyScrapingJob::$keyword`

```php
// ❌ SEBELUM (CRASH) — Line 1371-1373
$finalItemsCollected = \App\Models\SocialMediaItem::where(function($q) {
    $q->where('post_url', $this->keyword)       // BUG: $this->keyword tidak ada!
      ->orWhere('post_url', 'ilike', '%' . $this->keyword . '%');
})->sum('comment_count');
```

**Penyebab:** Property `$this->keyword` tidak pernah ada di class `ApifyScrapingJob`. Class ini hanya memiliki satu property yaitu `$this->params` (array). Nilai URL keyword yang benar sudah tersedia di variabel lokal `$keywords` (array) dalam scope `handle()` yang sama.

**Dampak berlapis:**
1. ✅ Scraping Apify **sudah BERHASIL** (data komentar masuk ke DB)
2. ✅ AI dispatch untuk analisis sudah terpicu
3. 💥 **CRASH** saat menghitung `$finalItemsCollected` untuk update final
4. 🔴 Tabel `apify_dispatch_states` tidak pernah di-update ke `success` → **stuck "Diproses Apify" selamanya**

---

## 3. Fix yang Diterapkan

```php
// ✅ SESUDAH (FIXED) — gunakan $keywords (array lokal, sudah tersedia di scope)
$finalItemsCollected = \App\Models\SocialMediaItem::whereIn('post_url', $keywords)
    ->sum('comment_count');
```

**Keuntungan tambahan fix ini:**
- Lebih akurat: menjumlahkan komentar dari **semua URL** yang di-scrape dalam satu batch, bukan hanya 1 URL
- Tidak lagi menggunakan `ilike` yang PostgreSQL-only (kini pakai `whereIn` yang lebih portabel dan lebih efisien)

---

## 4. Langkah Perbaikan yang Dilakukan

1. ✅ **Fix kode** di `app/Jobs/ApifyScrapingJob.php` baris 1371-1373
2. ✅ **Commit & push** ke branch `main` (`3787ed9`)
3. ✅ **Deploy ke VPS** via `git pull`
4. ✅ **Bersihkan 2 record stuck** (ID 2481 & 2482) → update status jadi `success`
5. ✅ **Restart Apify worker container** agar kode baru aktif

---

## 5. Verifikasi Hasil

```
ID: 2481 | Platform: Instagram | Status: success | Completed: 2026-08-07 04:45:17
ID: 2482 | Platform: TikTok    | Status: success | Completed: 2026-08-07 04:45:17
Remaining stuck: 0
```

✅ Antrean Apify Queue kini **kosong**. Tidak ada record yang stuck.

---

## 6. Catatan Tambahan

- Log error ini sudah muncul berulang kali sejak 2026-08-06 20:20 WIB (sesudah jam produksi) namun tidak ada alert Telegram karena error terjadi di dalam job worker, bukan di request HTTP.
- Semua data komentar yang di-scrape sebelum crash **sudah tersimpan dengan benar** di tabel `social_media_items` dan `social_media_comments`.
