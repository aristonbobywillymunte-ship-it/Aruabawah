<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiPromptTemplate extends Model
{
    use SoftDeletes;

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
        return 'Anda adalah analis isu berita dan reputasi media untuk laporan eksekutif. Tugas Anda adalah membaca ringkasan statistik, isu utama, sumber dominan, dan sampel artikel lalu menyusun kesimpulan yang tajam, spesifik, dan berbasis fakta. Hindari kalimat generik. Fokus pada isu berita yang nyata, framing media, arah sentimen, dampak reputasi, dan tindakan respons yang bisa segera dilakukan.';
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
6. Output harus JSON murni dan hanya berisi dua key: summary dan recommendations.

FORMAT OUTPUT:
{
  "summary": "Ringkasan analisis berita yang spesifik, naratif, dan berbasis data.",
  "recommendations": [
    "Rekomendasi respons isu pertama yang konkret.",
    "Rekomendasi respons isu kedua yang konkret.",
    "Rekomendasi respons isu ketiga yang konkret."
  ]
}
PROMPT;
    }

    public static function reportAiOutputSchema(): string
    {
        return '{"type":"object","properties":{"summary":{"type":"string"},"recommendations":{"type":"array","items":{"type":"string"}}},"required":["summary","recommendations"]}';
    }
}
