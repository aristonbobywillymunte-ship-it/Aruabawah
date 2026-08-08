# AI-READER-BASIS-ACTUAL-OR-ESTIMATED-1

Status: **PLANNED / BELUM DIIMPLEMENTASIKAN**

## Tujuan

Perbaiki pipeline analisis AI untuk Portal dan Sosmed agar AI dapat menentukan apakah estimasi jangkauan/pembaca sebaiknya menggunakan **metric aktual yang benar-benar tersedia dan dipercaya** atau **estimasi AI natural berbasis evidence**.

Prinsip utama:

- AI menentukan **basis pembaca** (`actual` atau `estimated`).
- Jika ada metric aktual yang jelas dan valid, AI boleh menggunakannya.
- Jika tidak ada metric aktual yang cukup dipercaya, AI membuat estimasi natural/granular.
- Laravel tetap menjadi sumber kebenaran untuk **score dan level**.
- Satu konten tetap dianalisis AI sekali secara global.
- Jangan mengubah scraping Portal, Sosmed, Apify, package rules, keyword matching, atau AI Final Quality Gate.

## Baca dulu

Wajib audit sebelum coding:

- `app/Jobs/AiAnalysisJob.php`
- `app/Models/AiAnalysisResult.php`
- `app/Models/Article.php`
- `app/Models/SocialMediaItem.php`
- `app/Services/AiProviderRouter.php`
- prompt/template AI yang aktif
- migration `ai_analysis_results`
- targeted AI tests existing

Jangan refactor area lain.

---

## 1. Scope Portal + Sosmed

Perubahan berlaku untuk:

1. Portal / Article
2. Social Media

Keduanya memakai konsep yang sama:

```text
Evidence
  ↓
AI menentukan basis pembaca
  ↓
actual metric valid?
  ├─ ya → actual
  └─ tidak → estimated
  ↓
effective_readers
  ↓
Laravel menentukan score + level
```

Portal biasanya jatuh ke `estimated`, kecuali memang ada pageview/reach aktual yang eksplisit di payload.

Sosmed lebih sering punya candidate actual metrics seperti views/play count/impressions/reach.

---

## 2. AI wajib menentukan reader basis

Tambahkan instruksi prompt agar AI mengembalikan minimal field berikut:

```json
{
  "reader_basis": "actual|estimated",
  "actual_metric_used": "view_count|null",
  "actual_metric_value": 8432,
  "effective_readers": 8432,
  "reader_basis_reason": "View count tersedia langsung dari platform dan merepresentasikan konsumsi konten.",
  "potential_estimated_readers": 8432
}
```

Jika estimated:

```json
{
  "reader_basis": "estimated",
  "actual_metric_used": null,
  "actual_metric_value": null,
  "effective_readers": 437,
  "reader_basis_reason": "Tidak ada metric konsumsi aktual yang cukup dipercaya; estimasi dibuat dari engagement dan karakteristik konten.",
  "potential_estimated_readers": 437
}
```

`potential_estimated_readers` dipertahankan untuk backward compatibility, tetapi nilainya harus konsisten dengan `effective_readers`.

---

## 3. Metric yang boleh dianggap actual

AI BOLEH memilih basis `actual` hanya jika evidence menunjukkan metric konsumsi/penayangan konten yang nyata dan dapat dipercaya.

Candidate yang boleh dipertimbangkan:

- `view_count`
- `play_count`
- `video_views`
- `views`
- `reach` jika memang merupakan metric platform aktual
- `impressions` jika memang mewakili exposure aktual
- pageview aktual untuk portal jika source/payload memang menyediakan

JANGAN menganggap field berikut sebagai pembaca aktual:

- `follower_count`
- `like_count`
- `comment_count`
- `share_count`
- jumlah hashtag
- jumlah mention
- angka lain di raw JSON yang tidak jelas semantiknya

Contoh salah:

```text
followers = 10.000
→ readers = 10.000  // DILARANG
```

Contoh salah:

```text
likes = 500
→ readers = 500  // DILARANG
```

---

## 4. Validasi actual metric

AI jangan otomatis percaya angka > 0.

AI harus menilai apakah metric:

- berasal dari field yang semantiknya jelas;
- bukan placeholder/default;
- bukan agregat akun;
- bukan follower count;
- bukan duplicated metric yang tidak konsisten;
- masuk akal terhadap platform/content type;
- punya konteks cukup untuk disebut actual consumption/exposure.

Jika ragu, gunakan `estimated`.

Prinsip:

```text
When uncertain → estimate, do not pretend actual.
```

---

## 5. Natural estimate jika basis = estimated

Jika actual metric tidak layak, AI membuat `effective_readers` sebagai estimasi natural/granular berbasis evidence.

Evidence Portal:

- sumber/media
- judul
- isi
- tokoh/subjek
- skala isu
- lokasi
- nilai berita
- potensi kepentingan publik
- metadata reach/pageview jika bukan actual tetapi masih berguna sebagai signal

Evidence Sosmed:

- platform
- content/caption
- author
- follower_count sebagai signal (BUKAN actual readers)
- views jika tidak valid sebagai actual tapi masih relevan sebagai supporting signal
- likes
- comments
- shares
- engagement
- hashtags
- comments context
- media type

Hindari angka pola/placeholder jika tidak ada alasan kuat:

```text
10, 20, 25, 50, 75, 100, 150, 200, 250, 300, 500, 750, 1000
```

Gunakan estimasi natural seperti:

```text
127
183
324
571
843
1237
```

Angka contoh hanya menunjukkan gaya natural, bukan nilai yang harus ditiru.

DILARANG memakai:

- `rand()`
- `random_int()`
- `mt_rand()`
- jitter/randomness palsu

Nilai tetap evidence-based.

---

## 6. Backend source of truth: effective_readers

Backend harus mempunyai satu angka canonical untuk score/level: `effective_readers`.

Aturan:

```text
if reader_basis == actual:
    effective_readers = validated actual_metric_value
else:
    effective_readers = AI natural estimate
```

Jika output AI tidak konsisten, backend harus mengoreksi atau menolak.

Contoh:

```json
{
  "reader_basis": "actual",
  "actual_metric_value": 8432,
  "effective_readers": 571
}
```

Tidak valid. Backend harus memastikan `effective_readers = 8432` bila basis actual diterima.

Jika basis estimated:

```json
{
  "reader_basis": "estimated",
  "actual_metric_value": null,
  "effective_readers": 571
}
```

valid.

---

## 7. Score dan level deterministik Laravel

Score dan level TIDAK BOLEH dipercayakan ke AI.

Laravel wajib menghitung dari `effective_readers` dengan mapping berikut:

```text
1–20     → Score 1  → Sangat rendah
21–40    → Score 2  → Sangat rendah
41–70    → Score 3  → Rendah
71–100   → Score 4  → Rendah
101–150  → Score 5  → Sedang
151–200  → Score 6  → Sedang
201–350  → Score 7  → Cukup tinggi
351–600  → Score 8  → Tinggi
601–999  → Score 9  → Sangat tinggi
>=1000   → Score 10 → Luar biasa/nasional
```

Contoh:

```text
127  → 5 → Sedang
183  → 6 → Sedang
324  → 7 → Cukup tinggi
571  → 8 → Tinggi
843  → 9 → Sangat tinggi
1237 → 10 → Luar biasa/nasional
8432 → 10 → Luar biasa/nasional
```

Jika AI mengembalikan score/level berbeda, Laravel override.

---

## 8. Persistence / schema

Audit schema existing terlebih dahulu.

Jangan menyalahgunakan field legacy bila maknanya berbeda.

Jika field belum ada, buat migration additive untuk minimal:

- `reader_basis` nullable string
- `actual_metric_used` nullable string
- `actual_metric_value` nullable bigint/integer sesuai DB
- `effective_readers` nullable bigint/integer
- `reader_basis_reason` nullable text/string

Pertahankan field existing:

- `potential_estimated_readers`
- `potential_reach_score`
- `potential_reach_level`
- `potential_reach_band`

Backward compatibility:

- legacy rows dengan field baru null tetap valid;
- jangan memaksa backfill produksi pada task ini;
- jangan drop/rename field existing.

Jika `effective_readers` ditambahkan, untuk analysis baru isi:

```text
potential_estimated_readers = effective_readers
```

agar UI lama tidak pecah sampai seluruh query pindah ke field canonical baru.

---

## 9. Social raw_json sync

Untuk SocialMediaItem, sinkronkan metadata AI baru ke `raw_json.ai_analysis` secara additive:

- reader_basis
- actual_metric_used
- actual_metric_value
- effective_readers
- reader_basis_reason

Jangan menghapus metadata existing seperti sentiment, risk, quality gate, reach fields.

---

## 10. Quality Gate tetap utuh

JANGAN ubah konsep atau perilaku:

- `is_noise`
- `noise_reason`
- `subjects`
- `quality_confidence`

Urutan business logic:

```text
AI analysis
  ↓
quality gate
  ↓
noise?
  ├─ ya → raw tetap disimpan, dashboard disaring
  └─ tidak → normal

Reader basis tetap disimpan untuk audit walau konten noise.
```

Notification tetap tidak boleh dipicu oleh konten yang sudah dinyatakan noise.

---

## 11. Portal examples

### Portal tanpa actual pageview

```text
source = media lokal
pageview = tidak tersedia

AI:
reader_basis = estimated
effective_readers = 183

Laravel:
score = 6
level = Sedang
```

### Portal dengan actual pageview yang benar-benar tersedia

```text
pageviews = 1374
source jelas = analytics/source metric

AI boleh:
reader_basis = actual
actual_metric_used = pageviews
actual_metric_value = 1374
effective_readers = 1374

Laravel:
score = 10
level = Luar biasa/nasional
```

Jangan mencari pageview dari internet dalam runtime AI. Hanya gunakan evidence yang memang tersedia pada payload/context.

---

## 12. Social examples

### TikTok actual view tersedia

```text
view_count = 8432
likes = 512
comments = 73
shares = 91

AI:
reader_basis = actual
actual_metric_used = view_count
actual_metric_value = 8432
effective_readers = 8432
```

### Instagram tanpa trustworthy actual reach

```text
view_count = 0
followers = 3200
likes = 87
comments = 12
shares = 7

AI:
reader_basis = estimated
actual_metric_used = null
actual_metric_value = null
effective_readers = 437
```

followers hanya signal, bukan actual readers.

---

## 13. Prompt wajib menjelaskan alasan

`reader_basis_reason` wajib pendek dan audit-friendly.

Contoh actual:

```text
"View count tersedia langsung dari payload platform dan merepresentasikan jumlah penayangan konten."
```

Contoh estimated:

```text
"Tidak ada metric konsumsi aktual yang cukup dipercaya; estimasi dibuat dari platform, engagement, dan karakteristik konten."
```

Jangan memasukkan chain-of-thought panjang. Alasan cukup ringkas.

---

## 14. Tests wajib

Gunakan fake/mock provider. Jangan call AI real.

Minimal test:

1. Social view_count valid → AI basis actual → effective_readers memakai actual.
2. Social follower_count saja → basis estimated, follower tidak dianggap actual.
3. Social likes/comments/shares saja → estimated.
4. Social view_count=0 → estimated.
5. Portal tanpa pageview → estimated.
6. Portal actual pageview valid → actual.
7. AI actual output tidak konsisten → backend canonicalizes/validates.
8. Score AI salah → Laravel override dari effective_readers.
9. Level AI salah → Laravel override.
10. Natural estimated examples mapping:
   - 127 → 5 / Sedang
   - 183 → 6 / Sedang
   - 324 → 7 / Cukup tinggi
   - 571 → 8 / Tinggi
   - 843 → 9 / Sangat tinggi
   - 1237 → 10 / Luar biasa/nasional
11. Actual 8432 → 10 / Luar biasa/nasional.
12. Boundary mapping lengkap:
   - 20/21
   - 40/41
   - 70/71
   - 100/101
   - 150/151
   - 200/201
   - 350/351
   - 600/601
   - 999/1000
13. Quality gate fields tetap tersimpan.
14. Noise tetap tidak memicu notifikasi.
15. Social raw_json menyimpan reader basis metadata.
16. Legacy row dengan field baru null tidak error.

---

## 15. Jangan ubah

JANGAN ubah:

- Apify scraping
- website/portal scraping
- social scraping payload
- package rules
- token failover
- keyword matching
- exclude/context keyword semantics
- one-content-one-global-AI-analysis
- provider fallback
- queue architecture
- project relation

Scope hanya analysis/persistence/readers mapping.

---

## 16. Server execution

Kerjakan langsung di repo server yang aktif sesuai workflow existing.

Sebelum edit:

```bash
cd /home/ubuntu/apps/proyek-baru
git status --short
git pull origin main
```

Jangan overwrite perubahan lokal server yang belum committed.

Setelah coding, jika migration dibuat:

```bash
sudo docker compose exec -T media-intelligent php artisan migrate --force
```

Syntax checks minimal:

```bash
sudo docker compose exec -T media-intelligent php -l app/Jobs/AiAnalysisJob.php
sudo docker compose exec -T media-intelligent php -l app/Models/AiAnalysisResult.php
```

Jalankan targeted AI tests saja.

Jika `AiAnalysisJob` berubah:

```bash
sudo docker compose exec -T media-intelligent php artisan queue:restart
```

Jangan menjalankan scraping Portal/Sosmed baru hanya untuk test.
Jangan call provider AI produksi nyata dalam automated tests.

---

## 17. Dokumentasi

Setelah test benar-benar hijau, update file ini:

```text
Status: COMPLETED
```

Tambahkan ringkasan:

- migration yes/no
- reader basis implementation
- Portal actual/estimated behavior
- Social actual/estimated behavior
- backend score deterministic yes/no
- random used: NO
- quality gate preserved yes/no
- scraping changed: no
- queue restart yes/no
- secret exposed: no
- targeted test result
- runtime QA result

---

## 18. Git

Commit hanya file terkait.

Suggested commit:

```text
feat: let AI choose actual or estimated reader basis
```

Jangan force push.
Jangan commit `.env`, PEM, credential, token, dump, atau temporary files.

---

## Final report

Laporkan singkat:

- root design implemented;
- files changed;
- schema/migration changed yes/no;
- how AI decides actual vs estimated;
- which metrics count as actual;
- examples Portal + Social;
- how effective_readers is canonicalized;
- score/level deterministic mapping;
- random used: NO;
- quality gate preserved;
- targeted tests;
- migration run yes/no;
- queue restart yes/no;
- scraping behavior changed: NO;
- secret exposed: NO;
- commit SHA;
- remaining blocker.

Jangan klaim selesai sebelum tests membuktikan follower/likes/comments tidak salah dianggap actual readers dan score/level selalu mengikuti effective_readers.
