<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<style>
  @page {
    size: A4 landscape;
    margin: 20px 25px;
  }
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size:8.5px; color:#1e293b; background:#fff; line-height: 1.3; }

  /* ── Structural Layout ── */
  .page { width: 100%; height: 100%; position: relative; }
  .grid-2 { display: table; width: 100%; table-layout: fixed; border-spacing: 12px 0; }
  .col { display: table-cell; vertical-align: top; }
  .col-6 { width: 50%; }
  .col-4 { width: 33.33%; }

  /* ── Header ── */
  .header { display:table; width:100%; margin-bottom:12px; border-bottom: 2px solid #0f172a; padding-bottom: 8px; }
  .header-left  { display:table-cell; vertical-align:middle; width:50%; }
  .header-right { display:table-cell; vertical-align:middle; width:50%; text-align:right; }
  .brand-title { font-size:18px; font-weight:900; color:#0f172a; letter-spacing:1px; }
  .brand-sub { font-size:7px; color:#64748b; letter-spacing:2px; text-transform:uppercase; margin-top:2px; }
  .report-title { font-size:14px; font-weight:800; color:#0d9488; }
  .report-meta { font-size:8px; color:#475569; margin-top:2px; }

  /* ── Section Title ── */
  .section-title {
    font-size:9.5px; font-weight:800; color:#0f172a;
    letter-spacing:1px; text-transform:uppercase;
    border-bottom: 2px solid #0d9488;
    padding-bottom: 3px; margin-bottom: 8px; margin-top: 10px;
  }

  /* ── Cards & Panels ── */
  .card {
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 6px; padding: 10px 12px; margin-bottom: 8px;
  }
  .card-primary { border-top: 3px solid #0d9488; }
  .card-accent { border-top: 3px solid #6366f1; }

  /* ── Stats ── */
  .stats-grid { display: table; width: 100%; border-spacing: 6px; margin: -6px 0 6px 0; }
  .stat-box { display: table-cell; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; text-align: center; }
  .stat-box.total { background: #f0fdfa; border-color: #99f6e4; }
  .stat-box.pos { background: #f0fdf4; border-color: #bbf7d0; }
  .stat-box.neu { background: #f8fafc; border-color: #e2e8f0; }
  .stat-box.neg { background: #fef2f2; border-color: #fecaca; }
  .stat-val { font-size: 16px; font-weight: 800; color: #0f172a; }
  .stat-val.total { color: #0d9488; }
  .stat-val.pos { color: #16a34a; }
  .stat-val.neu { color: #475569; }
  .stat-val.neg { color: #dc2626; }
  .stat-lbl { font-size: 7px; color: #64748b; font-weight: 700; text-transform: uppercase; margin-top: 2px; }

  /* ── Donut Chart ── */
  .donut-container { display: table; width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; }
  .donut-chart { display: table-cell; width: 35%; text-align: center; vertical-align: middle; }
  .donut-legend { display: table-cell; width: 65%; vertical-align: middle; padding-left: 10px; }
  .legend-item { display: table; width: 100%; margin-bottom: 4px; }
  .legend-color { display: table-cell; width: 8px; vertical-align: middle; }
  .legend-color span { display: block; width: 6px; height: 6px; border-radius: 50%; }
  .legend-lbl { display: table-cell; font-size: 8px; color: #475569; padding-left: 4px; }
  .legend-val { display: table-cell; text-align: right; font-weight: 700; font-size: 8px; }

  /* ── Table style ── */
  table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 7.5px; }
  table.data-table th { background: #0f172a; color: #fff; padding: 4px 6px; font-weight: 700; text-align: left; text-transform: uppercase; font-size: 7px; }
  table.data-table td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; color: #334155; vertical-align: middle; }
  table.data-table tr:nth-child(even) td { background: #f8fafc; }

  .badge { display: inline-block; padding: 1px 4px; border-radius: 4px; font-size: 6.5px; font-weight: 700; text-transform: uppercase; }
  .badge-pos { background: #dcfce7; color: #15803d; }
  .badge-neg { background: #fee2e2; color: #b91c1c; }
  .badge-neu { background: #f1f5f9; color: #475569; }

  /* ── Bar Chart ── */
  .bar-row { display: table; width: 100%; margin-bottom: 4px; }
  .bar-lbl { display: table-cell; width: 25%; font-size: 7.5px; color: #475569; }
  .bar-track { display: table-cell; vertical-align: middle; padding: 0 4px; }
  .bar-bg { background: #e2e8f0; height: 8px; border-radius: 4px; width: 100%; }
  .bar-fill { height: 8px; border-radius: 4px; }
  .bar-val { display: table-cell; width: 12%; text-align: right; font-weight: 700; font-size: 7.5px; }

  /* ── Word Cloud ── */
  .wordcloud { line-height: 1.8; text-align: center; padding: 6px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; }

  /* ── Footer ── */
  .footer {
    position: absolute; bottom: 0; left: 0; right: 0;
    border-top: 1px solid #e2e8f0; padding-top: 4px;
    display: table; width: 100%; font-size: 7px; color: #94a3b8;
  }
  .footer-left  { display: table-cell; }
  .footer-right { display: table-cell; text-align: right; }
</style>
</head>
<body>

  <!-- ==================== HALAMAN 1 ==================== -->
  <div class="page">
    <div class="header">
      <div class="header-left">
        <span class="brand-title">ARUSBAWAH</span>
        <div class="brand-sub">Media Intelligence Platform</div>
      </div>
      <div class="header-right">
        <span class="report-title">LAPORAN MONITORING MEDIA (LANDSCAPE)</span>
        <div class="report-meta">
          Proyek: <strong>{{ strtoupper($projectName) }}</strong> &nbsp;|&nbsp;
          Periode: {{ $startDate ?? 'Semua Data' }} - {{ $endDate ?? now()->format('d/m/Y') }}
        </div>
      </div>
    </div>

    <div class="grid-2">
      <!-- Kolom Kiri: Kesimpulan & Rekomendasi -->
      <div class="col col-6">
        @if(empty($toggles) || !empty($toggles['wawasan']))
        <div class="section-title">Rangkuman Eksekutif &amp; Kesimpulan</div>
        <div class="card card-primary" style="font-size: 9px; line-height: 1.5; color: #334155;">
          {!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $wawasanSummary) !!}
        </div>
        @endif

        @if(empty($toggles) || !empty($toggles['rekomendasi']))
        <div class="section-title">Rekomendasi Tindakan</div>
        <div class="card card-accent" style="font-size: 8.5px; line-height: 1.5; color: #334155;">
          <ul style="padding-left: 12px; margin: 0;">
            @foreach($wawasanRecs as $rec)
              <li style="margin-bottom: 4px;">{{ $rec }}</li>
            @endforeach
          </ul>
        </div>
        @endif
      </div>

      <!-- Kolom Kanan: Statistik & Sentimen -->
      <div class="col col-6">
        @if(empty($toggles) || !empty($toggles['statistik']))
        <div class="section-title">Ringkasan Statistik</div>
        <div class="stats-grid">
          <div class="stat-box total">
            <div class="stat-val total">{{ $total }}</div>
            <div class="stat-lbl">Total Sebutan</div>
          </div>
          <div class="stat-box pos">
            <div class="stat-val pos">{{ $positive }}</div>
            <div class="stat-lbl">Positif</div>
          </div>
          <div class="stat-box neu">
            <div class="stat-val neu">{{ $neutral }}</div>
            <div class="stat-lbl">Netral</div>
          </div>
          <div class="stat-box neg">
            <div class="stat-val neg">{{ $negative }}</div>
            <div class="stat-lbl">Negatif</div>
          </div>
        </div>
        @endif

        @if(empty($toggles) || !empty($toggles['grafikSentimen']))
        <div class="section-title">Distribusi Sentimen</div>
        <div class="donut-container">
          <div class="donut-chart">
            @php
              $total_s = max($total, 1);
              $pPct = round($positive / $total_s * 100);
              $nePct= round($neutral  / $total_s * 100);
              $ngPct= round($negative / $total_s * 100);

              $r = 38; $cx = 50; $cy = 50;
              $circ = 2 * M_PI * $r;

              $posD = ($positive / $total_s) * $circ;
              $neuD = ($neutral  / $total_s) * $circ;
              $negD = ($negative / $total_s) * $circ;

              $posOff = $circ * 0.25;
              $neuOff = $posOff - $posD;
              $negOff = $neuOff - $neuD;
            @endphp
            <svg viewBox="0 0 100 100" width="100" height="100">
              <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="none" stroke="#e2e8f0" stroke-width="12"/>
              @if($positive > 0)
              <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="none" stroke="#10b981" stroke-width="12"
                      stroke-dasharray="{{ number_format($posD,4) }} {{ number_format($circ - $posD, 4) }}"
                      stroke-dashoffset="{{ number_format($posOff,4) }}" transform="rotate(-90 {{ $cx }} {{ $cy }})"/>
              @endif
              @if($neutral > 0)
              <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="none" stroke="#64748b" stroke-width="12"
                      stroke-dasharray="{{ number_format($neuD,4) }} {{ number_format($circ - $neuD, 4) }}"
                      stroke-dashoffset="{{ number_format($neuOff,4) }}" transform="rotate(-90 {{ $cx }} {{ $cy }})"/>
              @endif
              @if($negative > 0)
              <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="none" stroke="#ef4444" stroke-width="12"
                      stroke-dasharray="{{ number_format($negD,4) }} {{ number_format($circ - $negD, 4) }}"
                      stroke-dashoffset="{{ number_format($negOff,4) }}" transform="rotate(-90 {{ $cx }} {{ $cy }})"/>
              @endif
              <text x="{{ $cx }}" y="{{ $cy - 2 }}" text-anchor="middle" font-size="12" font-weight="900" fill="#0f172a">{{ $total }}</text>
              <text x="{{ $cx }}" y="{{ $cy + 8 }}" text-anchor="middle" font-size="5.5" fill="#94a3b8" font-weight="700">TOTAL</text>
            </svg>
          </div>
          <div class="donut-legend">
            <div class="legend-item">
              <div class="legend-color"><span style="background:#10b981;"></span></div>
              <div class="legend-lbl">Positif</div>
              <div class="legend-val" style="color:#059669;">{{ $positive }} ({{ $pPct }}%)</div>
            </div>
            <div class="legend-item">
              <div class="legend-color"><span style="background:#64748b;"></span></div>
              <div class="legend-lbl">Netral</div>
              <div class="legend-val" style="color:#475569;">{{ $neutral }} ({{ $nePct }}%)</div>
            </div>
            <div class="legend-item">
              <div class="legend-color"><span style="background:#ef4444;"></span></div>
              <div class="legend-lbl">Negatif</div>
              <div class="legend-val" style="color:#dc2626;">{{ $negative }} ({{ $ngPct }}%)</div>
            </div>
          </div>
        </div>
        @endif
      </div>
    </div>

    <div class="footer">
      <div class="footer-left"><strong>ARUSBAWAH Media Intelligence</strong> | Laporan Hasil Analimen</div>
      <div class="footer-right">Halaman 1 dari 2 &nbsp;|&nbsp; Rahasia</div>
    </div>
  </div>

  <div style="page-break-before: always;"></div>

  <!-- ==================== HALAMAN 2 ==================== -->
  <div class="page">
    <div class="header">
      <div class="header-left">
        <span class="brand-title">ARUSBAWAH</span>
        <div class="brand-sub">Media Intelligence Platform</div>
      </div>
      <div class="header-right">
        <span class="report-title">ANALISIS DATA DETAIL</span>
        <div class="report-meta">Proyek: <strong>{{ strtoupper($projectName) }}</strong></div>
      </div>
    </div>

    <div class="grid-2" style="border-spacing: 10px 0;">
      <!-- Kolom Kiri: Sumber & Topik -->
      <div class="col col-4">
        @if(empty($toggles) || !empty($toggles['sumberBerita']) || !empty($toggles['sumberMedsos']))
        <div class="section-title">Penyebutan Per Sumber</div>
        <div class="card" style="padding: 8px;">
          @php $maxSrc = max(array_values($sourceCounts) ?: [1]); @endphp
          @foreach(array_slice($sourceCounts, 0, 5, true) as $src => $cnt)
            @php
              $pct = $maxSrc > 0 ? ($cnt / $maxSrc) * 100 : 0;
              $colors = ['Twitter'=>'#1da1f2','Instagram'=>'#e1306c','Youtube'=>'#ff0000',
                         'Tiktok'=>'#010101','Facebook'=>'#1877f2','News'=>'#0d9488','Threads'=>'#000'];
              $c = $colors[$src] ?? '#0d9488';
            @endphp
            <div class="bar-row">
              <div class="bar-lbl">{{ \Str::limit($src, 10) }}</div>
              <div class="bar-track">
                <div class="bar-bg">
                  <div class="bar-fill" style="width:{{ $pct }}%; background:{{ $c }};"></div>
                </div>
              </div>
              <div class="bar-val" style="color:{{ $c }};">{{ $cnt }}</div>
            </div>
          @endforeach
        </div>
        @endif

        @if(empty($toggles) || !empty($toggles['konteks']))
        <div class="section-title">Konteks Percakapan (Kata Kunci)</div>
        <div class="wordcloud">
          @if(empty($topWords))
            <p style="color:#94a3b8; font-size:8px;">Belum ada data topik yang cukup.</p>
          @else
            @foreach(array_slice($topWords, 0, 12, true) as $word => $count)
              @php
                $maxCount = max($topWords);
                $ratio = $count / $maxCount;
                $fontSize = 8 + ($ratio * 6);
                $opacity = 0.5 + ($ratio * 0.5);
              @endphp
              <span style="display:inline-block; margin:2px 4px; font-size:{{ $fontSize }}px; font-weight:bold; color:rgba(13,148,136,{{ $opacity }}); text-transform:uppercase;">
                {{ $word }}
              </span>
            @endforeach
          @endif
        </div>
        @endif
      </div>

      <!-- Kolom Tengah: Kata Kunci & Media Sosial -->
      <div class="col col-4">
        @if(empty($toggles) || !empty($toggles['perKataKunci']))
        <div class="section-title">Analisis Kata Kunci</div>
        <table class="data-table">
          <thead>
            <tr>
              <th style="width:15%;">#</th>
              <th>Kata Kunci</th>
              <th style="width:25%;">Total</th>
            </tr>
          </thead>
          <tbody>
            @if(empty($keywordsTable))
              <tr><td colspan="3" style="text-align:center; color:#94a3b8;">Tidak ada data.</td></tr>
            @else
              @foreach(array_slice($keywordsTable, 0, 4, true) as $kw => $count)
              <tr>
                <td style="text-align:center;">{{ $loop->iteration }}</td>
                <td style="font-weight:bold; color:#0f172a;">#{{ strtoupper($kw) }}</td>
                <td>{{ $count }}</td>
              </tr>
              @endforeach
            @endif
          </tbody>
        </table>
        @endif

        @if(empty($toggles) || !empty($toggles['sumberMedsos']))
        <div class="section-title">Aktivitas Media Sosial</div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Platform</th>
              <th>User</th>
              <th style="width:15%;">Sentimen</th>
            </tr>
          </thead>
          <tbody>
            @if(empty($socialMediaItems) || $socialMediaItems->isEmpty())
              <tr><td colspan="3" style="text-align:center; color:#94a3b8;">Tidak ada data medsos.</td></tr>
            @else
              @foreach($socialMediaItems->take(4) as $item)
                @php
                  $ai = $item->aiAnalysisResult ?? null;
                  $sentiment = $ai?->sentiment ? ucfirst((string) $ai->sentiment) : 'Netral';
                  $badgeClass = match(strtolower($sentiment)) {
                    'positive', 'positif' => 'badge-pos',
                    'negative', 'negatif' => 'badge-neg',
                    default    => 'badge-neu',
                  };
                @endphp
                <tr>
                  <td style="font-weight:bold;">{{ $item->platform }}</td>
                  <td>{{ \Str::limit($item->author_name ?? 'User', 15) }}</td>
                  <td><span class="badge {{ $badgeClass }}">{{ $sentiment }}</span></td>
                </tr>
              @endforeach
            @endif
          </tbody>
        </table>
        @endif
      </div>

      <!-- Kolom Kanan: Berita Terpopuler & Terbaru -->
      <div class="col col-4">
        @if(empty($toggles) || !empty($toggles['beritaTerpopuler']))
        <div class="section-title">Berita Jangkauan Tertinggi</div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Judul Berita</th>
              <th style="width:25%;">Reach</th>
            </tr>
          </thead>
          <tbody>
            @if($topReachArticles->isEmpty())
              <tr><td colspan="2" style="text-align:center; color:#94a3b8;">Tidak ada data.</td></tr>
            @else
              @foreach($topReachArticles->take(4) as $art)
                @php
                  $analysis = $art->aiAnalysisResult;
                  $reach = $analysis ? $analysis->officialArticleEstimatedReaders() : null;
                @endphp
                <tr>
                  <td>{{ \Str::limit($art->title, 35) }}</td>
                  <td style="font-weight:bold; color:#0d9488;">
                    {{ $reach ? number_format($reach, 0, ',', '.') : '-' }}
                  </td>
                </tr>
              @endforeach
            @endif
          </tbody>
        </table>
        @endif

        @if(empty($toggles) || !empty($toggles['beritaTerbaru']))
        <div class="section-title">Penyebutan Terbaru</div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Judul</th>
              <th style="width:20%;">Tgl</th>
            </tr>
          </thead>
          <tbody>
            @php
              $topArticles = $articles->sortBy(function($art) {
                if ($art->sentiment === 'negative') return 1;
                if ($art->sentiment === 'neutral') return 2;
                return 3;
              })->take(4);
            @endphp
            @foreach($topArticles as $art)
              <tr>
                <td>{{ \Str::limit($art->title, 35) }}</td>
                <td>{{ $art->published_at ? \Carbon\Carbon::parse($art->published_at)->format('d/m') : '-' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
        @endif
      </div>
    </div>

    <div class="footer">
      <div class="footer-left"><strong>ARUSBAWAH Media Intelligence</strong> | Laporan Hasil Analisis Sentimen</div>
      <div class="footer-right">Halaman 2 dari 2 &nbsp;|&nbsp; Rahasia</div>
    </div>
  </div>

</body>
</html>
