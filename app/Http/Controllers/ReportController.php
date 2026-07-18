<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\AiAnalysisResult;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReportController extends Controller
{
    private const SOCIAL_SOURCE_NAMES = [
        'facebook',
        'instagram',
        'tiktok',
        'twitter',
        'twitter/x',
        'x.com',
        'threads',
        'youtube',
    ];

    private function accessibleProjectQuery()
    {
        $user = auth()->user();
        abort_unless($user, 403, 'Autentikasi diperlukan.');

        return Project::accessibleBy($user);
    }

    private function resolveProjectOrFail($projectId): Project
    {
        $project = (clone $this->accessibleProjectQuery())->find($projectId);
        abort_unless($project, 403, 'Anda tidak memiliki akses ke project ini.');

        return $project;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  PDF  — Infografis
    // ─────────────────────────────────────────────────────────────────────
    public function downloadPdf(Request $request)
    {
        $projectId   = $request->query('project_id');
        $startDate   = $request->query('start_date');
        $endDate     = $request->query('end_date');

        $project     = $this->resolveProjectOrFail($projectId);
        $projectName = $project->name;
        $articles    = $this->getArticles($projectId, $startDate, $endDate);
        $socialMediaItems = $this->getSocialMediaItems($projectId, $startDate, $endDate);

        $total    = $articles->count();
        $positive = $articles->where('sentiment', 'positive')->count();
        $neutral  = $articles->where('sentiment', 'neutral')->count();
        $negative = $articles->where('sentiment', 'negative')->count();

        $sourceCounts = $articles->groupBy('source_name')
            ->map(fn($g) => $g->count())
            ->sortDesc()
            ->toArray();

        $pos_pct = $total > 0 ? round(($positive / $total) * 100) : 0;
        $neu_pct = $total > 0 ? round(($neutral / $total) * 100) : 0;
        $neg_pct = $total > 0 ? round(($negative / $total) * 100) : 0;
        $reputation_score = $total > 0 ? round((($positive + ($neutral * 0.5)) / $total) * 100) : 0;

        // Prioritaskan wawasan buatan AI (jika sudah di-generate)
        if (!empty($project->ai_insight_summary) && !empty($project->ai_insight_recommendations)) {
            $wawasanSummary = $project->ai_insight_summary;
            $wawasanRecs = $project->ai_insight_recommendations;
        } else {
            $wawasanSummary = "Berdasarkan analisis terhadap **{$total}** penyebutan, proyek **" . strtoupper($projectName) . "** memiliki reputasi media yang ";
            if ($reputation_score >= 75) {
                $wawasanSummary .= "sangat kuat (**{$reputation_score}/100**). Sentimen positif mendominasi perbincangan sebesar **{$pos_pct}%**, yang mencerminkan respons masyarakat yang sangat baik.";
            } elseif ($reputation_score >= 50) {
                $wawasanSummary .= "cukup stabil (**{$reputation_score}/100**). Sebagian besar perbincangan bersifat netral (**{$neu_pct}%**), menunjukkan liputan berita yang bersifat informatif tanpa opini yang kuat.";
            } else {
                $wawasanSummary .= "kurang kondusif (**{$reputation_score}/100**). Volume sentimen negatif mencapai **{$neg_pct}%**, mengindikasikan adanya isu sensitif atau kritik yang perlu segera direspon.";
            }

            $wawasanRecs = [];
            if ($neg_pct >= 20) {
                $wawasanRecs[] = "Lakukan klarifikasi segera melalui siaran pers terkait isu negatif utama yang berkembang.";
                $wawasanRecs[] = "Tingkatkan frekuensi publikasi berita positif untuk menyeimbangkan sentimen di media online.";
            } else {
                $wawasanRecs[] = "Pertahankan kampanye komunikasi yang sedang berjalan dan perluas jangkauan ke media nasional terkemuka.";
                $wawasanRecs[] = "Optimalkan kata kunci pendukung untuk menangkap peluang publikasi yang lebih luas.";
            }
            $wawasanRecs[] = "Gunakan influencer lokal untuk memperkuat pesan positif di kanal media sosial utama.";
        }

        // 1. Konteks Percakapan (Topik Utama / Word Cloud)
        $stopWords = ['dan', 'di', 'ke', 'dari', 'yang', 'untuk', 'dengan', 'ini', 'itu', 'pada', 'dalam', 'adalah', 'akan', 'juga', 'sudah', 'ada', 'bisa', 'atau', 'tidak', 'lebih', 'saat', 'oleh', 'para', 'telah', 'agar', 'atas', 'jika', 'karena', 'maka', 'namun', 'pun', 'serta', 'tentang', 'setelah', 'antara', 'hingga', 'ia', 'kami', 'kita', 'mereka', 'anda', 'bagi', 'dua', 'tiga', 'lain', 'hal', 'tahun', 'baru', 'terkait', 'pihak', 'sebuah', 'satu', 'tersebut', 'the', 'a', 'an', 'is', 'in', 'of', 'and', 'to', 'for', 'masa', 'jalan', 'jadi', 'pemerintah', 'gubernur'];
        $titles = $articles->pluck('title');
        $wordFreq = [];
        foreach ($titles as $title) {
            $cleanTitle = strtolower(preg_replace('/[^a-zA-Z0-9\s]/u', ' ', html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8')));
            $words = array_filter(explode(' ', $cleanTitle), function($w) use ($stopWords) {
                return strlen($w) > 3 && !in_array($w, $stopWords);
            });
            foreach ($words as $word) {
                $wordFreq[$word] = ($wordFreq[$word] ?? 0) + 1;
            }
        }
        arsort($wordFreq);
        $topWords = array_slice($wordFreq, 0, 30, true);

        // 2. Per Kata Kunci
        $primaryKeywords = $project->topics ?? [$project->name];
        $keywordsTable = [];
        foreach ($primaryKeywords as $kw) {
            $count = $articles->filter(function($art) use ($kw) {
                return stripos($art->title, $kw) !== false || stripos($art->content, $kw) !== false;
            })->count();
            if ($count > 0) {
                $keywordsTable[$kw] = $count;
            }
        }
        arsort($keywordsTable);

        // 3. Berita Terpopuler (Top Reach)
        $topReachArticles = $articles->filter(function($art) {
            return $art->aiAnalysisResult && $art->aiAnalysisResult->hasCompleteOfficialAiResult();
        })->sortByDesc(function($art) {
            $analysis = $art->aiAnalysisResult;
            return (int) ($analysis->project_estimated_readers ?? 0);
        })->take(10);

        // 4. Sumber Medsos
        $socialsList = ['Twitter', 'Twitter/X', 'x.com', 'Instagram', 'Youtube', 'TikTok', 'Facebook', 'Threads'];
        $socialArticles = $socialMediaItems->filter(function($item) use ($socialsList) {
            return in_array($item->platform, $socialsList, true);
        });
        $socialCounts = $socialArticles->groupBy('platform')->map(fn($g) => $g->count())->sortDesc()->toArray();

        $toggles = json_decode($request->query('toggles', '{}'), true);

        return view('reports.pdf', compact(
            'projectName', 'articles',
            'total', 'positive', 'neutral', 'negative',
            'sourceCounts', 'startDate', 'endDate', 'toggles',
            'wawasanSummary', 'wawasanRecs',
            'topWords', 'keywordsTable', 'topReachArticles', 'socialCounts', 'socialMediaItems'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Excel  — Multi-sheet berformat rapi
    // ─────────────────────────────────────────────────────────────────────
    public function downloadExcel(Request $request)
    {
        $projectId   = $request->query('project_id');
        $startDate   = $request->query('start_date');
        $endDate     = $request->query('end_date');

        $project  = $this->resolveProjectOrFail($projectId);
        $articles = $this->getArticles($projectId, $startDate, $endDate);

        $spreadsheet = new Spreadsheet();

        // Sheet 1 — Ringkasan
        $s1 = $spreadsheet->getActiveSheet();
        $s1->setTitle('Ringkasan');
        $this->buildSummarySheet($s1, $project, $articles, $startDate, $endDate);

        // Sheet 2 — Data Penyebutan
        $s2 = $spreadsheet->createSheet();
        $s2->setTitle('Data Penyebutan');
        $this->buildArticlesSheet($s2, $articles);

        // Sheet 3 — Analisis Sentimen
        $s3 = $spreadsheet->createSheet();
        $s3->setTitle('Analisis Sentimen');
        $this->buildSentimentSheet($s3, $articles);

        $spreadsheet->setActiveSheetIndex(0);

        $tmpFile  = tempnam(sys_get_temp_dir(), 'laporan_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpFile);

        $filename = 'laporan-' . \Str::slug($project->name) . '-' . now()->format('Ymd_His') . '.xlsx';

        return response()->download($tmpFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────
    private function getArticles($projectId, $startDate, $endDate)
    {
        $project = $this->resolveProjectOrFail($projectId);
        $q = $project->articles()
            ->withCompleteOfficialAiResult()
            ->with(['aiAnalysisResult' => function ($query) {
                $query->completeOfficialAiResult();
            }])
            ->where(function ($query) {
                $query->whereNull('source_name')
                    ->orWhereRaw(
                        'LOWER(TRIM(source_name)) NOT IN (' . implode(',', array_fill(0, count(self::SOCIAL_SOURCE_NAMES), '?')) . ')',
                        self::SOCIAL_SOURCE_NAMES
                    );
            });
        if ($startDate) $q->whereDate('published_at', '>=', $startDate);
        if ($endDate)   $q->whereDate('published_at', '<=', $endDate);
        return $q->orderByDesc('published_at')->get();
    }

    private function getSocialMediaItems($projectId, $startDate, $endDate)
    {
        $project = $this->resolveProjectOrFail($projectId);
        $q = $project->socialMediaItems()
            ->with(['aiAnalysisResult' => function ($query) {
                $query->completeOfficialAiResult();
            }]);

        if ($startDate) {
            $q->whereDate('posted_at', '>=', $startDate);
        }

        if ($endDate) {
            $q->whereDate('posted_at', '<=', $endDate);
        }

        return $q->get()->sortByDesc(function ($item) {
            $sentiment = strtolower((string) optional($item->aiAnalysisResult)->sentiment);

            return match ($sentiment) {
                'negative' => 3,
                'neutral' => 2,
                'positive' => 1,
                default => 0,
            };
        })->sortByDesc(function ($item) {
            return optional($item->posted_at)?->timestamp ?? 0;
        })->values();
    }

    private function buildSummarySheet($sheet, $project, $articles, $startDate, $endDate): void
    {
        $total    = $articles->count();
        $positive = $articles->where('sentiment', 'positive')->count();
        $neutral  = $articles->where('sentiment', 'neutral')->count();
        $negative = $articles->where('sentiment', 'negative')->count();

        // Brand header
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'ARUSBAWAH MEDIA INTELLIGENCE');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1fa387']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(38);

        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A2', 'Laporan Monitoring Media — Proyek: ' . strtoupper($project->name));
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '178a70']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(24);

        $period = $startDate && $endDate ? "{$startDate} s/d {$endDate}" : 'Semua Data';
        $sheet->mergeCells('A3:F3');
        $sheet->setCellValue('A3', "Periode: {$period}   |   Dibuat: " . now()->format('d/m/Y H:i'));
        $sheet->getStyle('A3')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748b']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(18);

        // Stats
        $sheet->setCellValue('A5', 'RINGKASAN STATISTIK');
        $sheet->getStyle('A5')->applyFromArray(['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1fa387']]]);

        $stats = [
            ['Total Penyebutan', $total,    '1fa387'],
            ['Sentimen Positif', $positive, '22c55e'],
            ['Sentimen Netral',  $neutral,  '64748b'],
            ['Sentimen Negatif', $negative, 'ef4444'],
        ];
        $row = 6;
        foreach ($stats as [$label, $val, $color]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $val);
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                'font'    => ['bold' => true, 'color' => ['rgb' => 'ffffff']],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'ffffff']]],
            ]);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight(22);
            $row++;
        }

        // Per-source breakdown
        $row += 2;
        $sheet->setCellValue("A{$row}", 'PENYEBUTAN PER SUMBER');
        $sheet->getStyle("A{$row}")->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1fa387']]]);
        $row++;

        foreach (['Sumber', 'Total', 'Positif', 'Netral', 'Negatif'] as $ci => $hdr) {
            $col = chr(65 + $ci);
            $sheet->setCellValue("{$col}{$row}", $hdr);
            $sheet->getStyle("{$col}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'ffffff']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            ]);
        }
        $row++;

        foreach ($articles->groupBy('source_name') as $src => $items) {
            $sheet->setCellValue("A{$row}", $src);
            $sheet->setCellValue("B{$row}", $items->count());
            $sheet->setCellValue("C{$row}", $items->where('sentiment','positive')->count());
            $sheet->setCellValue("D{$row}", $items->where('sentiment','neutral')->count());
            $sheet->setCellValue("E{$row}", $items->where('sentiment','negative')->count());
            $bg = $row % 2 === 0 ? 'f8fafc' : 'ffffff';
            $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            ]);
            $row++;
        }

        foreach (['A'=>30,'B'=>14,'C'=>14,'D'=>14,'E'=>14,'F'=>14] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }
    }

    private function buildArticlesSheet($sheet, $articles): void
    {
        $headers = ['#','Judul','Sumber','Kategori','Sentimen','Skor','URL','Tanggal','Potensi','Lokal','Relevansi','Confidence','Method','Calced At','Keterangan'];
        foreach ($headers as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}1", $h);
            $sheet->getStyle("{$col}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'ffffff']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1fa387']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        $row = 2;
        foreach ($articles as $i => $art) {
            $sentBg = match($art->sentiment) {
                'positive' => 'dcfce7', 'negative' => 'fee2e2', default => 'f1f5f9'
            };
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $art->title);
            $sheet->setCellValue("C{$row}", $art->source_name);
            $sheet->setCellValue("D{$row}", $art->category);
            $sheet->setCellValue("E{$row}", ucfirst($art->sentiment));
            $sheet->setCellValue("F{$row}", number_format($art->sentiment_score, 2));
            $sheet->setCellValue("G{$row}", $art->url ?? '-');
            $sheet->setCellValue("H{$row}", $art->published_at
                ? \Carbon\Carbon::parse($art->published_at)->format('d/m/Y') : '-');
            $reach = $this->formatReachSummary($art->aiAnalysisResult);
            $sheet->setCellValue("I{$row}", $reach['potential']);
            $sheet->setCellValue("J{$row}", $reach['adjusted']);
            $sheet->setCellValue("K{$row}", $reach['relevance']);
            $sheet->setCellValue("L{$row}", $reach['confidence']);
            $sheet->setCellValue("M{$row}", $reach['method']);
            $sheet->setCellValue("N{$row}", $reach['calculated_at']);
            $sheet->setCellValue("O{$row}", $reach['note']);

            $sheet->getStyle("E{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $sentBg]],
            ]);
            $bg = $row % 2 === 0 ? 'f8fafc' : 'ffffff';
            $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            ]);
            $row++;
        }

        foreach (['A'=>5,'B'=>50,'C'=>15,'D'=>18,'E'=>14,'F'=>10,'G'=>40,'H'=>14,'I'=>16,'J'=>16,'K'=>18,'L'=>16,'M'=>16,'N'=>18,'O'=>40] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }
    }

    private function buildSentimentSheet($sheet, $articles): void
    {
        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', 'ANALISIS SENTIMEN');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1fa387']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach (['Sentimen','Jumlah','Persentase','Skor Rata-rata'] as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}3", $h);
            $sheet->getStyle("{$col}3")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'ffffff']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            ]);
        }

        $total = max($articles->count(), 1);
        $rows  = [
            ['positive','Positif','dcfce7'],
            ['neutral', 'Netral', 'f1f5f9'],
            ['negative','Negatif','fee2e2'],
        ];
        $r = 4;
        foreach ($rows as [$key, $label, $bg]) {
            $g   = $articles->where('sentiment', $key);
            $cnt = $g->count();
            $sheet->setCellValue("A{$r}", $label);
            $sheet->setCellValue("B{$r}", $cnt);
            $sheet->setCellValue("C{$r}", round($cnt / $total * 100, 1) . '%');
            $sheet->setCellValue("D{$r}", $cnt > 0 ? number_format($g->avg('sentiment_score'), 2) : '0.00');
            $sheet->getStyle("A{$r}:D{$r}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            ]);
            $r++;
        }

        foreach (['A'=>20,'B'=>15,'C'=>16,'D'=>18] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }
    }

    private function formatReachSummary($analysis): array
    {
        if (! $analysis instanceof AiAnalysisResult) {
            return [
                'potential' => 'Belum dinilai AI',
                'project' => 'Belum dinilai AI',
                'adjusted' => 'Belum dinilai AI',
                'relevance' => 'Belum dinilai AI',
                'confidence' => 'Belum dinilai AI',
                'method' => '-',
                'calculated_at' => '-',
                'note' => 'Belum dinilai AI',
            ];
        }

        if (! $analysis->hasCompleteOfficialAiResult()) {
            return [
                'potential' => 'Belum dinilai AI',
                'project' => 'Belum dinilai AI',
                'adjusted' => 'Belum dinilai AI',
                'relevance' => 'Belum dinilai AI',
                'confidence' => 'Belum dinilai AI',
                'method' => '-',
                'calculated_at' => '-',
                'note' => 'Belum dinilai AI',
            ];
        }

        $potentialLevel = $this->normalizeReachLevelLabel($analysis->potential_reach_level);
        $confidenceLevel = $this->normalizeConfidenceLevelLabel($analysis->confidence_level);
        $potential = sprintf('%d/10 — %s — %s', (int) $analysis->potential_reach_score, $potentialLevel, $analysis->potential_reach_band ?: '-');
        $projectReaders = $analysis->officialArticleEstimatedReaders();
        $project = $projectReaders !== null
            ? sprintf('%d/10 — %s — %s', (int) $analysis->project_reach_score, $this->normalizeReachLevelLabel($analysis->project_reach_level), $analysis->project_reach_band ?: '-')
            : 'Belum tersedia';
        $relevance = sprintf('%d/100', (int) $analysis->local_relevance_score);
        $confidence = sprintf('%d/100 — %s', (int) $analysis->confidence_score, $confidenceLevel);

        return [
            'potential' => $potential,
            'project' => $project,
            'adjusted' => $project,
            'relevance' => $relevance,
            'confidence' => $confidence,
            'method' => (string) $analysis->reach_method,
            'calculated_at' => optional($analysis->updated_at)->format('d/m/Y H:i') ?: '-',
            'note' => 'Estimasi AI, bukan reach exact',
        ];
    }

    private function normalizeReachLevelLabel(?string $value): string
    {
        return match ($value) {
            'Low' => 'Low',
            'Local' => 'Local',
            'Medium' => 'Medium',
            'High' => 'High',
            'Viral' => 'Viral',
            default => 'Belum dinilai AI',
        };
    }

    private function normalizeConfidenceLevelLabel(?string $value): string
    {
        return match ($value) {
            'Low' => 'Low',
            'Medium' => 'Medium',
            'High' => 'High',
            default => 'Belum dinilai AI',
        };
    }
}
