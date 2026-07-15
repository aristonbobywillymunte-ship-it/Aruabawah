<?php

namespace App\Exports;

use App\Models\Article;
use App\Models\Project;
use App\Models\AiAnalysisResult;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ArticlesExport
{
    protected $projectId;
    protected $projectName;
    protected $startDate;
    protected $endDate;

    public function __construct($projectId, $projectName, $startDate = null, $endDate = null)
    {
        $this->projectId   = $this->resolveAccessibleProjectId($projectId);
        $this->projectName = $projectName;
        $this->startDate   = $startDate;
        $this->endDate     = $endDate;
    }

    protected function resolveAccessibleProjectId($projectId)
    {
        $user = auth()->user();
        abort_unless($user, 403, 'Autentikasi diperlukan.');

        $project = Project::accessibleBy($user)->find($projectId);
        abort_unless($project, 403, 'Anda tidak memiliki akses ke project ini.');

        return $project->id;
    }

    public function generate(): string
    {
        $spreadsheet = new Spreadsheet();

        // ── Sheet 1: Ringkasan ────────────────────────────────────────────
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Ringkasan');
        $this->buildSummarySheet($sheet1);

        // ── Sheet 2: Data Penyebutan ──────────────────────────────────────
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Data Penyebutan');
        $this->buildArticlesSheet($sheet2);

        // ── Sheet 3: Analisis Sentimen ────────────────────────────────────
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Analisis Sentimen');
        $this->buildSentimentSheet($sheet3);

        $spreadsheet->setActiveSheetIndex(0);

        // Save to temp file
        $filename = tempnam(sys_get_temp_dir(), 'laporan_') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);
        $writer->save($filename);

        return $filename;
    }

    private function buildSummarySheet($sheet): void
    {
        $articles = $this->getArticles();
        $total    = $articles->count();
        $positive = $articles->where('sentiment', 'positive')->count();
        $neutral  = $articles->where('sentiment', 'neutral')->count();
        $negative = $articles->where('sentiment', 'negative')->count();

        // Header brand
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'ARUSBAWAH MEDIA INTELLIGENCE');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1fa387']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(36);

        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A2', 'Laporan Monitoring Media — Proyek: ' . strtoupper($this->projectName));
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '178a70']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(24);

        $sheet->mergeCells('A3:F3');
        $period = ($this->startDate && $this->endDate)
            ? "Periode: {$this->startDate} s/d {$this->endDate}"
            : 'Periode: Semua Data';
        $sheet->setCellValue('A3', $period . '   |   Dibuat: ' . now()->format('d/m/Y H:i'));
        $sheet->getStyle('A3')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748b']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Stats table
        $sheet->setCellValue('A5', 'RINGKASAN STATISTIK');
        $sheet->getStyle('A5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
        ]);

        $stats = [
            ['Total Penyebutan', $total, '1fa387'],
            ['Sentimen Positif', $positive, '22c55e'],
            ['Sentimen Netral',  $neutral,  '64748b'],
            ['Sentimen Negatif', $negative, 'ef4444'],
        ];

        $row = 6;
        foreach ($stats as [$label, $value, $color]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                'font'    => ['bold' => true, 'color' => ['rgb' => 'ffffff']],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'ffffff']]],
            ]);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight(22);
            $row++;
        }

        // Source breakdown
        $row += 2;
        $sheet->setCellValue("A{$row}", 'PENYEBUTAN PER SUMBER');
        $sheet->getStyle("A{$row}")->applyFromArray(['font' => ['bold' => true, 'size' => 11]]);
        $row++;

        $sources = $articles->groupBy('source_name');
        $headers = ['Sumber', 'Jumlah', 'Positif', 'Netral', 'Negatif'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue("{$col}{$row}", $h);
            $sheet->getStyle("{$col}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'ffffff']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            ]);
            $col++;
        }
        $row++;

        foreach ($sources as $source => $items) {
            $sheet->setCellValue("A{$row}", $source);
            $sheet->setCellValue("B{$row}", $items->count());
            $sheet->setCellValue("C{$row}", $items->where('sentiment', 'positive')->count());
            $sheet->setCellValue("D{$row}", $items->where('sentiment', 'neutral')->count());
            $sheet->setCellValue("E{$row}", $items->where('sentiment', 'negative')->count());
            $bg = $row % 2 === 0 ? 'f8fafc' : 'ffffff';
            $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            ]);
            $row++;
        }

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
    }

    private function buildArticlesSheet($sheet): void
    {
        $articles = $this->getArticles();

        // Header
        $headers = ['#', 'Judul', 'Sumber', 'Kategori', 'Sentimen', 'Skor', 'URL', 'Tanggal', 'Potensi', 'Lokal', 'Relevansi', 'Confidence', 'Method', 'Calced At', 'Keterangan'];
        $cols    = range('A', 'O');
        foreach ($headers as $i => $h) {
            $col = $cols[$i];
            $sheet->setCellValue("{$col}1", $h);
            $sheet->getStyle("{$col}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'ffffff']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1fa387']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }
        $sheet->getRowDimension(1)->setRowHeight(22);

        $row = 2;
        foreach ($articles as $i => $art) {
            $sentimentColor = match ($art->sentiment) {
                'positive' => 'dcfce7',
                'negative' => 'fee2e2',
                default    => 'f1f5f9',
            };
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $art->title);
            $sheet->setCellValue("C{$row}", $art->source_name);
            $sheet->setCellValue("D{$row}", $art->category);
            $sheet->setCellValue("E{$row}", ucfirst($art->sentiment));
            $sheet->setCellValue("F{$row}", number_format($art->sentiment_score, 2));
            $sheet->setCellValue("G{$row}", $art->url ?? '-');
            $sheet->setCellValue("H{$row}", $art->published_at ? \Carbon\Carbon::parse($art->published_at)->format('d/m/Y') : '-');
            $reach = $this->formatReachSummary($art->aiAnalysisResult);
            $sheet->setCellValue("I{$row}", $reach['potential']);
            $sheet->setCellValue("J{$row}", $reach['project']);
            $sheet->setCellValue("K{$row}", $reach['relevance']);
            $sheet->setCellValue("L{$row}", $reach['confidence']);
            $sheet->setCellValue("M{$row}", $reach['method']);
            $sheet->setCellValue("N{$row}", $reach['calculated_at']);
            $sheet->setCellValue("O{$row}", $reach['note']);

            $sheet->getStyle("E{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $sentimentColor]],
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

    private function buildSentimentSheet($sheet): void
    {
        $articles = $this->getArticles();

        $sheet->setCellValue('A1', 'ANALISIS SENTIMEN');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1fa387']],
        ]);

        $headers = ['Sentimen', 'Jumlah', 'Persentase', 'Skor Rata-rata'];
        foreach (range('A', 'D') as $i => $col) {
            $sheet->setCellValue("{$col}3", $headers[$i]);
            $sheet->getStyle("{$col}3")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'ffffff']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            ]);
        }

        $total = $articles->count() ?: 1;
        $sentiments = [
            ['positive', 'Positif',  'dcfce7'],
            ['neutral',  'Netral',   'f1f5f9'],
            ['negative', 'Negatif',  'fee2e2'],
        ];

        $row = 4;
        foreach ($sentiments as [$key, $label, $bg]) {
            $group = $articles->where('sentiment', $key);
            $count = $group->count();
            $pct   = round($count / $total * 100, 1) . '%';
            $avg   = $count > 0 ? number_format($group->avg('sentiment_score'), 2) : '0.00';

            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $count);
            $sheet->setCellValue("C{$row}", $pct);
            $sheet->setCellValue("D{$row}", $avg);
            $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            ]);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(18);
    }

    private function getArticles()
    {
        $project = Project::findOrFail($this->projectId);
        $query = $project->articles()
            ->withCompleteOfficialAiResult()
            ->with(['aiAnalysisResult' => function ($builder) {
                $builder->completeOfficialAiResult();
            }]);
        if ($this->startDate) {
            $query->whereDate('published_at', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->whereDate('published_at', '<=', $this->endDate);
        }
        return $query->orderByDesc('published_at')->get();
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

        $projectReaders = $analysis->officialArticleEstimatedReaders();

        return [
            'potential' => sprintf('%d pembaca — Skor %d/10 (%s) — %s', (int) $analysis->potential_estimated_readers, (int) $analysis->potential_reach_score, $this->normalizeReachLevelLabel($analysis->potential_reach_level), $analysis->potential_reach_band ?: '-'),
            'project' => $projectReaders !== null
                ? sprintf('%d pembaca — Skor %d/10 (%s) — %s', $projectReaders, (int) $analysis->project_reach_score, $this->normalizeReachLevelLabel($analysis->project_reach_level), $analysis->project_reach_band ?: '-')
                : 'Belum tersedia',
            'relevance' => sprintf('%d/100', (int) $analysis->local_relevance_score),
            'confidence' => sprintf('%d/100 — %s', (int) $analysis->confidence_score, $this->normalizeConfidenceLevelLabel($analysis->confidence_level)),
            'method' => (string) $analysis->reach_method,
            'calculated_at' => optional($analysis->updated_at)->format('d/m/Y H:i') ?: '-',
            'note' => 'Estimasi AI, bukan reach exact',
        ];
    }

    private function normalizeReachLevelLabel(?string $value): string
    {
        return match ($value) {
            'Sangat rendah', 'Rendah', 'Sedang', 'Cukup tinggi', 'Tinggi', 'Sangat tinggi', 'Luar biasa/nasional' => $value,
            default => 'Belum dinilai AI',
        };
    }

    private function normalizeConfidenceLevelLabel(?string $value): string
    {
        return match ($value) {
            'Low', 'Medium', 'High' => $value,
            default => 'Belum dinilai AI',
        };
    }
}
