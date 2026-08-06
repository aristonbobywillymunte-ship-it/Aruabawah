# 📋 Laporan Audit: Pipeline Monitor

**Halaman:** `/admin/pipeline-monitor`  
**Tanggal:** 2026-08-07  
**File Utama:** `app/Livewire/Admin/PipelineMonitor.php` (1163 baris)  
**Blade View:** `resources/views/livewire/admin/pipeline-monitor.blade.php`

---

## 1. Gambaran Umum

Pipeline Monitor adalah halaman dashboard operasional paling kompleks di seluruh sistem.  
Halaman ini menampilkan **6 Tab** + **1 Tab Kesehatan**:

| Tab | Method | Data Source |
|-----|--------|-------------|
| Scraping | `getScrapingItems()` | `articles` + `aiAnalysisResult` (eager loaded) |
| Social | `getSocialItems()` | `social_media_items` + `projects` + `aiAnalysisResult` |
| AI | `getAiItems()` | `ai_analysis_results` + `article` + `socialMediaItem` |
| Notifikasi | `getNotificationItems()` | `risk_notifications` + `aiAnalysisResult.article` |
| Queue Pending | `getPendingJobs()` | `ai_analysis_dispatch_states` + `project` |
| Queue Failed | `getFailedJobs()` | `ai_analysis_dispatch_states` + `project` |
| Kesehatan | `getHealthStatusStats()` | Multi-source: Redis, Log file, Cache, DB |

---

## 2. Fitur Utama

- **Filter Multi-dimensi:** search, status, platform, AI state, risk level, project per-tab
- **Aksi:** Retry single job, Retry all failed, Delete job, Clear pending queue, Retry notification, Delete notification
- **View Content:** Modal untuk melihat isi artikel/post langsung dari tabel
- **`getSummaryStats()`:** Statistik ringkasan yang dipanggil di setiap `render()` (berat)
- **`getHealthStatusStats()`:** Hanya dipanggil saat tab `health` aktif (aman)

---

## 3. ⚠️ Temuan Audit: Bug & Potensi Masalah

### 🔴 Bug #1: Filter Project Menggunakan Keyword Match, Bukan Relasi DB

**Method:** `getScrapingItems()` (baris ~449), `getAiItems()` (baris ~619), `getSocialItems()` (baris ~542)

Saat user memilih filter project, sistem **tidak** menggunakan relasi database (`project_id` atau pivot table), melainkan secara dinamis mengambil keyword dari project dan melakukan `ILIKE` search ke konten artikel/post. 

```php
// ❌ MASALAH: Filter project via keyword scan konten, bukan relasi
$primaryKeywords = $project->scrapeKeywordVariants();
$query->where(fn($q) => $q->where('articles.title', 'ilike', '%'.$kw.'%')...);
```

**Dampak:**
- Artikel yang memang sudah terhubung ke project via relasi, tapi **kontennya tidak mengandung keyword** (misal: keyword project berubah setelah artikel masuk), TIDAK akan muncul di filter
- **Test `test_filter_project_finds_article_without_duplicate` GAGAL** karena artikel berisi konten "Content" yang tidak cocok dengan keyword Project B ("B")
- Query bisa sangat lambat karena ILIKE scan per keyword ke seluruh tabel `articles.content`

---

### 🟠 Bug #2: N+1 Query di `getHealthStatusStats()` — `latestNotifications`

**Baris 932-939:**
```php
$latestNotifications = RiskNotification::latest()->limit(5)->get()->map(function($n) {
    return [
        'title' => $n->aiAnalysisResult->article->title ?? 'N/A',  // ← N+1!
        ...
    ];
});
```
Setiap iterasi memuat `aiAnalysisResult` dan `article` secara lazy. Perlu: `->with(['aiAnalysisResult.article'])`.

---

### 🟡 Bug #3: `getSummaryStats()` — Berat, Dipanggil Setiap Render

Method `getSummaryStats()` dipanggil tanpa kondisi di setiap `render()` (baris 1035), berisi:
- `chunkById(250)` loop semua artikel aktif + keyword match per project (sangat berat pada data besar)
- `AiAnalysisResult::count()` x4 (total, success, failed, highRisk — bisa 1 query dengan `groupBy`)
- `RiskNotification::count()` x3

**Total estimasi query per page load: 20+ queries**, belum termasuk artikel chunk.

---

### 🟡 Bug #4: `retryAllFailedAiStates()` — N+1 Loop tanpa Batasan

**Baris 138-175:** Memuat semua `AiAnalysisDispatchState::where('status','failed')->get()` sekaligus tanpa `limit()`, kemudian loop dan `find()` artikel/social satu per satu. Jika ada 10.000 failed states, ini akan menyebabkan timeout.

---

### 🟡 Bug #5: `getHealthStatusStats()` membaca file log ke memory

**Baris 996-1019:**
```php
$logContent = file_get_contents($logPath); // ← Baca file 19MB ke RAM!
```
File `laravel.log` bisa mencapai puluhan MB. Ini berbahaya di production environment.

---

### 🔴 Test Coverage: 1 dari 5 Test GAGAL

```
✓ pipeline monitor shows single row for multi project article
⨯ filter project finds article without duplicate          ← FAIL
✓ failed ai state shows safe failure category
✓ pipeline monitor shows backfill stats
✓ clear all pending ai states keeps fresh retry wait states
```

**Test yang gagal:** `test_filter_project_finds_article_without_duplicate`  
**Root cause:** Filter project berdasarkan keyword match, bukan relasi — artikel dengan konten yang tidak cocok keyword project tidak ditemukan.

---

## 4. Rencana Tindak Lanjut

| # | Item | Prioritas |
|---|------|-----------|
| 1 | Fix `latestNotifications` N+1 → tambah `->with(['aiAnalysisResult.article'])` | 🟠 Medium |
| 2 | Fix `getSummaryStats()` count queries → satukan dengan `selectRaw` / `groupBy` | 🟡 Low |
| 3 | Fix `getHealthStatusStats()` → ganti `file_get_contents` dengan `tail` via PHP `fseek` ke 50KB terakhir | 🟡 Low |
| 4 | Fix test `filter_project_finds_article_without_duplicate` → test seharusnya set keyword project yang match konten artikel | 🟢 Quick Fix |
| 5 | Tambah `chunk(100)` atau `limit(500)` di `retryAllFailedAiStates()` | 🟠 Medium |
