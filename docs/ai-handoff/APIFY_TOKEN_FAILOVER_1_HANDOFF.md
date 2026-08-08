# APIFY-TOKEN-FAILOVER-1 — Bounded Token Failover

Status: **PLANNED / BELUM DIIMPLEMENTASIKAN**  
Tanggal: **2026-08-08**

## Tujuan
Perbaiki failover token Apify agar token utama + 3 backup dapat dipakai secara aman tanpa risiko redispatch job tak terbatas saat quota/token bermasalah.

## Context yang wajib dibaca
- `app/Models/ApifySetting.php`
- `app/Jobs/ApifyScrapingJob.php`
- `app/Livewire/Admin/ApifyConfiguration.php`
- `tests/Feature/ApifyDispatchTest.php`

Masalah saat ini:
1. Error quota/credential memanggil `rotateToNextToken()` lalu `self::dispatch($retryParams)`.
2. Job baru dapat membuat job baru lagi; `attempts()` tidak menjadi batas global antar-job.
3. `rotateToNextToken()` memilih token yang hanya `filled()`, bukan harus `connected/ready`.
4. Jika hanya satu token terisi atau semua token limit, rotasi dapat kembali ke token yang sudah gagal.

## Implementasi

### 1. Ubah pemilihan token menjadi bounded failover
Di `ApifySetting`, sediakan helper yang dapat:
- mengambil token/status berdasarkan index 0..3;
- memilih token `filled` + status `connected/ready`;
- mengecualikan index yang sudah dicoba;
- mengembalikan `null` jika tidak ada token lain yang layak.

Jangan memilih ulang token yang sudah gagal dalam execution yang sama.

### 2. Jangan redispatch job baru untuk rotasi token
Hapus pola rotasi quota/credential yang bergantung pada:

```php
self::dispatch($retryParams);
```

Untuk error token/quota, lakukan failover terkontrol di execution job yang sama. Maksimal setiap index token dicoba satu kali.

Target flow:

```text
Main gagal quota -> Backup 1 -> Backup 2 -> Backup 3 -> STOP
```

Jika salah satu sukses:
- simpan index tersebut sebagai `active_token_index`;
- lanjutkan processing normal;
- jangan mencoba token lain.

### 3. Bedakan error yang boleh memicu token failover
Failover token hanya untuk credential/quota yang memang menunjukkan token/account tidak dapat dipakai, termasuk existing classifier seperti:
- `monthly usage hard limit exceeded`
- `platform-feature-disabled`
- `user-or-token-not-found`
- `insufficient-permissions`
- invalid token/credential yang relevan

Jangan rotasi token untuk:
- timeout/network error;
- HTTP 5xx umum;
- Actor FAILED karena payload/actor;
- dataset fetch failure;
- error aplikasi lain yang tidak terkait token/quota.

Error non-token tetap memakai mekanisme retry/cooldown normal yang sudah ada.

### 4. Semua token tidak tersedia
Jika semua token eligible sudah dicoba/gagal:
- hentikan job;
- jangan dispatch job rotasi baru;
- actor/state harus mencatat kegagalan dengan code/marker stabil, gunakan `APIFY_ALL_TOKENS_EXHAUSTED` atau ekuivalen yang konsisten;
- pesan aman: `Semua token Apify tidak tersedia atau mencapai limit.`;
- pasang cooldown agar scheduler tidak menembak Apify terus-menerus;
- jangan bocorkan token di log/error.

Pertahankan reset cooldown/error saat admin menyimpan atau berhasil mengetes token baru.

## Constraints
- Jangan ubah schema/migration kecuali benar-benar wajib.
- Jangan ubah payload actor, scraping result mapping, package rules, atau scheduler interval di luar kebutuhan failover.
- Token tetap encrypted dan tidak boleh muncul di log/test output.
- Pertahankan kompatibilitas `active_token_index` 0..3 dan status koneksi per-token.
- Jangan mengubah website scraping; scope ini hanya Apify/social.
- QA besar di akhir saja. Fase ini cukup targeted test + syntax/diff checks.

## Targeted tests wajib
Tambahkan/perbarui test minimal untuk:
1. Main quota gagal -> Backup 1 connected berhasil.
2. Main + Backup 1 gagal -> Backup 2 berhasil.
3. Backup `belum_dicek`/`error` dilewati.
4. Hanya satu token terisi dan quota habis -> berhenti tanpa redispatch baru.
5. Semua 4 token quota habis -> setiap token maksimal sekali, lalu `APIFY_ALL_TOKENS_EXHAUSTED`.
6. Timeout/HTTP 5xx biasa tidak menyebabkan rotasi token.
7. Token sukses menjadi `active_token_index` baru.
8. Token value tidak muncul dalam log/error state.

Gunakan `Http::fake()` + `Queue::fake()` bila relevan; jangan melakukan request Apify nyata dalam automated test.

## Verifikasi minimum
Jalankan hanya yang relevan:

```bash
php -l app/Models/ApifySetting.php
php -l app/Jobs/ApifyScrapingJob.php
php artisan test --filter=ApifyDispatchTest
php artisan route:list
php artisan view:clear
git diff --check
```

Tidak perlu full test suite pada fase kecil ini.

## Final report wajib
Laporkan singkat:
- root cause;
- file berubah;
- bentuk bounded failover final;
- hasil 8 skenario targeted test;
- apakah ada migration: yes/no;
- apakah scheduler/queue behavior lain berubah: yes/no;
- apakah token/secret terekspos: yes/no;
- blocker tersisa.

Jangan mengklaim selesai sebelum test membuktikan tidak ada redispatch loop saat semua token gagal.
