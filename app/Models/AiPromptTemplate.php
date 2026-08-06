<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiPromptTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'source_type',
        'system_prompt',
        'user_prompt_template',
        'output_schema',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public static function resolveActiveDefaultForSourceType(string $name, string $sourceType): ?self
    {
        $name = trim($name);
        $sourceType = trim($sourceType);

        return static::query()
            ->where('name', $name)
            ->where('source_type', $sourceType)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    public static function resolvePreferredActiveForSourceType(string $sourceType): ?self
    {
        $sourceType = trim($sourceType);

        return static::query()
            ->where('source_type', $sourceType)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    public static function ensureDefaultForSourceType(string $sourceType): ?self
    {
        $sourceType = trim($sourceType);

        $currentDefault = static::query()
            ->where('source_type', $sourceType)
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if ($currentDefault) {
            return $currentDefault;
        }

        $preferred = static::resolvePreferredActiveForSourceType($sourceType);
        if (! $preferred) {
            return null;
        }

        static::query()
            ->where('source_type', $sourceType)
            ->where('id', '!=', $preferred->id)
            ->update(['is_default' => false]);

        $preferred->forceFill(['is_default' => true])->save();

        return $preferred->refresh();
    }

    public static function saranPortalManualSystemPrompt(): string
    {
        return 'Anda adalah sistem ahli Reverse Engineering & HTML Anatomy Analysis untuk Web Scraping. Tugas Anda adalah membedah arsitektur DOM portal berita dan menghasilkan konfigurasi ekstraksi data (scraping JSON configuration) yang akurat, lengkap, dan konsisten. Jangan mengosongkan field hanya karena ragu jika masih ada petunjuk HTML yang masuk akal; isi kandidat terbaik yang paling mungkin dan turunkan confidence bila bukti lemah.';
    }

    public static function saranPortalManualUserPromptTemplate(): string
    {
        return <<<'PROMPT'
INFO PORTAL TARGET:
- Nama Portal: {name}
- Domain: {domain}
- HTML Mentah: {html}

ATURAN MUTLAK:
1. Nama portal dan domain WAJIB dipakai sebagai identitas utama portal.
2. Input utama WAJIB adalah HTML mentah yang diberikan user.
3. AI WAJIB membaca dan membedah HTML mentah tersebut terlebih dahulu.
4. Fokus utama adalah selector artikel, isi artikel, penulis, tanggal, noise, dan juga search URL.
5. Search URL dan Selector Hasil Pencarian TIDAK boleh dibiarkan kosong hanya karena halaman yang dianalisis adalah artikel. Jika tidak ditemukan di HTML, isi kandidat terbaik berdasarkan struktur situs dan beri confidence rendah.
6. Jika HTML berisi halaman search/result, ambil search URL dan Selector Hasil Pencarian secara eksplisit dari struktur tersebut.
7. Variabel pencarian WAJIB menggunakan placeholder exact: {query} (contoh: /search?key={query} atau /?s={query}).
8. DILARANG mengasumsikan parameter bawaan WordPress (/?s=) jika situs menggunakan route custom seperti /search?key={query}. Jika situs custom, prioritaskan pola custom.
9. Jika domain adalah "arusbawah.co" dan search page ditemukan, search_url yang benar adalah "https://arusbawah.co/search?key={query}".
10. Tipe crawling WAJIB ditentukan otomatis oleh AI dan harus dipilih dari: html, rss, api.
11. Jangan meminta user mengirim HTML atau URL lain. Gunakan HTML yang sudah ada di input.
12. Output harus JSON murni. Jangan tambahkan salam, penjelasan, markdown, atau code fence.
13. Jika satu field tidak punya bukti kuat, tetap isi dengan kandidat terbaik yang paling masuk akal dan jelaskan keraguannya di ai_reason.
14. Jika HTML yang diberikan adalah HTML artikel, tetap upayakan mengisi search_url, search_result_selector, article_link_selector, article_author_selector, dan article_date_selector dari pola situs, sitemap, feed, breadcrumb, atau link internal yang paling dominan.

METODOLOGI:
- Bedah struktur HTML yang diberikan.
- Jika HTML search/result: ambil search URL dan selector hasil pencarian secara eksplisit.
- Jika HTML artikel: fokus pada selector isi artikel, link artikel, penulis, tanggal, noise, dan tetap cari pola search URL serta selector daftar artikel dari struktur situs yang paling mungkin.
- Tentukan crawling_type berdasarkan struktur halaman: html, rss, atau api.
- Jangan mengembalikan field kosong bila masih ada pola yang masuk akal untuk diisi.

KELUARAN:
- Balas hanya JSON valid sesuai schema.
PROMPT;
    }

    public static function saranPortalManualOutputSchema(): string
    {
        return '{"type":"object","properties":{"base_url":{"type":"string"},"crawling_type":{"type":"string"},"search_url":{"type":"string"},"feed_url":{"type":"string"},"sitemap_url":{"type":"string"},"search_result_selector":{"type":"string"},"article_link_selector":{"type":"string"},"article_content_selector":{"type":"string"},"article_noise_selector":{"type":"string"},"article_author_selector":{"type":"string"},"article_date_selector":{"type":"string"},"ai_reason":{"type":"string"},"confidence":{"type":"number"}},"required":["base_url","crawling_type","search_url","feed_url","sitemap_url","search_result_selector","article_link_selector","article_content_selector","article_noise_selector","article_author_selector","article_date_selector","ai_reason","confidence"]}';
    }

    public static function reportAiSystemPrompt(): string
    {
        return 'Anda adalah analis isu berita dan reputasi media untuk laporan eksekutif. Tugas Anda adalah membaca ringkasan statistik, kondisi viral, isu utama, sumber dominan, dan sampel artikel lalu menyusun kesimpulan yang tajam, spesifik, dan berbasis fakta. Hindari kalimat generik. Fokus pada isu berita yang nyata, framing media, arah sentimen, dampak reputasi, kondisi viral, dan tindakan respons yang bisa segera dilakukan.';
    }

    public static function reportAiUserPromptTemplate(): string
    {
        return <<<'PROMPT'
KONTEKS LAPORAN:
- Nama Proyek: {project_name}
- Periode Laporan: {period_start} s/d {period_end}
- Total Penyebutan / Artikel: {total_mentions}
- Sentimen Positif: {positive_count} ({positive_pct}%)
- Sentimen Netral: {neutral_count} ({neutral_pct}%)
- Sentimen Negatif: {negative_count} ({negative_pct}%)
- Kondisi Viral: {viral_status}
- Penjelasan Kondisi Viral: {viral_desc}
- Penyebutan 7 Hari Terakhir: {viral_recent_7d}
- Dasar Penilaian Viral: {viral_basis}
- Sumber Dominan: {top_sources}
- Topik / Kata Kunci Dominan: {top_topics}

SAMPEL BERITA / PENYEBUTAN TERKINI:
{top_articles}

ATURAN WAJIB:
1. Tulis kesimpulan yang benar-benar membaca isu berita yang muncul dari data di atas.
2. Sebutkan isu, aktor, konteks, dan arah pemberitaan secara spesifik.
3. Hindari kalimat umum seperti "kinerja baik" atau "reputasi kuat" tanpa menyebut isu nyata yang terlihat pada data.
4. Rekomendasi harus berupa langkah respons isu yang konkret, relevan dengan pemberitaan, dan bisa ditindaklanjuti.
5. Gunakan bahasa Indonesia formal untuk laporan eksekutif.
6. Buat juga penilaian tersendiri tentang kondisi viral berdasarkan data di atas, lalu masukkan ke dalam ringkasan secara eksplisit.
7. Output harus JSON murni dan hanya berisi tiga key: summary, recommendations, dan viral_condition.

FORMAT OUTPUT:
{
  "summary": "Ringkasan analisis berita yang spesifik, naratif, dan berbasis data.",
  "recommendations": [
    "Rekomendasi respons isu pertama yang konkret.",
    "Rekomendasi respons isu kedua yang konkret.",
    "Rekomendasi respons isu ketiga yang konkret."
  ],
  "viral_condition": "Penilaian khusus kondisi viral yang menjelaskan apakah proyek mulai viral, sangat viral, atau normal beserta alasannya."
}
PROMPT;
    }

    public static function reportAiOutputSchema(): string
    {
        return '{"type":"object","properties":{"summary":{"type":"string"},"recommendations":{"type":"array","items":{"type":"string"}},"viral_condition":{"type":"string"}},"required":["summary","recommendations","viral_condition"]}';
    }

    public static function aiReaderEstimateInstructionBlock(): string
    {
        return <<<'PROMPT'
ATURAN ESTIMASI PEMBACA:
1. Wajib menghasilkan estimasi pembaca dengan field berikut: project_estimated_readers, potential_estimated_readers, potential_reach_score, potential_reach_level, potential_reach_band, local_relevance_score, confidence_score, confidence_level, signals_used, reasoning_summary, limitations, is_exact_reach, reach_method.
2. project_estimated_readers adalah estimasi jumlah pembaca artikel secara umum. Jangan gunakan angka random atau string rentang (misal "10-20"). Nilai ini harus dihitung berdasarkan kekuatan dan skala media, posisi artikel, karakter isu, dan distribusi.
3. potential_estimated_readers adalah estimasi potensi pembaca artikel secara umum. Artikel di portal besar bisa memiliki potential_estimated_readers besar. Nilai ini biasanya hampir sama dengan project_estimated_readers.
4. Jangan mengubah nilai estimasi pembaca menjadi nol.
5. Jika analytics nyata tidak ada, estimasi harus konservatif dan confidence maksimal 69 dengan confidence_level "Medium".
6. Score dan level WAJIB mengikuti tabel berikut berdasarkan potential_estimated_readers:
   - 1-20 pembaca -> Skor 1 (Sangat rendah)
   - 21-40 pembaca -> Skor 2 (Sangat rendah)
   - 41-70 pembaca -> Skor 3 (Rendah)
   - 71-100 pembaca -> Skor 4 (Rendah)
   - 101-150 pembaca -> Skor 5 (Sedang)
   - 151-200 pembaca -> Skor 6 (Sedang)
   - 201-350 pembaca -> Skor 7 (Cukup tinggi)
   - 351-600 pembaca -> Skor 8 (Tinggi)
   - 601-999 pembaca -> Skor 9 (Sangat tinggi)
   - >=1000 pembaca -> Skor 10 (Luar biasa/nasional)
7. potential_reach_band wajib menjelaskan rentang estimasi tersebut.
8. Balas hanya JSON valid tanpa markdown, penjelasan, atau teks tambahan.
PROMPT;
    }

    public static function articleAiSystemPrompt(): string
    {
        return 'Anda adalah AI analis berita senior untuk analisis artikel media. Baca judul, konten, konteks project, sumber, media, dan engagement lalu keluarkan JSON valid. Fokus pada ringkasan, sentimen, isu utama, entitas, risiko, rekomendasi, dan estimasi jangkauan pembaca yang natural, spesifik, dan realistis. Estimasi pembaca harus berupa integer natural, tidak boleh angka generik atau string rentang. Gunakan sinyal kekuatan media, karakter isu, distribusi, dan konteks artikel. ' . static::aiReaderEstimateInstructionBlock();
    }

    public static function articleAiUserPromptTemplate(): string
    {
        return <<<'PROMPT'
ANALISIS BERITA:
- Judul: {title}
- Konten: {content}
- Sumber: {source_name}
- URL: {url}
- Platform: {platform}
- Jenis Media: {media_type}
- Media URL: {media_url}
- Thumbnail URL: {thumbnail_url}
- Penulis: {author_name}
- Tanggal Publikasi: {published_at}
- Engagement: {engagement_context}
- Media Context: {media_context}
- Project Context: {project_context}
- Reach Context: {reach_context}

ATURAN WAJIB:
1. Fokus pada isu berita yang nyata, framing media, sentimen, risiko reputasi, dan relevansi terhadap project.
2. Gunakan konteks project hanya untuk relevansi dan risiko, bukan untuk menurunkan atau menaikkan estimasi pembaca secara artifisial.
3. Balas hanya JSON valid sesuai schema yang diberikan.
PROMPT;
    }

    public static function articleAiOutputSchema(): string
    {
        return '{"type":"object","properties":{"summary":{"type":"string"},"sentiment":{"type":"string"},"sentiment_score":{"type":"number"},"main_issue":{"type":"string"},"entities":{"type":"array"},"risk_level":{"type":"string"},"risk_reason":{"type":"string"},"potential_estimated_readers":{"type":"integer","minimum":1},"project_estimated_readers":{"type":"integer","minimum":1},"potential_reach_score":{"type":"integer","minimum":1,"maximum":10},"potential_reach_level":{"type":"string"},"potential_reach_band":{"type":"string"},"local_relevance_score":{"type":"integer","minimum":0,"maximum":100},"confidence_score":{"type":"integer","minimum":0,"maximum":100},"confidence_level":{"type":"string"},"signals_used":{"type":"array"},"reasoning_summary":{"type":"string"},"limitations":{"type":"string"},"is_exact_reach":{"type":"boolean"},"reach_method":{"type":"string"},"recommendation":{"type":"string"}},"required":["summary","sentiment","sentiment_score","main_issue","entities","risk_level","risk_reason","potential_estimated_readers","project_estimated_readers","potential_reach_score","potential_reach_level","potential_reach_band","local_relevance_score","confidence_score","confidence_level","signals_used","reasoning_summary","limitations","is_exact_reach","reach_method","recommendation"]}';
    }

    public static function socialAiSystemPrompt(): string
    {
        return 'Anda adalah AI analis media sosial. Analisis postingan medsos yang diberikan dan berikan respon dalam format JSON yang valid. Prioritaskan link konten, jenis media, caption, konteks visual, dan engagement untuk menentukan nilai konten. Jangan menebak isi visual secara berlebihan; jika media tidak bisa diakses, sebutkan keterbatasan secara eksplisit di limitations. ' . static::aiReaderEstimateInstructionBlock();
    }

    public static function socialAiUserPromptTemplate(): string
    {
        return <<<'PROMPT'
ANALISIS POSTINGAN MEDIA SOSIAL:
- Platform: {platform}
- URL: {url}
- Media Type: {media_type}
- Media URL: {media_url}
- Thumbnail URL: {thumbnail_url}
- Author: {author_name}
- Konten: {content}
- Engagement: {engagement_context}
- Media Context: {media_context}
- Konteks Project: {project_context}

ATURAN WAJIB:
1. Untuk sosial media, prioritaskan link konten, jenis media, caption, konteks visual, dan engagement bila tersedia.
2. Jika link atau thumbnail mengarah ke video/foto/carousel, gunakan itu sebagai sinyal utama untuk menentukan kontennya.
3. Jangan menebak isi visual secara berlebihan; jika media tidak bisa diakses, tulis keterbatasan secara eksplisit.
4. Balas hanya JSON valid sesuai schema yang diberikan.
PROMPT;
    }

    public static function socialAiOutputSchema(): string
    {
        return '{"type":"object","properties":{"summary":{"type":"string"},"sentiment":{"type":"string"},"sentiment_score":{"type":"number"},"main_issue":{"type":"string"},"entities":{"type":"array"},"risk_level":{"type":"string"},"risk_reason":{"type":"string"},"potential_estimated_readers":{"type":"integer","minimum":1},"project_estimated_readers":{"type":"integer","minimum":1},"potential_reach_score":{"type":"integer","minimum":1,"maximum":10},"potential_reach_level":{"type":"string"},"potential_reach_band":{"type":"string"},"local_relevance_score":{"type":"integer","minimum":0,"maximum":100},"confidence_score":{"type":"integer","minimum":0,"maximum":100},"confidence_level":{"type":"string"},"signals_used":{"type":"array"},"reasoning_summary":{"type":"string"},"limitations":{"type":"string"},"is_exact_reach":{"type":"boolean"},"reach_method":{"type":"string"},"recommendation":{"type":"string"},"content_type":{"type":"string"},"media_type":{"type":"string"},"media_link_used":{"type":"string"},"media_signal":{"type":"string"}},"required":["summary","sentiment","sentiment_score","main_issue","entities","risk_level","risk_reason","potential_estimated_readers","project_estimated_readers","potential_reach_score","potential_reach_level","potential_reach_band","local_relevance_score","confidence_score","confidence_level","signals_used","reasoning_summary","limitations","is_exact_reach","reach_method","recommendation"]}';
    }
}
