<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size:10px; color:#1e293b; background:#fff; }

  /* ── Page ── */
  .page { padding:28px 32px; }

  /* ── Header ── */
  .header { display:table; width:100%; margin-bottom:18px; }
  .header-left  { display:table-cell; vertical-align:middle; width:60%; }
  .header-right { display:table-cell; vertical-align:middle; width:40%; text-align:right; }
  .brand-name  { font-size:20px; font-weight:900; color:#1e293b; letter-spacing:2px; }
  .brand-sub   { font-size:7px; color:#94a3b8; letter-spacing:3px; text-transform:uppercase; margin-top:1px; }
  .report-title{ font-size:15px; font-weight:700; color:#1e293b; }
  .report-meta { font-size:8px; color:#64748b; margin-top:3px; }
  .divider { border:none; border-top:2.5px solid #1fa387; margin-bottom:16px; }

  /* ── Section title ── */
  .section-title {
    font-size:8.5px; font-weight:700; color:#1fa387;
    letter-spacing:2px; text-transform:uppercase;
    border-left:3px solid #1fa387; padding-left:7px;
    margin-bottom:10px; margin-top:14px;
  }

  /* ── Stat cards row ── */
  .stats-row { display:table; width:100%; border-collapse:separate; border-spacing:6px 0; margin-bottom:4px; }
  .stat-card {
    display:table-cell; width:25%;
    background:#f8fafc; border:1px solid #e2e8f0;
    border-radius:8px; padding:10px 8px; text-align:center;
    vertical-align:middle;
  }
  .stat-card.green  { background:#e6f6f2; border-color:#1fa387; }
  .stat-card.pos    { background:#f0fdf4; border-color:#22c55e; }
  .stat-card.neg    { background:#fef2f2; border-color:#ef4444; }
  .stat-card.neu    { background:#f8fafc; border-color:#94a3b8; }
  .stat-number { font-size:22px; font-weight:900; color:#1e293b; line-height:1; }
  .stat-number.teal { color:#1fa387; }
  .stat-number.pos  { color:#16a34a; }
  .stat-number.neg  { color:#dc2626; }
  .stat-number.neu  { color:#475569; }
  .stat-label  { font-size:7.5px; color:#64748b; margin-top:3px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; }

  /* ── Bar chart ── */
  .chart-wrap { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; margin-bottom:8px; }
  .chart-title { font-size:8px; font-weight:700; color:#334155; margin-bottom:8px; }
  .bar-row { display:table; width:100%; margin-bottom:5px; }
  .bar-label { display:table-cell; width:15%; font-size:8px; color:#475569; vertical-align:middle; }
  .bar-track { display:table-cell; vertical-align:middle; padding-left:4px; }
  .bar-bg { background:#e2e8f0; border-radius:4px; height:12px; width:100%; }
  .bar-fill { height:12px; border-radius:4px; }
  .bar-val { display:table-cell; width:12%; text-align:right; font-size:8px; font-weight:700; vertical-align:middle; padding-left:4px; }

  /* ── Donut chart (SVG) ── */
  .donut-wrap { display:table; width:100%; margin-bottom:8px; }
  .donut-chart { display:table-cell; width:44%; text-align:center; vertical-align:middle; }
  .donut-legend { display:table-cell; vertical-align:middle; padding-left:14px; }
  .legend-item { margin-bottom:6px; display:table; width:100%; }
  .legend-dot { display:table-cell; width:10px; vertical-align:middle; }
  .legend-dot span { display:inline-block; width:8px; height:8px; border-radius:50%; }
  .legend-text { display:table-cell; font-size:8px; color:#475569; vertical-align:middle; padding-left:4px; }
  .legend-val  { display:table-cell; text-align:right; font-size:8.5px; font-weight:700; color:#1e293b; vertical-align:middle; }

  /* ── Table ── */
  table.data-table { width:100%; border-collapse:collapse; margin-top:6px; font-size:8.5px; }
  .data-table thead th {
    background:#1fa387; color:#fff; padding:6px 8px;
    text-align:left; font-weight:700; font-size:7.5px;
    letter-spacing:0.5px; text-transform:uppercase;
  }
  .data-table tbody tr:nth-child(even) td { background:#f8fafc; }
  .data-table tbody td { padding:5px 8px; border-bottom:1px solid #f1f5f9; color:#334155; }
  .badge {
    display:inline-block; padding:2px 6px; border-radius:10px;
    font-size:7px; font-weight:700; text-transform:uppercase;
  }
  .badge-pos { background:#dcfce7; color:#15803d; }
  .badge-neg { background:#fee2e2; color:#b91c1c; }
  .badge-neu { background:#f1f5f9; color:#475569; }

  /* ── Source pills ── */
  .source-grid { display:table; width:100%; border-collapse:separate; border-spacing:5px; }
  .source-cell { display:table-cell; }
  .source-pill {
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;
    padding:8px 6px; text-align:center;
  }
  .source-pill-num { font-size:14px; font-weight:900; color:#1fa387; }
  .source-pill-name{ font-size:7px; color:#64748b; margin-top:1px; }

  /* ── Footer ── */
  .footer {
    margin-top:20px; border-top:1px solid #e2e8f0;
    padding-top:8px; display:table; width:100%;
    font-size:7px; color:#94a3b8;
  }
  .footer-left  { display:table-cell; }
  .footer-right { display:table-cell; text-align:right; }

  /* ── Watermark band ── */
  .teal-band {
    background:#1fa387; color:#fff; border-radius:6px;
    padding:6px 12px; margin-bottom:14px;
    font-size:8px; font-weight:700; letter-spacing:1px;
    text-transform:uppercase; text-align:center;
  }
</style>
</head>
<body>
<div class="page">

  {{-- ══ HEADER ══ --}}
  <div class="header">
    <div class="header-left">
      <table style="border: none; border-collapse: collapse; margin: 0; padding: 0;">
        <tr>
          <td style="border: none; padding: 0; padding-right: 8px; vertical-align: middle;">
            <!-- Brand Logo Arusbawah SVG -->
            <svg width="24" height="24" viewBox="0 0 42 42" fill="none" xmlns="http://www.w3.org/2000/svg">
              <polygon points="21,4 39,38 3,38" fill="none" stroke="#c0392b" stroke-width="4" stroke-linejoin="round"/>
              <line x1="11" y1="28" x2="31" y2="28" stroke="#c0392b" stroke-width="4" stroke-linecap="round"/>
            </svg>
          </td>
          <td style="border: none; padding: 0; vertical-align: middle; text-align: left;">
            <div class="brand-name" style="line-height: 0.9; margin: 0; padding: 0; font-weight: 900; color: #1e293b;">ARUSBAWAH</div>
            <div class="brand-sub" style="margin: 0; margin-top: 3px; padding: 0;">Media Intelligence Platform</div>
          </td>
        </tr>
      </table>
    </div>
    <div class="header-right">
      <div class="report-title">Laporan Monitoring Media</div>
      <div class="report-meta">Proyek: <strong>{{ strtoupper($projectName) }}</strong></div>
      <div class="report-meta">Periode: {{ $startDate ?? 'Semua Data' }} s/d {{ $endDate ?? now()->format('d/m/Y') }}</div>
      <div class="report-meta">Dibuat: {{ now()->format('d M Y, H:i') }} WIB</div>
    </div>
  </div>
  <hr class="divider"/>

  {{-- Teal band ──────────────────────────────────────────────────────── --}}
  <div class="teal-band">
    Laporan Analisis Sentimen &amp; Monitoring Media — Arusbawah Media Intelligence
  </div>

  @if(empty($toggles) || !empty($toggles['wawasan']))
  {{-- ══ RANGKUMAN & WAWASAN ══ --}}
  <div class="section-title" style="margin-top:20px;">Rangkuman Eksekutif</div>
  <div style="background:#f8fafc; border-left:4px solid #1fa387; padding:15px; margin-bottom:15px; font-size:11px; line-height:1.6; color:#334155; border-radius:4px;">
    <p style="margin:0;">{!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $wawasanSummary) !!}</p>
  </div>
  @endif

  @if(empty($toggles) || !empty($toggles['rekomendasi']))
  {{-- ══ REKOMENDASI ══ --}}
  <div class="section-title">Rekomendasi Tindakan</div>
  <div style="background:#fff; border:1px solid #e2e8f0; border-top:4px solid #1fa387; border-radius:8px; padding:20px; margin-bottom:30px; font-size:11px; line-height:1.6; color:#334155;">
    <ul style="margin:0; padding-left:15px;">
      @foreach($wawasanRecs as $rec)
        <li style="margin-bottom:8px;">{{ $rec }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  @if(empty($toggles) || !empty($toggles['statistik']))
  {{-- ══ STATS SUMMARY ══ --}}
  <div class="section-title">Ringkasan Statistik</div>
  <div class="stats-row">
    <div class="stat-card green">
      <div class="stat-number teal">{{ $total }}</div>
      <div class="stat-label">Total Penyebutan</div>
    </div>
    <div class="stat-card pos">
      <div class="stat-number pos">{{ $positive }}</div>
      <div class="stat-label">Positif</div>
    </div>
    <div class="stat-card neu">
      <div class="stat-number neu">{{ $neutral }}</div>
      <div class="stat-label">Netral</div>
    </div>
    <div class="stat-card neg">
      <div class="stat-number neg">{{ $negative }}</div>
      <div class="stat-label">Negatif</div>
    </div>
  </div>
  @endif

  @if(empty($toggles) || !empty($toggles['grafikSentimen']))
  {{-- ══ DONUT + SENTIMENT BAR ══ --}}
  <div class="section-title">Distribusi Sentimen</div>
  <div class="chart-wrap">
    <div class="donut-wrap">

      {{-- SVG Donut Chart --}}
      <div class="donut-chart">
        @php
          $total_s = max($positive + $neutral + $negative, 1);
          $pPct = round($positive / $total_s * 100);
          $nePct= round($neutral  / $total_s * 100);
          $ngPct= round($negative / $total_s * 100);

          // SVG donut: radius=45, cx=cy=50, stroke-width=18
          $r = 45; $cx = 50; $cy = 50;
          $circ = 2 * M_PI * $r;

          $posD = ($positive / $total_s) * $circ;
          $neuD = ($neutral  / $total_s) * $circ;
          $negD = ($negative / $total_s) * $circ;

          // offsets: start from top (-quarter circle)
          $posOff = $circ * 0.25;
          $neuOff = $posOff - $posD;
          $negOff = $neuOff - $neuD;
        @endphp
        <svg viewBox="0 0 100 100" width="120" height="120">
          {{-- Background circle --}}
          <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}"
                  fill="none" stroke="#e2e8f0" stroke-width="18"/>
          {{-- Positive --}}
          @if($positive > 0)
          <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}"
                  fill="none" stroke="#22c55e" stroke-width="18"
                  stroke-dasharray="{{ number_format($posD,4) }} {{ number_format($circ - $posD, 4) }}"
                  stroke-dashoffset="{{ number_format($posOff,4) }}"
                  transform="rotate(-90 {{ $cx }} {{ $cy }})"/>
          @endif
          {{-- Neutral --}}
          @if($neutral > 0)
          <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}"
                  fill="none" stroke="#94a3b8" stroke-width="18"
                  stroke-dasharray="{{ number_format($neuD,4) }} {{ number_format($circ - $neuD, 4) }}"
                  stroke-dashoffset="{{ number_format($neuOff,4) }}"
                  transform="rotate(-90 {{ $cx }} {{ $cy }})"/>
          @endif
          {{-- Negative --}}
          @if($negative > 0)
          <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}"
                  fill="none" stroke="#ef4444" stroke-width="18"
                  stroke-dasharray="{{ number_format($negD,4) }} {{ number_format($circ - $negD, 4) }}"
                  stroke-dashoffset="{{ number_format($negOff,4) }}"
                  transform="rotate(-90 {{ $cx }} {{ $cy }})"/>
          @endif
          {{-- Center text --}}
          <text x="{{ $cx }}" y="{{ $cy - 4 }}" text-anchor="middle"
                font-size="14" font-weight="900" fill="#1e293b">{{ $total }}</text>
          <text x="{{ $cx }}" y="{{ $cy + 8 }}" text-anchor="middle"
                font-size="6" fill="#94a3b8">TOTAL</text>
        </svg>
      </div>

      {{-- Legend --}}
      <div class="donut-legend">
        <div class="legend-item">
          <div class="legend-dot"><span style="background:#22c55e;"></span></div>
          <div class="legend-text">Positif</div>
          <div class="legend-val" style="color:#16a34a;">{{ $positive }} <small>({{ $pPct }}%)</small></div>
        </div>
        <div class="legend-item">
          <div class="legend-dot"><span style="background:#94a3b8;"></span></div>
          <div class="legend-text">Netral</div>
          <div class="legend-val" style="color:#475569;">{{ $neutral }} <small>({{ $nePct }}%)</small></div>
        </div>
        <div class="legend-item">
          <div class="legend-dot"><span style="background:#ef4444;"></span></div>
          <div class="legend-text">Negatif</div>
          <div class="legend-val" style="color:#dc2626;">{{ $negative }} <small>({{ $ngPct }}%)</small></div>
        </div>
      </div>
    </div>
  </div>
  @endif

  @if(empty($toggles) || (!empty($toggles['sumberBerita']) || !empty($toggles['sumberMedsos'])))
  {{-- ══ BAR CHART (PENYEBUTAN PER SUMBER) ══ --}}
  <div class="section-title" style="page-break-before: always;">Penyebutan Per Sumber</div>
  <div class="chart-wrap">
  @php $maxSrc = max(array_values($sourceCounts) ?: [1]); @endphp
    @foreach(array_slice($sourceCounts, 0, 10, true) as $src => $cnt)
    @php
      $pct = $maxSrc > 0 ? ($cnt / $maxSrc) * 100 : 0;
      $colors = ['Twitter'=>'#1da1f2','Instagram'=>'#e1306c','Youtube'=>'#ff0000',
                 'Tiktok'=>'#010101','Facebook'=>'#1877f2','News'=>'#1fa387','Threads'=>'#000'];
      $c = $colors[$src] ?? '#1fa387';
    @endphp
    <div class="bar-row">
      <div class="bar-label">{{ $src }}</div>
      <div class="bar-track">
        <div class="bar-bg">
          <div class="bar-fill" style="width:{{ $pct }}%;background:{{ $c }};"></div>
        </div>
      </div>
      <div class="bar-val" style="color:{{ $c }};">{{ $cnt }}</div>
    </div>
    @endforeach
  </div>
  @endif

  @if(empty($toggles) || !empty($toggles['konteks']))
  {{-- ══ KONTEKS PERCAKAPAN (AWAN KATA) ══ --}}
  <div class="section-title">Konteks Percakapan (Topik Utama)</div>
  <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px; text-align:center; margin-bottom:30px;">
    @if(empty($topWords))
      <p style="color:#94a3b8; font-size:11px;">Belum ada data topik yang cukup.</p>
    @else
      <div style="line-height:2;">
        @foreach($topWords as $word => $count)
          @php
            $maxCount = max($topWords);
            $ratio = $count / $maxCount;
            $fontSize = 10 + ($ratio * 16); // 10px to 26px
            $opacity = 0.4 + ($ratio * 0.6); // 0.4 to 1.0
            $weight = $ratio > 0.6 ? 'bold' : 'normal';
          @endphp
          <span style="display:inline-block; margin:0 8px; font-size:{{ $fontSize }}px; font-weight:{{ $weight }}; color:rgba(31,163,135,{{ $opacity }}); text-transform:uppercase;">
            {{ $word }}
          </span>
        @endforeach
      </div>
    @endif
  </div>
  @endif

  @if(empty($toggles) || !empty($toggles['perKataKunci']))
  {{-- ══ PER KATA KUNCI ══ --}}
  <div class="section-title">Analisis Per Kata Kunci</div>
  <table class="data-table" style="margin-bottom:30px;">
    <thead>
      <tr>
        <th style="width:10%;">#</th>
        <th style="width:60%;">Kata Kunci</th>
        <th style="width:30%;">Total Penyebutan</th>
      </tr>
    </thead>
    <tbody>
      @if(empty($keywordsTable))
      <tr><td colspan="3" style="text-align:center; color:#94a3b8;">Belum ada data kata kunci.</td></tr>
      @else
        @php $i = 1; @endphp
        @foreach($keywordsTable as $kw => $count)
        <tr>
          <td style="text-align:center;">{{ $i++ }}</td>
          <td style="font-weight:bold; color:#1e293b;"># {{ strtoupper($kw) }}</td>
          <td>{{ $count }}</td>
        </tr>
        @endforeach
      @endif
    </tbody>
  </table>
  @endif

  @if(empty($toggles) || !empty($toggles['beritaTerpopuler']))
  {{-- ══ BERITA TERPOPULER ══ --}}
  <div class="section-title" style="page-break-before: always;">Berita Terpopuler (Berdasarkan Jangkauan)</div>
  <table class="data-table" style="margin-bottom:30px;">
    <thead>
      <tr>
        <th>#</th>
        <th>Judul</th>
        <th>Sumber</th>
        <th>Sentimen</th>
        <th>Potensi Reach</th>
      </tr>
    </thead>
    <tbody>
      @if($topReachArticles->isEmpty())
      <tr><td colspan="5" style="text-align:center; color:#94a3b8;">Belum ada artikel yang dinilai AI untuk jangkauan.</td></tr>
      @else
        @foreach($topReachArticles as $art)
        @php
          $badgeClass = match($art->sentiment) {
            'positive' => 'badge-pos',
            'negative' => 'badge-neg',
            default    => 'badge-neu',
          };
          $badgeLabel = match($art->sentiment) {
            'positive' => 'Positif',
            'negative' => 'Negatif',
            default    => 'Netral',
          };
          $analysis = $art->aiAnalysisResult;
          $reach = $analysis ? $analysis->officialArticleEstimatedReaders() : null;
        @endphp
        <tr>
          <td style="text-align:center;">{{ $loop->iteration }}</td>
          <td style="max-width:250px;">{{ \Str::limit($art->title, 70) }}</td>
          <td>{{ $art->source_name }}</td>
          <td><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
          <td style="font-weight:bold; color:#1fa387;">
            {{ $reach !== null ? number_format($reach, 0, ',', '.') : 'Belum tersedia' }}
          </td>
        </tr>
        @endforeach
      @endif
    </tbody>
  </table>
  @endif

  @if(empty($toggles) || !empty($toggles['beritaTerbaru']))
  {{-- ══ TOP ARTICLES TABLE ══ --}}
  <div class="section-title">10 Penyebutan Terbaru</div>
  <table class="data-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Judul</th>
        <th>Sumber</th>
        <th>Kategori</th>
        <th>Sentimen</th>
        <th>Tanggal</th>
        <th>Potensi Reach</th>
      </tr>
    </thead>
    <tbody>
      @php
        $topArticles = $articles->sortBy(function($art) {
          if ($art->sentiment === 'negative') return 1;
          if ($art->sentiment === 'neutral') return 2;
          return 3;
        })->take(10);
      @endphp
      @foreach($topArticles as $i => $art)
      @php
        $badgeClass = match($art->sentiment) {
          'positive' => 'badge-pos',
          'negative' => 'badge-neg',
          default    => 'badge-neu',
        };
        $badgeLabel = match($art->sentiment) {
          'positive' => 'Positif',
          'negative' => 'Negatif',
          default    => 'Netral',
        };
        $reach = ['potential' => 'Belum dinilai AI'];
        if ($art->aiAnalysisResult && $art->aiAnalysisResult->hasCompleteOfficialAiResult()) {
          $analysis = $art->aiAnalysisResult;
          $officialReaders = $analysis->officialArticleEstimatedReaders();
          $reach = [
            'potential' => $officialReaders !== null
              ? sprintf('%d pembaca', $officialReaders)
              : 'Belum tersedia',
          ];
        }
      @endphp
      <tr>
        <td style="text-align:center;">{{ $loop->iteration }}</td>
        <td style="max-width:200px;">{{ \Str::limit($art->title, 65) }}</td>
        <td>{{ $art->source_name }}</td>
        <td>{{ $art->category }}</td>
        <td><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
        <td style="white-space:nowrap;">
          {{ $art->published_at ? \Carbon\Carbon::parse($art->published_at)->format('d/m/Y') : '-' }}
        </td>
        <td style="white-space:nowrap;font-size:7px;">{{ $reach['potential'] }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  @if(empty($toggles) || !empty($toggles['sumberMedsos']))
  {{-- ══ DATA MEDSOS ══ --}}
  <div class="section-title" style="page-break-before: always;">Data Media Sosial</div>
  <table class="data-table" style="margin-bottom:30px;">
    <thead>
      <tr>
        <th style="width:6%;">#</th>
        <th style="width:14%;">Platform</th>
        <th style="width:22%;">Judul / Author</th>
        <th style="width:30%;">URL</th>
        <th style="width:14%;">Tanggal Post</th>
        <th style="width:10%;">Sentimen</th>
        <th style="width:10%;">AI</th>
      </tr>
    </thead>
    <tbody>
      @if(empty($socialMediaItems) || $socialMediaItems->isEmpty())
      <tr><td colspan="7" style="text-align:center; color:#94a3b8;">Belum ada data media sosial.</td></tr>
      @else
        @foreach($socialMediaItems->take(12) as $item)
          @php
            $ai = $item->aiAnalysisResult ?? null;
            $sentiment = $ai?->sentiment ? ucfirst((string) $ai->sentiment) : '-';
            $aiLabel = $ai && $ai->hasCompleteOfficialAiResult()
              ? ucfirst((string) ($ai->analysis_status ?? '-'))
              : 'Belum dianalisis';
          @endphp
          <tr>
            <td style="text-align:center;">{{ $loop->iteration }}</td>
            <td>{{ $item->platform }}</td>
            <td style="max-width:180px;">
              <div style="font-weight:bold; color:#1e293b;">{{ \Str::limit($item->title ?? ('Post dari ' . $item->platform), 50) }}</div>
              <div style="font-size:7px; color:#64748b;">{{ $item->author_name ?? '-' }}</div>
            </td>
            <td style="font-size:7px; word-break:break-all;">{{ \Str::limit($item->post_url ?? '-', 60) }}</td>
            <td style="white-space:nowrap;">{{ $item->posted_at ? \Carbon\Carbon::parse($item->posted_at)->format('d/m/Y H:i') : 'Tanggal tidak tersedia' }}</td>
            <td style="white-space:nowrap;">{{ $sentiment }}</td>
            <td style="white-space:nowrap;">{{ $aiLabel }}</td>
          </tr>
        @endforeach
      @endif
    </tbody>
  </table>
  @endif

  {{-- ══ FOOTER ══ --}}
  <div class="footer">
    <div class="footer-left">
      <strong>ARUSBAWAH Media Intelligence</strong> &nbsp;|&nbsp; Laporan ini dibuat secara otomatis oleh sistem
    </div>
    <div class="footer-right">
      Halaman 1 &nbsp;|&nbsp; Rahasia — Hanya untuk internal
    </div>
  </div>

  @if(empty($toggles) || !empty($toggles['sumberMedsos']))
  {{-- ══ SUMBER MEDSOS ══ --}}
  <div class="section-title">Sumber Media Sosial</div>
  <table class="data-table" style="margin-bottom:30px;">
    <thead>
      <tr>
        <th style="width:10%;">#</th>
        <th style="width:60%;">Platform</th>
        <th style="width:30%;">Total Interaksi / Post</th>
      </tr>
    </thead>
    <tbody>
      @if(empty($socialCounts))
      <tr><td colspan="3" style="text-align:center; color:#94a3b8;">Belum ada data media sosial.</td></tr>
      @else
        @php $i = 1; @endphp
        @foreach($socialCounts as $plat => $count)
        <tr>
          <td style="text-align:center;">{{ $i++ }}</td>
          <td style="font-weight:bold; color:#1e293b;">{{ $plat }}</td>
          <td>{{ $count }}</td>
        </tr>
        @endforeach
      @endif
    </tbody>
  </table>
  @endif

</div>
</body>
</html>
