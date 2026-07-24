<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiPromptTemplate extends Model
{
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
}
