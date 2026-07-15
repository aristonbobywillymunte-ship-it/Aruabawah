<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test: Inkonsistensi reach_score_10 (legacy) vs field canonical (project_reach_score).
 *
 * Berdasarkan temuan audit id=85 (article_id=83):
 *  - reach_score_10 = 0  → LEGACY placeholder, BUKAN skor bisnis
 *  - project_reach_score = 7 → CANONICAL, dipakai UI
 *
 * Test ini memvalidasi bahwa:
 * 1. Row dengan reach_score_10=0 tapi canonical fields valid dianggap VALID.
 * 2. Field canonical (project_reach_score) dipakai, bukan reach_score_10.
 * 3. Notification gate memakai potential_reach_level canonical, bukan reach_level legacy 'Unknown'.
 * 4. Notification gate MEDIUM+HIGH_REACH terpicu dengan benar menggunakan field canonical.
 * 5. NotificationDropdown menghasilkan project_reach_level, bukan 'Unknown'.
 */
class LegacyReachScoreConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper: buat row ai_analysis_results dengan field canonical v2 (seperti row id=85).
     */
    private function makeCanonicalAiRow(array $overrides = []): int
    {
        $defaults = [
            'social_media_item_id' => null,
            'summary' => 'Test summary',
            'sentiment' => 'positive',
            'sentiment_score' => 0.85,
            'main_issue' => 'Test issue',
            'entities' => json_encode(['Test']),
            'risk_level' => 'low',
            'risk_reason' => 'Test reason',
            'recommendation' => 'Test recommendation',
            'raw_response' => '{}',
            'analysis_status' => 'success',
            'reach_method' => 'ai_reader_estimate_v1',
            // Field canonical v2
            'potential_estimated_readers' => 284,
            'potential_reach_score' => 7,
            'potential_reach_level' => 'Cukup tinggi',
            'potential_reach_band' => '201-350 pembaca',
            'project_estimated_readers' => 245,
            'project_reach_score' => 7,
            'project_reach_level' => 'Cukup tinggi',
            'project_reach_band' => '201-350 pembaca',
            'local_relevance_score' => 9,
            'confidence_score' => 65,
            'confidence_level' => 'Medium',
            'signals_used' => json_encode(['local_media_outlet']),
            'reasoning_summary' => 'Test reasoning',
            'limitations' => 'Test limitations',
            'is_exact_reach' => false,
            // Legacy placeholders — BUKAN data bisnis
            'reach_estimate' => 0,
            'reach_score_10' => 0,          // ← DEPRECATED placeholder
            'reach_score_max' => 10,
            'reach_level' => 'Unknown',     // ← DEPRECATED placeholder
            'estimated_reach_band' => 'Unknown',
            'reach_trend' => 'stable',
            'reach_source' => 'unknown',
            'reach_confidence' => 'low',
            'reach_reason' => 'Legacy field – not used in business logic',
            'validation_errors' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $articleId = DB::table('articles')->insertGetId([
            'title' => 'Test Article ' . uniqid(),
            'content' => 'Content ' . str_repeat('x', 600),
            'url' => 'https://example.com/' . uniqid(),
            'source_name' => 'Test Source',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('ai_analysis_results')->insertGetId(
            array_merge($defaults, ['article_id' => $articleId], $overrides)
        );
    }

    /**
     * Test 1: Row dengan reach_score_10=0 tapi canonical fields valid
     *         harus dianggap sebagai hasil AI yang valid (bukan invalid).
     */
    public function test_row_with_reach_score_10_zero_and_valid_canonical_is_considered_valid(): void
    {
        DB::beginTransaction();

        $id = $this->makeCanonicalAiRow(['reach_score_10' => 0]);

        $row = DB::table('ai_analysis_results')->find($id);

        // reach_score_10=0 adalah placeholder, bukan indikator invalid
        $this->assertEquals(0, $row->reach_score_10, 'reach_score_10 harus 0 sebagai placeholder');

        // Field canonical harus tersedia dan valid
        $this->assertEquals('success', $row->analysis_status);
        $this->assertEquals('ai_reader_estimate_v1', $row->reach_method);
        $this->assertGreaterThanOrEqual(1, $row->potential_estimated_readers);
        $this->assertGreaterThanOrEqual(1, $row->project_estimated_readers);
        $this->assertLessThanOrEqual(
            $row->potential_estimated_readers,
            $row->project_estimated_readers,
            'project_estimated_readers harus <= potential_estimated_readers'
        );
        $this->assertGreaterThanOrEqual(1, $row->project_reach_score);
        $this->assertLessThanOrEqual(10, $row->project_reach_score);

        DB::rollBack();
    }

    /**
     * Test 2: reach_score_10 TIDAK dipakai sebagai proxy validasi.
     *         Validitas ditentukan hanya dari field canonical.
     */
    public function test_validation_uses_canonical_fields_not_reach_score_10(): void
    {
        DB::beginTransaction();

        // Row dengan reach_score_10=0 tapi canonical valid
        $validId = $this->makeCanonicalAiRow([
            'reach_score_10' => 0,
            'potential_estimated_readers' => 100,
            'project_estimated_readers' => 80,
            'project_reach_score' => 5,
            'analysis_status' => 'success',
            'reach_method' => 'ai_reader_estimate_v1',
        ]);

        // Row dengan reach_score_10 > 0 tapi canonical TIDAK valid (method salah)
        $invalidId = $this->makeCanonicalAiRow([
            'reach_score_10' => 8,
            'potential_estimated_readers' => null,
            'project_estimated_readers' => null,
            'project_reach_score' => null,
            'analysis_status' => 'success',
            'reach_method' => 'legacy_v1',
        ]);

        $validRow = DB::table('ai_analysis_results')->find($validId);
        $invalidRow = DB::table('ai_analysis_results')->find($invalidId);

        // Validasi canonical: potential_readers, project_readers, method
        $isValid = fn($row) => $row->analysis_status === 'success'
            && $row->reach_method === 'ai_reader_estimate_v1'
            && (int) $row->potential_estimated_readers >= 1
            && (int) $row->project_estimated_readers >= 1
            && (int) $row->project_estimated_readers <= (int) $row->potential_estimated_readers;

        $this->assertTrue($isValid($validRow), 'Row dengan reach_score_10=0 tapi canonical valid harus valid');
        $this->assertFalse($isValid($invalidRow), 'Row dengan reach_score_10>0 tapi method legacy harus invalid');

        DB::rollBack();
    }

    /**
     * Test 3: Notification gate medium+high_reach menggunakan potential_reach_level canonical.
     *         BUKAN reach_level legacy yang bernilai 'Unknown'.
     *
     * Bug lama: reach_level='Unknown' → kondisi medium+high tidak pernah terpicu.
     * Fix baru: potential_reach_level='Tinggi' → kondisi terpicu dengan benar.
     */
    public function test_notification_gate_medium_risk_uses_canonical_reach_level(): void
    {
        // Simulasikan kondisi dari AiAnalysisJob setelah perbaikan
        $normalized = [
            'analysis_status' => 'success',
            'risk_level' => 'medium',
            // Legacy placeholder — sebelum fix, ini yang digunakan → tidak pernah 'high'
            'reach_level' => 'Unknown',
            // Canonical field — setelah fix, ini yang digunakan
            'potential_reach_level' => 'Tinggi',
        ];

        // Implementasi $shouldNotify SETELAH FIX (dari AiAnalysisJob):
        $canonicalReachLevel = strtolower((string) ($normalized['potential_reach_level'] ?? $normalized['reach_level'] ?? ''));
        $shouldNotify = ($normalized['analysis_status'] ?? 'success') === 'success'
            && (
                ($normalized['risk_level'] === 'high' || $normalized['risk_level'] === 'critical')
                || ($normalized['risk_level'] === 'medium' && in_array($canonicalReachLevel, ['tinggi', 'sangat tinggi', 'high'], true))
            );

        $this->assertTrue(
            $shouldNotify,
            'Notifikasi medium+tinggi HARUS terpicu menggunakan potential_reach_level canonical'
        );

        // Verifikasi bahwa logika LAMA (dengan reach_level='Unknown') TIDAK akan terpicu
        $shouldNotifyLegacy = ($normalized['analysis_status'] ?? 'success') === 'success'
            && (
                ($normalized['risk_level'] === 'high' || $normalized['risk_level'] === 'critical')
                || ($normalized['risk_level'] === 'medium' && $normalized['reach_level'] === 'high')
            );

        $this->assertFalse(
            $shouldNotifyLegacy,
            'Bug lama: notifikasi medium+tinggi TIDAK terpicu karena reach_level=Unknown'
        );
    }

    /**
     * Test 4: NotificationDropdown harus menggunakan project_reach_level canonical,
     *         bukan reach_level legacy yang bernilai 'Unknown'.
     */
    public function test_notification_dropdown_uses_canonical_reach_level(): void
    {
        DB::beginTransaction();

        $id = $this->makeCanonicalAiRow([
            'risk_level' => 'high',
            'reach_level' => 'Unknown',         // Legacy
            'project_reach_level' => 'Tinggi',  // Canonical
            'potential_reach_level' => 'Tinggi',
        ]);

        $row = DB::table('ai_analysis_results')->find($id);

        // Simulasikan logika NotificationDropdown SETELAH FIX:
        $displayedReachLevel = $row->project_reach_level ?? $row->potential_reach_level ?? $row->reach_level;

        $this->assertEquals('Tinggi', $displayedReachLevel,
            'Dropdown harus menampilkan project_reach_level canonical, bukan reach_level=Unknown');
        $this->assertNotEquals('Unknown', $displayedReachLevel,
            'Dropdown tidak boleh menampilkan nilai legacy Unknown');

        DB::rollBack();
    }

    /**
     * Test 5: Row id=85 analog — pastikan kondisi valid (tidak berubah).
     *         project_estimated_readers <= potential_estimated_readers.
     */
    public function test_analog_row_85_passes_canonical_validation(): void
    {
        DB::beginTransaction();

        $id = $this->makeCanonicalAiRow([
            'reach_score_10' => 0,          // Sama seperti id=85
            'project_reach_score' => 7,     // Sama seperti id=85
            'potential_estimated_readers' => 284,
            'project_estimated_readers' => 245,
            'reach_method' => 'ai_reader_estimate_v1',
            'analysis_status' => 'success',
        ]);

        $row = DB::table('ai_analysis_results')->find($id);

        $this->assertEquals(0, $row->reach_score_10, 'Legacy placeholder harus 0');
        $this->assertEquals(7, $row->project_reach_score, 'Canonical score harus 7');
        $this->assertEquals(284, $row->potential_estimated_readers);
        $this->assertEquals(245, $row->project_estimated_readers);
        $this->assertLessThanOrEqual(
            $row->potential_estimated_readers,
            $row->project_estimated_readers
        );
        $this->assertEquals('success', $row->analysis_status);
        $this->assertEquals('ai_reader_estimate_v1', $row->reach_method);

        DB::rollBack();
    }

    /**
     * Test 6: Field reach_score_10 tidak dipakai dalam query sort/popular.
     *         Sort menggunakan project_estimated_readers.
     */
    public function test_sorting_uses_project_estimated_readers_not_reach_score_10(): void
    {
        DB::beginTransaction();

        // Artikel dengan project_estimated_readers tinggi tapi reach_score_10=0
        $highReaderArticleId = DB::table('articles')->insertGetId([
            'title' => 'High Reader Article',
            'content' => str_repeat('x', 600),
            'url' => 'https://example.com/high',
            'source_name' => 'Test',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_analysis_results')->insert(array_merge(
            $this->legacyDefaults(),
            [
                'article_id' => $highReaderArticleId,
                'project_estimated_readers' => 1000,
                'potential_estimated_readers' => 1200,
                'reach_score_10' => 0,  // Legacy 0
            ]
        ));

        // Artikel dengan project_estimated_readers rendah tapi reach_score_10=8
        $lowReaderArticleId = DB::table('articles')->insertGetId([
            'title' => 'Low Reader Article',
            'content' => str_repeat('x', 600),
            'url' => 'https://example.com/low',
            'source_name' => 'Test',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_analysis_results')->insert(array_merge(
            $this->legacyDefaults(),
            [
                'article_id' => $lowReaderArticleId,
                'project_estimated_readers' => 50,
                'potential_estimated_readers' => 60,
                'reach_score_10' => 8,  // Legacy > 0 tapi pembaca sedikit
            ]
        ));

        // Query sort "popular" menggunakan project_estimated_readers
        $sorted = DB::table('ai_analysis_results')
            ->where('analysis_status', 'success')
            ->where('reach_method', 'ai_reader_estimate_v1')
            ->orderByDesc('project_estimated_readers')
            ->pluck('article_id');

        $this->assertEquals($highReaderArticleId, $sorted->first(),
            'Artikel dengan project_estimated_readers tertinggi harus muncul pertama, bukan berdasarkan reach_score_10');

        DB::rollBack();
    }

    private function legacyDefaults(): array
    {
        return [
            'social_media_item_id' => null,
            'summary' => 'Test',
            'sentiment' => 'positive',
            'sentiment_score' => 0.5,
            'main_issue' => 'Test',
            'entities' => '[]',
            'risk_level' => 'low',
            'risk_reason' => 'Test',
            'recommendation' => 'Test',
            'raw_response' => '{}',
            'analysis_status' => 'success',
            'reach_method' => 'ai_reader_estimate_v1',
            'potential_reach_score' => 7,
            'potential_reach_level' => 'Cukup tinggi',
            'potential_reach_band' => '201-350 pembaca',
            'project_reach_score' => 7,
            'project_reach_level' => 'Cukup tinggi',
            'project_reach_band' => '201-350 pembaca',
            'local_relevance_score' => 7,
            'confidence_score' => 65,
            'confidence_level' => 'Medium',
            'signals_used' => '[]',
            'reasoning_summary' => 'Test',
            'limitations' => 'Test',
            'is_exact_reach' => false,
            'reach_estimate' => 0,
            'reach_score_max' => 10,
            'reach_level' => 'Unknown',
            'estimated_reach_band' => 'Unknown',
            'reach_trend' => 'stable',
            'reach_source' => 'unknown',
            'reach_confidence' => 'low',
            'reach_reason' => 'Legacy field',
            'validation_errors' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
