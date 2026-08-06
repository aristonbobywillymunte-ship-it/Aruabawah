# 📋 Laporan Audit: Dashboard Administrator (System Health)

Berikut hasil audit menyeluruh dari semua file terkait halaman **Admin Dashboard / System Health** pada project Laravel.

---

## 1. ROUTE & LAYOUT

**Blade View Utama:** `/Users/unity/Documents/proyek baru/resources/views/admin/dashboard.blade.php`

**Fitur:**
- Menampilkan header "Dashboard Administrator".
- Banner alert untuk error scraping dari ApifyActor dengan pesan `Kendala Pengambilan Data Media Sosial`.
- Memanggil komponen Livewire `<livewire:admin.system-health />` sebagai inti dashboard.

---

## 2. LIVEWIRE COMPONENT — `SystemHealth.php`

**File:** `/Users/unity/Documents/proyek baru/app/Livewire/Admin/SystemHealth.php`

### Fungsi Utama:
Menyediakan status kesehatan (health check) dari 8 elemen penting platform:
1. **AI Provider** (Default, Fallback, Queue)
2. **Apify Scrapers** (Tokens, Active Actors, Queue, Failures)
3. **Scraping Queue** (Pending & Failed Tasks)
4. **Telegram Bot** (Status kredensial)
5. **Database Utama** (PostgreSQL Connection)
6. **Redis Service** (Ping & Queue Count)
7. **Scheduler (Cron)** (Heartbeat cache)
8. **Reverb Server** (WebSocket)

Mendukung 3 modal queue:
- **AI Queue Modal** (Pagination)
- **Apify Queue Modal** (Max 50 terakhir)
- **Redis Queue Modal** (Live dari `lrange` & `zrange`)

---

## 3. ⚠️ POTENSI MASALAH & BUG (TEMUAN AUDIT)

### 🔴 Bug N+1 Query Massive di Berbagai Modal
Komponen ini sangat rawan terhadap N+1 Query Problem di berbagai fitur modal:

1. **AI Queue (`getQueueData`)**: 
   Setiap data (15 item/page) memanggil query tambahan ke tabel `projects`, `articles`, dan `social_media_items`. 
   **Dampak:** 15 x 3 = 45 query redundan per render modal.

2. **Apify Queue (`openApifyQueueModal`)**:
   Melakukan iterasi hingga 50 data `apify_dispatch_states`, dan setiap baris melakukan 2 query ke tabel `projects` dan `apify_actors`.
   **Dampak:** 50 x 2 = 100 query redundan per render modal.

3. **Redis Queue (`parseRedisJobPayload`)**:
   Loop iterasi dari raw redis payloads. Jika payload mengandung id project, secara manual query `DB::table('projects')->where('id', $projId)`.
   **Dampak:** Tergantung jumlah queue Redis, bisa mencetak ratusan query mendadak yang membebani database dan blocking Redis.

### 🟡 Bug Logic: Error Cleansing
Method `clearErrors()` melakukan update massive ke database tanpa index batasan yang optimal, langsung membersihkan ratusan data error message di `ScrapingItem` dan mengubah `status` menjadi 'failed' tanpa limitasi chunk. Pada DB produksi besar, query ini bisa freeze (lock table).

### 🔴 Test Coverage: 0%
Fitur semasif dan sekrusial Dashboard Health Check ini (terutama yang membaca Redis Queue secara native) **sama sekali tidak memiliki automated tests** di folder `tests/Feature/`.

---

## 4. Rencana Tindak Lanjut

1. **Fix N+1 Query `openApifyQueueModal`**: Gunakan `->leftJoin('projects')` dan `->leftJoin('apify_actors')`.
2. **Fix N+1 Query `getQueueData`**: Preload relations untuk array `$project_ids`, `$article_ids`, dan `$social_ids` via query array chunks (`whereIn`), kemudian dipetakan ke memory.
3. **Fix N+1 Query Redis (`parseRedisJobPayload`)**: Kumpulkan semua `project_id` dari string parsing JSON, lalu query hanya 1x (`whereIn('id', $projIds)->pluck('name', 'id')`), kemudian injeksikan nama project ke hasil mapping.
4. **Buat Unit Test**: Buat class test `AdminSystemHealthTest.php` untuk memastikan Dashboard tidak meledak jika Redis down / Database down.

---

## 5. Pertanyaan Operasional: AI Provider "Aktif Utama"

**Q: Kenapa pada dashboard bagian AI Provider, tulisan "Aktif Utama" isinya cuman 1 provider (contoh: `test1 (qwen3-vl-8b-instruct)`)?**

**A:** Secara arsitektur database dan *routing* AI di project ini, sistem memang dirancang agar **hanya ada tepat 1 (satu) AI Provider** yang berstatus sebagai **Default Utama** (`is_default = true`). 
Sedangkan sisa provider lainnya yang berstatus aktif akan otomatis dianggap sebagai **Fallback (cadangan)** (ditampilkan sebagai "Tersedia (3)" pada UI).

**Alasan desain ini:**
1. **Efisiensi Biaya:** Sistem selalu mencoba menggunakan model utama yang paling stabil/murah terlebih dahulu untuk memproses ribuan data secara paralel.
2. **Auto-Routing:** Jika Provider Utama gagal, *time-out*, atau terkena *rate limit*, *Router AI* akan secara otomatis mengalihkan (fallback) antrean yang gagal tersebut ke salah satu dari 3 provider cadangan tanpa perlu intervensi manusia.
Oleh karena itu, tampilan "Aktif Utama" hanya memunculkan 1 nama saja.
