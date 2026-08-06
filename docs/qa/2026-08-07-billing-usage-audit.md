# 📋 Laporan Audit: Apify Billing & Usage Page

Berikut hasil audit menyeluruh dari semua file terkait halaman **Apify Billing & Usage** pada project Laravel.

---

## 1. ROUTE — `routes/web.php` (baris 121–123)

**File:** `/Users/unity/Documents/proyek baru/routes/web.php`

```php
Route::get('/admin/apify-financials', function () {
    return view('admin.apify-financial-report');
})->middleware('admin')->name('admin.apify-financials');
```

**Detail:**
- **URL:** `/admin/apify-financials`
- **Route name:** `admin.apify-financials`
- **Middleware:** `admin` (proteksi level admin)
- **View yang dikembalikan:** `admin.apify-financial-report` (layout wrapper)
- **Pattern:** Route closure sederhana.

---

## 2. LIVEWIRE COMPONENT — `ApifyFinancialReport.php`

**File:** `/Users/unity/Documents/proyek baru/app/Livewire/Admin/ApifyFinancialReport.php`

### Public Properties (State)
| Property | Tipe | Default | Fungsi |
|---|---|---|---|
| `$projectId` | `?int` | `null` | Filter by project |
| `$startDate` | `?string` | `null` | Filter tanggal mulai |
| `$endDate` | `?string` | `null` | Filter tanggal akhir |
| `$showItemsModal` | `bool` | `false` | Visibilitas modal detail item |
| `$modalLoading` | `bool` | `false` | Loading state modal |
| `$selectedItems` | `array` | `[]` | Item data yang ditampilkan di modal |
| `$selectedPlatform` | `string` | `''` | Platform terpilih untuk modal |
| `$selectedKeyword` | `string` | `''` | Keyword/URL terpilih untuk modal |
| `$selectedRunId` | `string` | `''` | Run ID terpilih |
| `$selectedProjectName` | `string` | `''` | Nama proyek terpilih |
| `$isCommentModal` | `bool` | `false` | Apakah modal menampilkan komentar (bukan post) |

### Methods

#### `openItems($projectId, $platform, $keyword, $runId, $projectName)`
- Membuka modal dan memuat data item scraping.
- **Deteksi otomatis:** Jika `$keyword` adalah URL valid (`FILTER_VALIDATE_URL`), dianggap sebagai Comment Scraper, bukan Post Scraper.

#### `render()`
- Query 3 dataset:
  1. `$projects` — semua proyek dari tabel `projects`
  2. `$recentRuns` — paginated 20/halaman dari `apify_dispatch_states`
  3. `$costSummary` — via `loadCostSummary()`
- **Filter behavior:** Jika `$startDate` tidak diset, defaultnya 30 hari terakhir.

#### `loadCostSummary(): array`
- Query sama dengan `recentRuns` (tanpa pagination).
- Group by platform + tipe scraper (Post/Komentar).

---

## 3. ⚠️ POTENSI MASALAH & BUG (TEMUAN AUDIT)

### 🔴 Bug Serius: N+1 Query Problem di `render()`
```php
$recentRuns->getCollection()->transform(function ($r) {
    $actor = DB::table('apify_actors')->where('id', $r->actor_id)->value('actor_name'); // N query!
    $projectName = DB::table('projects')->where('id', $r->project_id)->value('name');   // N query!
    ...
});
```
**Masalah:** Setiap baris di halaman (20 row) memicu 2 query DB terpisah — total bisa **40+ query** per render cycle. Harus diganti dengan eager load / join di awal query.

### 🔴 Bug: Debug `\Log::info()` Tersisa di Production Code
**Masalah:** Log debug SQL dipanggil setiap kali admin membuka modal item. Ini mencemari log produksi dan berpotensi ekspos info internal (SQL query structure). Harus dihapus atau dibungkus `if (config('app.debug'))`.

### 🟡 Masalah: `loadCostSummary()` Duplikasi Query dari `render()`
Fungsi `loadCostSummary()` menjalankan query yang **hampir identik** dengan query `$recentRuns` di `render()`. Artinya setiap render menjalankan **2 query besar** ke `apify_dispatch_states` dengan filter yang sama.

### 🟡 Masalah: Potensi XSS di Blade via `addslashes()`
Menggunakan `addslashes()` untuk escape parameter Livewire actions di atribut HTML rentan jika keyword mengandung karakter khusus (kutip tunggal, Unicode, dsb).

### 🟡 Masalah: `ilike` (PostgreSQL-only) — Database Coupling
`ilike` adalah operator **PostgreSQL-specific** untuk case-insensitive LIKE.

### 🟡 Masalah: Modal Loading State Tidak Efektif (Race Condition)
Livewire **tidak** melakukan partial update di tengah eksekusi method sinkron — `$modalLoading = true` tidak akan pernah ditampilkan ke user karena state `false` sudah diset lagi sebelum response dikirim.

### 🔴 Test Coverage: 0%
Halaman Billing & Usage ini **sepenuhnya tidak ter-cover** oleh automated test.

---

## 4. Rencana Tindak Lanjut

1. **Fix N+1 Query:** Gunakan `->join()` atau preload data actor & project di query `$recentRuns`.
2. **Hapus Log Debug:** Hapus pemanggilan `\Log::info` di `openItems()`.
3. **Optimasi Query:** Refactor `loadCostSummary` agar tidak melakukan query redundan atau gunakan query agregasi yang lebih efisien.
4. **Buat Test:** Buat `AdminApifyFinancialReportTest.php` untuk memastikan fungsi filter, pagination, dan summary berjalan dengan baik tanpa N+1 query.
