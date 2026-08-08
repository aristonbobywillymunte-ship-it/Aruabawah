# AI-FINAL-QUALITY-GATE-1

Status: **PLANNED / SERVER IMPLEMENTATION REQUIRED**
Tanggal: **2026-08-09**

## Tujuan
Menjadikan AI sebagai quality gate terakhir untuk konten Portal dan Sosmed tanpa mengubah konsep satu konten = satu analisis AI global.

Desain yang wajib dipertahankan:
- Paket tidak perlu mengatur quota/enable AI.
- Satu Article/SocialMediaItem dianalisis AI sekali secara global.
- Keyword pencarian/penyaring/exclude tetap menjadi candidate filter murah sebelum AI.
- AI tidak menyimpan `relevant=true/false` per proyek secara global.
- AI boleh menentukan kualitas global: `is_noise`, `noise_reason`, `subjects`.
- Raw content tidak pernah dihapus hanya karena AI menilai noise.
- Dashboard menyembunyikan hanya konten yang sudah dianalisis dan `is_noise=true`; konten belum dianalisis tetap boleh tampil agar rollout konservatif.

## Repo server
Path: `/home/ubuntu/apps/proyek-baru`
Container app: `media-intelligent`

## Baca dulu
- `app/Jobs/AiAnalysisJob.php`
- `app/Models/AiAnalysisResult.php`
- `app/Models/Article.php`
- `app/Models/SocialMediaItem.php`
- `app/Livewire/MediaDashboard.php`
- `app/Services/AiAnalysisDispatchStateService.php`
- `app/Services/ContentMatchingService.php`
- migration `ai_analysis_results` yang existing
- test AI pipeline/dashboard yang relevan

## Implementasi

### 1. Tambahkan field quality gate ke `ai_analysis_results`
Buat migration baru additive saja:
- `is_noise` boolean nullable, default NULL.
- `noise_reason` text nullable.
- `subjects` json nullable.
- `quality_confidence` unsigned small integer nullable (0-100).

Semantik:
- `is_noise = NULL` => belum ada keputusan quality gate / legacy result.
- `is_noise = false` => AI menilai konten layak/non-noise.
- `is_noise = true` => AI menilai konten noise/spam/tidak substantif secara global.

Jangan membuat `is_relevant_to_project` karena analisis AI tetap global.

### 2. Update `AiAnalysisResult`
Tambahkan field baru ke `$fillable` dan cast:
- `is_noise` => boolean
- `subjects` => array
- `quality_confidence` => integer

Tambahkan scope:
```php
public function scopeVisibleToDashboard(Builder $query): Builder
{
    return $query->where(function ($q) {
        $q->whereNull('is_noise')->orWhere('is_noise', false);
    });
}
```

### 3. Perluas prompt AI di `AiAnalysisJob::buildPrompt()`
Jangan mengganti prompt lama. Tambahkan instruction additive setelah schema existing:

```text
QUALITY GATE GLOBAL (WAJIB):
Nilai kualitas konten secara global, bukan relevansi terhadap satu proyek.
Kembalikan juga field:
- is_noise: boolean
- noise_reason: string|null
- subjects: array<string>
- quality_confidence: integer 0-100

Definisi noise=true hanya jika konten jelas spam/noise/tidak substantif, misalnya caption repetitif tak bermakna, promosi/hashtag stuffing yang tidak membawa isi substantif, placeholder, atau penyebutan target yang hanya incidental dan tidak memiliki substansi berita/post.
Jangan menandai noise hanya karena teks pendek, banyak emoji, satir, bahasa informal, atau sentimen negatif.
Jika ragu, pilih is_noise=false dan confidence lebih rendah.
subjects harus berisi subjek/orang/lembaga/topik utama yang benar-benar dibahas dalam konten.
noise_reason wajib singkat dan tidak mengandung secret.
```

Penting: `{project_context}` tetap boleh membantu sentiment/risk, tetapi keputusan `is_noise` harus global agar satu hasil AI bisa dipakai lintas proyek.

### 4. Normalisasi output AI
Di `normalizeAnalysisResult()`:
- baca `is_noise` secara ketat sebagai boolean;
- jika field tidak ada, simpan `null` agar legacy/provider lama tidak salah dianggap clean/noise;
- `noise_reason` maksimal ~500 karakter;
- `subjects` hanya array string, trim, unique, maksimal 20 item;
- `quality_confidence` clamp 0..100 atau null jika tidak ada.

Tambahkan ke array `$normalized` sehingga `persistAnalysis()` otomatis menyimpan field baru.

Jangan membuat validation reach gagal hanya karena provider lama belum mengirim quality field. Quality gate additive dan backward-compatible.

### 5. Sync social raw_json
Di `syncSourceRecord()` untuk social, tambahkan ke `raw_json.ai_analysis`:
- `is_noise`
- `noise_reason`
- `subjects`
- `quality_confidence`

Jangan hapus field AI existing.

### 6. Dashboard final filter konservatif
Di `MediaDashboard::projectArticlesQuery()`:

Portal query:
- LEFT JOIN `ai_analysis_results` dengan `article_id = articles.id` dan `social_media_item_id IS NULL`.
- exclude hanya jika AI result `is_noise = true`.
- legacy/no AI/null tetap tampil.

Semantik SQL kira-kira:
```sql
AND (ai_analysis_results.is_noise IS NULL OR ai_analysis_results.is_noise = false)
```

Social query:
- LEFT JOIN `ai_analysis_results` dengan `social_media_item_id = social_media_items.id`.
- exclude hanya `is_noise = true`.
- no AI/null tetap tampil.

Pastikan JOIN tidak menduplikasi row. Jika schema memungkinkan lebih dari satu analysis record per source, gunakan subquery/latest/unique approach yang aman. Audit unique constraints sebelum memilih join.

Jangan mengubah project keyword matching/pivot pada fase ini. Noise hanya menjadi final display veto.

### 7. Jangan hapus relation project
Jika AI menilai noise:
- jangan detach `project_articles`;
- jangan detach `project_social_media_items`;
- jangan delete Article/SocialMediaItem;
- jangan delete analysis.

Tujuannya auditability. Hanya dashboard utama yang menyembunyikan.

### 8. Notifikasi
Jika `is_noise=true`, jangan kirim risk notification/Telegram walaupun model AI juga menghasilkan risk tinggi.

Update `$shouldNotify` supaya mensyaratkan:
```php
($normalized['is_noise'] ?? false) !== true
```

### 9. Realtime/cache
Karena dashboard cache bergantung pada project `updated_at`, pastikan saat AI quality gate selesai project yang terkait ikut ter-refresh seperti behavior existing.

Karena satu content dapat terkait banyak project, jika memungkinkan touch semua project relasi content yang dianalisis, bukan hanya payload `project_id`, agar dashboard proyek lain tidak menyimpan cache noise lama.

Implementasi aman:
- Article => touch semua `$article->projects()` terkait.
- Social => touch semua `$socialItem->projects()` terkait.

Hindari N+1 yang tidak perlu.

## Test wajib
Tambahkan targeted test untuk minimal:
1. AI output `is_noise=false` tersimpan dengan subjects/reason/confidence.
2. AI output `is_noise=true` tersimpan.
3. Missing quality fields tetap backward-compatible (`is_noise=null`) dan analysis lama tetap sukses jika reach valid.
4. Social raw_json mendapat metadata quality gate.
5. Portal `is_noise=true` tidak muncul di dashboard project.
6. Portal `is_noise=false` muncul.
7. Portal belum punya AI result tetap muncul.
8. Social `is_noise=true` tidak muncul.
9. Social `is_noise=false` muncul.
10. Social belum dianalisis tetap muncul.
11. Noise item tetap ada di DB dan pivot project tidak dihapus.
12. `is_noise=true` tidak mengirim risk notification.
13. One content + multiple projects tetap mempunyai satu AI analysis result global.
14. Project A/B tidak membuat duplicate AI dispatch untuk content yang sama.

Gunakan `Http::fake`, `Queue::fake`, `Event::fake` sesuai kebutuhan. Jangan panggil AI provider real.

## QA server
Jalankan di VPS:
```bash
cd /home/ubuntu/apps/proyek-baru
sudo docker compose exec -T media-intelligent php artisan migrate --force
sudo docker compose exec -T media-intelligent php -l app/Jobs/AiAnalysisJob.php
sudo docker compose exec -T media-intelligent php -l app/Models/AiAnalysisResult.php
sudo docker compose exec -T media-intelligent php -l app/Livewire/MediaDashboard.php
sudo docker compose exec -T media-intelligent php artisan test --filter=AiAnalysis
sudo docker compose exec -T media-intelligent php artisan test --filter=MediaDashboard
sudo docker compose exec -T media-intelligent php artisan route:list
sudo docker compose exec -T media-intelligent php artisan view:clear
git diff --check
```

Jika nama test suite berbeda, jalankan targeted test file yang benar. Tidak perlu full suite kecuali targeted test menunjukkan regression.

### Queue reload
Karena `AiAnalysisJob` berubah, setelah test hijau:
```bash
sudo docker compose exec -T media-intelligent php artisan queue:restart
```
Lalu pastikan worker ai-analysis hidup lagi dan queue tidak stuck.

## Runtime QA aman
Jangan melakukan scraping massal.
Gunakan satu konten existing atau test content yang aman:
- satu content normal => is_noise=false;
- satu noise/spam obvious => is_noise=true;
- verifikasi raw content tetap ada;
- verifikasi noise tidak muncul di dashboard;
- verifikasi non-noise muncul;
- verifikasi satu content yang terkait dua project tetap hanya punya satu `ai_analysis_results` record.

## Catatan produk
Jangan otomatis memindahkan potongan spam ke `exclude_keywords`.
Kata Kunci Pengecualian tetap blacklist manual/deterministik.
AI quality gate adalah layer terpisah.

## Final report
Laporkan ringkas:
- root cause/desain;
- file berubah;
- migration;
- prompt quality gate final;
- bagaimana noise disimpan;
- bagaimana dashboard memfilter;
- bagaimana multi-project tetap satu AI analysis;
- targeted test results;
- migration run yes/no;
- queue restart yes/no;
- runtime QA result;
- token/secret exposed yes/no;
- commit SHA;
- blocker tersisa.

Jangan klaim selesai sebelum migration + targeted test + queue restart + runtime QA aman selesai.
