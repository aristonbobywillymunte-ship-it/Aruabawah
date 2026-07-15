<!DOCTYPE html>
<html lang="id" class="light">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Laporan Media Intelligence - {{ $projectName }}</title>

<!-- Load Tailwind CSS -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

<!-- Load Fonts and Material Symbols Icons -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1" rel="stylesheet"/>

<script id="tailwind-config">
  tailwind.config = {
    darkMode: "class",
    theme: {
      extend: {
        colors: {
          "background": "#ffffff",
          "surface": "#f8fafc",
          "surface-container": "#f1f5f9",
          "surface-container-high": "#e2e8f0",
          "on-surface": "#0f172a",
          "on-surface-variant": "#475569",
          "primary": "#0284c7",
          "primary-container": "#e0f2fe",
          "on-primary-container": "#0369a1",
          "outline": "#cbd5e1",
          "outline-variant": "#e2e8f0",
          "positive": "#059669",
          "neutral": "#ea580c",
          "negative": "#7c3aed"
        },
        borderRadius: {
          "DEFAULT": "0.5rem",
          "lg": "1rem",
          "xl": "1.5rem",
          "full": "9999px"
        },
        fontFamily: {
          "sans": ["Inter", "sans-serif"]
        }
      }
    }
  }
</script>

<style>
  /* ── A4 Landscape Frame for Browser Preview ── */
  .a4-landscape {
    width: 297mm;
    height: 210mm;
    margin: 30px auto;
    background: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    position: relative;
    box-sizing: border-box;
  }
  
  /* ── Print Styles: Forces chrome to render exactly as A4 Landscape margins 0 ── */
  @media print {
    body {
      background: #ffffff !important;
      padding: 0 !important;
      margin: 0 !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    .no-print {
      display: none !important;
    }
    .a4-landscape {
      box-shadow: none !important;
      margin: 0 !important;
      border: none !important;
      width: 297mm !important;
      height: 210mm !important;
      page-break-after: always !important;
      overflow: hidden !important;
    }
    @page {
      size: A4 landscape;
      margin: 0;
    }
  }

  .glass-panel {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
  }
</style>
</head>
<body class="bg-slate-100 text-on-surface font-sans selection:bg-primary/20 selection:text-primary">

  <!-- Print Helper Banner (Visible only in web preview) -->
  <div class="no-print bg-amber-50 border-b border-amber-200 py-3 px-4 text-center text-xs font-semibold text-amber-800 flex items-center justify-center gap-2 shadow-sm">
    <span>💡 <strong>Tip Cetak Premium</strong>: Tekan <kbd class="bg-amber-100 px-1.5 py-0.5 rounded border border-amber-300">Cmd + P</kbd> (Mac) atau <kbd class="bg-amber-100 px-1.5 py-0.5 rounded border border-amber-300">Ctrl + P</kbd> (Windows) lalu pilih tujuan <strong>"Save as PDF"</strong> dengan Tata Letak <strong>Lanskap</strong> dan Margin <strong>Minimum / None</strong>.</span>
    <button onclick="window.print()" class="bg-amber-600 hover:bg-amber-700 text-white text-[11px] font-bold px-3 py-1 rounded-lg shadow-sm transition ml-3 cursor-pointer">
      Cetak Sekarang
    </button>
  </div>

  <!-- Resolve logo base64 dynamically for robust PDF embedding -->
  @php
    $logoBase64 = null;
    $customLogo = \App\Helpers\AppBrandingHelper::getAppLogoPath();
    if ($customLogo) {
        $logoFullPath = storage_path('app/public/' . $customLogo);
        if (file_exists($logoFullPath)) {
            $logoData = base64_encode(file_get_contents($logoFullPath));
            $mimeType = mime_content_type($logoFullPath);
            $logoBase64 = 'data:' . $mimeType . ';base64,' . $logoData;
        }
    }
  @endphp

  <!-- ==================== HALAMAN 1 ==================== -->
  <div class="a4-landscape flex flex-col p-8 justify-between">
    <!-- Header -->
    <header class="flex justify-between items-end border-b border-outline-variant pb-4 shrink-0">
      <div class="flex items-center gap-5">
        <div class="p-2 bg-white rounded-xl border border-outline-variant shadow-sm">
          @if($logoBase64)
            <img class="h-10 w-auto object-contain" src="{{ $logoBase64 }}" alt="Logo"/>
          @else
            <span class="w-10 h-10 bg-primary text-white rounded-lg flex items-center justify-center font-black text-xl shadow-md">S</span>
          @endif
        </div>
        <div>
          <h1 class="font-extrabold text-2xl text-slate-900 tracking-tight leading-none">Arusbawah Intelligence</h1>
          <p class="font-bold text-[10px] text-on-surface-variant uppercase tracking-[0.2em] mt-1.5">Laporan Tinjauan Strategis</p>
        </div>
      </div>
      
      <div class="text-right">
        <div class="grid grid-cols-2 gap-x-3 text-left text-[11px] text-on-surface-variant leading-relaxed">
          <div class="font-semibold text-right opacity-60">Proyek:</div>
          <div class="text-slate-900 font-bold">{{ strtoupper($projectName) }}</div>
          <div class="font-semibold text-right opacity-60">Periode:</div>
          <div class="text-slate-900 font-bold">{{ $startDate ?? 'Semua Data' }} - {{ $endDate ?? now()->format('d/m/Y') }}</div>
          <div class="font-semibold text-right opacity-60">Dibuat:</div>
          <div class="text-slate-900 font-bold">{{ now()->format('d/m/Y H:i') }} WIB</div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <div class="grid grid-cols-12 gap-6 flex-grow my-4 overflow-hidden">
      <!-- Left Column: Narrative (66%) -->
      <div class="col-span-8 flex flex-col gap-4">
        <!-- Executive Summary -->
        @if(empty($toggles) || !empty($toggles['wawasan']))
        <div>
          <div class="flex items-center gap-2 mb-2 border-b border-outline-variant pb-1">
            <span class="material-symbols-outlined text-primary text-[16px]">auto_graph</span>
            <h2 class="font-bold text-[10px] uppercase tracking-widest text-slate-800">Kesimpulan AI</h2>
          </div>
          <div class="glass-panel rounded-xl p-4 border-glow">
            <p class="text-on-surface-variant leading-relaxed text-[12px] text-justify break-words">
              {!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $wawasanSummary) !!}
            </p>
          </div>
        </div>
        @endif

        <!-- Strategic Recommendations -->
        @if(empty($toggles) || !empty($toggles['rekomendasi']))
        <div>
          <div class="flex items-center gap-2 mb-2 border-b border-outline-variant pb-1">
            <span class="material-symbols-outlined text-primary text-[16px]">lightbulb</span>
            <h2 class="font-bold text-[10px] uppercase tracking-widest text-slate-800">Rekomendasi AI</h2>
          </div>
          <div class="bg-violet-50/50 border border-violet-100 rounded-xl p-4">
            <ul class="space-y-1.5 text-on-surface-variant text-[11.5px] text-justify">
              @foreach($wawasanRecs as $rec)
                <li class="flex items-start gap-2.5 break-words">
                  <span class="w-1.5 h-1.5 rounded-full bg-violet-600 mt-1.5 shrink-0"></span>
                  <span class="leading-normal">{{ $rec }}</span>
                </li>
              @endforeach
            </ul>
          </div>
        </div>
        @endif

        <!-- Temporal Trends (Grafik Penyebutan) -->
        @if(empty($toggles) || !empty($toggles['grafikPenyebutan']))
        <div>
          <div class="flex items-center gap-2 mb-2 border-b border-outline-variant pb-1">
            <span class="material-symbols-outlined text-primary text-[16px]">query_stats</span>
            <h2 class="font-bold text-[10px] uppercase tracking-widest text-slate-800">Grafik Penyebutan</h2>
          </div>
          <div class="bg-slate-50 border border-slate-200 rounded-xl flex flex-col justify-between p-3.5 relative shadow-sm gap-2">
            <div class="flex justify-between items-center">
              <span class="text-[9px] font-bold text-slate-500 uppercase tracking-wider font-semibold">Perkembangan Tren Kuartal</span>
              <span class="text-[9px] font-black text-primary bg-primary/10 px-2 py-0.5 rounded border border-primary/20 font-bold">Pertumbuhan +14.2%</span>
            </div>
            
            <div class="flex items-center flex-grow mt-0.5">
              <!-- Y-Axis Labels -->
              <div class="flex flex-col justify-between h-16 text-[8.5px] font-bold text-slate-400 pr-2.5 border-r border-slate-200 text-right w-11 font-semibold">
                <span>10.000</span>
                <span>5.000</span>
                <span>0</span>
              </div>
              
              <!-- Chart Area -->
              <div class="flex-grow pl-2.5 h-16 relative">
                <svg viewBox="0 0 400 80" class="w-full h-full overflow-visible">
                  <!-- Dotted Horizontal Gridlines -->
                  <line x1="0" y1="0" x2="400" y2="0" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="3,3"/>
                  <line x1="0" y1="40" x2="400" y2="40" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="3,3"/>
                  <line x1="0" y1="80" x2="400" y2="80" stroke="#e2e8f0" stroke-width="1"/>
                  
                  <defs>
                    <linearGradient id="areaGrad2" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="#0284c7" stop-opacity="0.25" />
                      <stop offset="100%" stop-color="#0284c7" stop-opacity="0" />
                    </linearGradient>
                  </defs>
                  
                  <!-- Area Polygon -->
                  <polygon points="0,80 0,55 80,42 160,25 240,48 320,12 400,30 400,80" fill="url(#areaGrad2)" />
                  
                  <!-- Line Path -->
                  <path d="M 0,55 L 80,42 L 160,25 L 240,48 L 320,12 L 400,30" fill="none" stroke="#0284c7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                  
                  <!-- Interactive Dots -->
                  <circle cx="0" cy="55" r="3.5" fill="#0284c7"/>
                  <circle cx="80" cy="42" r="3.5" fill="#0284c7"/>
                  <circle cx="160" cy="25" r="3.5" fill="#0284c7"/>
                  <circle cx="240" cy="48" r="3.5" fill="#0284c7"/>
                  <circle cx="320" cy="12" r="4.5" fill="#0284c7" stroke="#ffffff" stroke-width="1.5"/>
                  <circle cx="400" cy="30" r="3.5" fill="#0284c7"/>
                </svg>
              </div>
            </div>
            
            <!-- X-Axis Labels -->
            <div class="flex justify-between text-[8.5px] font-bold text-slate-400 pl-14 pt-0.5 border-t border-slate-200 font-semibold">
              <span>Kuartal 1</span>
              <span>Kuartal 2</span>
              <span>Kuartal 3</span>
              <span>Kuartal 4</span>
            </div>

            <!-- Description Text -->
            <div class="text-[9px] text-slate-500 leading-normal pt-1.5 border-t border-slate-200/60 font-medium">
              * Grafik ini mengukur akselerasi penyebutan proyek di media massa serta media sosial secara kuartalan. Kenaikan kurva mencerminkan efektivitas perluasan jangkauan publikasi komunikasi.
            </div>
          </div>
        </div>
        @endif
      </div>

      <!-- Right Column: Metrics (33%) -->
      <div class="col-span-4 flex flex-col gap-4 border-l border-outline-variant/60 pl-6 justify-start overflow-hidden">
        <!-- Stats summary by Channel/Source Type -->
        @if(empty($toggles) || !empty($toggles['statistik']))
        @php
          $portalCount = $articles->count();
          $igCount     = $socialMediaItems->filter(fn($item) => in_array(strtolower($item->platform), ['instagram', 'ig'], true))->count();
          $fbCount     = $socialMediaItems->filter(fn($item) => in_array(strtolower($item->platform), ['facebook', 'fb'], true))->count();
          $tiktokCount = $socialMediaItems->filter(fn($item) => in_array(strtolower($item->platform), ['tiktok', 'tik tok'], true))->count();
          $channelBreakdown = [
            ['label' => 'Portal', 'count' => $portalCount, 'color' => '#0284c7', 'textClass' => 'text-primary', 'borderClass' => 'border-t-primary'],
            ['label' => 'Instagram', 'count' => $igCount, 'color' => '#ec4899', 'textClass' => 'text-pink-600', 'borderClass' => 'border-t-pink-500'],
            ['label' => 'Facebook', 'count' => $fbCount, 'color' => '#2563eb', 'textClass' => 'text-blue-600', 'borderClass' => 'border-t-blue-600'],
            ['label' => 'TikTok', 'count' => $tiktokCount, 'color' => '#1e293b', 'textClass' => 'text-slate-800', 'borderClass' => 'border-t-slate-800'],
          ];
          $channelTotal = max(array_sum(array_column($channelBreakdown, 'count')), 1);
          $circ = 2 * M_PI * 14;
          $donutCirc = 2 * M_PI * 26;
          $donutOffset = 0;
        @endphp
        <div>
          <div class="flex items-center gap-1.5 mb-1 border-b border-outline-variant pb-0.5">
            <span class="material-symbols-outlined text-primary text-[14px]">bar_chart</span>
            <h2 class="font-bold text-[9px] uppercase tracking-widest text-slate-800">Ringkasan Statistik</h2>
          </div>
          <div class="grid grid-cols-2 gap-2">
            @foreach($channelBreakdown as $segment)
            <div class="glass-panel rounded-lg p-2 flex items-center justify-between border-t-4 {{ $segment['borderClass'] }} shadow-sm">
              <div class="text-left min-w-0 pr-2">
                <div class="text-[7.5px] {{ $segment['textClass'] }} uppercase tracking-wider font-bold">{{ $segment['label'] }}</div>
                <div class="text-[15px] font-black text-slate-900 leading-none mt-1">{{ number_format($segment['count'], 0, ',', '.') }}</div>
              </div>
              <svg class="w-6 h-6 shrink-0" viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="14" fill="none" stroke="#e2e8f0" stroke-width="3.5"/>
                @if($segment['count'] > 0)
                <circle cx="18" cy="18" r="14" fill="none" stroke="{{ $segment['color'] }}" stroke-width="3.5"
                        stroke-dasharray="{{ ($segment['count'] / $channelTotal) * $circ }} {{ $circ }}"
                        stroke-dashoffset="0" transform="rotate(-90 18 18)"/>
                @endif
              </svg>
            </div>
            @endforeach
          </div>
        </div>
        @endif

        <!-- Sentiment Distribution (Grafik Sentimen) -->
        @if(empty($toggles) || !empty($toggles['grafikSentimen']))
        @php
          $total_s = max($total, 1);
          $posPercent = round(($positive / $total_s) * 100);
          $neuPercent = round(($neutral / $total_s) * 100);
          $negPercent = round(($negative / $total_s) * 100);
        @endphp
        <div>
          <div class="flex items-center gap-1.5 mb-1.5 border-b border-outline-variant pb-0.5 justify-center">
            <span class="material-symbols-outlined text-primary text-[15px]">pie_chart</span>
            <h2 class="font-bold text-[9.5px] uppercase tracking-widest text-slate-800">Grafik Sentimen</h2>
          </div>
          <div class="glass-panel rounded-xl p-3 flex items-center justify-between border-glow shadow-sm bg-white">
            <div class="relative w-16 h-16 shrink-0">
              @php
                $r = 38; $cx = 50; $cy = 50;
                $circLarge = 2 * M_PI * $r;
              @endphp
              <svg viewBox="0 0 100 100" class="w-full h-full transform -rotate-90">
                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="none" stroke="#e2e8f0" stroke-width="12"/>
                @php
                  $runningOffsetSentiment = 0;
                  $sentimentSlices = [
                    ['val' => $positive, 'color' => '#059669'],
                    ['val' => $neutral,  'color' => '#ea580c'],
                    ['val' => $negative, 'color' => '#7c3aed']
                  ];
                @endphp
                @foreach($sentimentSlices as $slice)
                  @if($slice['val'] > 0)
                    @php
                      $dash = ($slice['val'] / $total_s) * $circLarge;
                    @endphp
                    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="none" stroke="{{ $slice['color'] }}" stroke-width="12"
                            stroke-dasharray="{{ $dash }} {{ $circLarge - $dash }}"
                            stroke-dashoffset="-{{ $runningOffsetSentiment }}"/>
                    @php
                      $runningOffsetSentiment += $dash;
                    @endphp
                  @endif
                @endforeach
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                <span class="text-[10px] font-black text-slate-800 leading-none">{{ $total }}</span>
                <span class="text-[5px] font-bold text-slate-400 mt-0.5">TOTAL</span>
              </div>
            </div>
            <div class="space-y-1 flex-grow pl-4 text-[10px]">
              <div class="flex items-center justify-between bg-slate-50 border border-slate-100 rounded px-2 py-0.5">
                <div class="flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-positive"></span>
                  <span class="font-medium text-slate-600">Positif</span>
                </div>
                <span class="font-extrabold text-slate-800">{{ $posPercent }}%</span>
              </div>
              <div class="flex items-center justify-between bg-slate-50 border border-slate-100 rounded px-2 py-0.5">
                <div class="flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-neutral"></span>
                  <span class="font-medium text-slate-600">Netral</span>
                </div>
                <span class="font-extrabold text-slate-800">{{ $neuPercent }}%</span>
              </div>
              <div class="flex items-center justify-between bg-slate-50 border border-slate-100 rounded px-2 py-0.5">
                <div class="flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-negative"></span>
                  <span class="font-medium text-slate-600">Negatif</span>
                </div>
                <span class="font-extrabold text-slate-800">{{ $negPercent }}%</span>
              </div>
            </div>
          </div>
        </div>
        @endif

        <!-- Keyword Analysis -->
        @if(empty($toggles) || !empty($toggles['perKataKunci']))
        <div>
          <div class="flex items-center gap-1.5 mb-1.5 border-b border-outline-variant pb-0.5">
            <span class="material-symbols-outlined text-primary text-[14px]">list_alt</span>
            <h2 class="font-bold text-[9.5px] uppercase tracking-widest text-slate-800">Analisis Kata Kunci</h2>
          </div>
          <div class="glass-panel rounded-xl p-2.5 flex flex-col items-center border-glow shadow-sm bg-white font-sans">
            @php
              $kwColors = ['#0ea5e9', '#7c3aed', '#059669', '#ea580c', '#3b82f6'];
              $topKeywords = array_slice($keywordsTable, 0, 5, true);
              $totalKw = max(array_sum($topKeywords), 1);
            @endphp
            <div class="w-full flex items-center justify-between gap-3">
              <div class="relative w-12 h-12 shrink-0">
                <svg viewBox="0 0 100 100" class="w-full h-full transform -rotate-90">
                  <circle cx="50" cy="50" r="38" fill="none" stroke="#e2e8f0" stroke-width="12"/>
                  @php
                    $runningOffsetKw = 0;
                    $circ2Kw = 2 * M_PI * 38;
                  @endphp
                  @foreach($topKeywords as $kw => $cnt)
                    @php
                      $pctKw = ($cnt / $totalKw);
                      $dashArrayKw = ($pctKw * $circ2Kw);
                      $c = $kwColors[$loop->index % count($kwColors)];
                    @endphp
                    <circle cx="50" cy="50" r="38" fill="none" stroke="{{ $c }}" stroke-width="12"
                            stroke-dasharray="{{ $dashArrayKw }} {{ $circ2Kw - $dashArrayKw }}"
                            stroke-dashoffset="-{{ $runningOffsetKw }}"/>
                    @php
                      $runningOffsetKw += $dashArrayKw;
                    @endphp
                  @endforeach
                </svg>
              </div>
              <div class="flex-grow space-y-0.5 text-[8.5px]">
                @foreach($topKeywords as $kw => $cnt)
                  @php
                    $c = $kwColors[$loop->index % count($kwColors)];
                    $pct = round(($cnt / $totalKw) * 100);
                  @endphp
                  <div class="flex items-center justify-between border-b border-slate-100 pb-0.5">
                    <div class="flex items-center gap-1">
                      <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: {{ $c }};"></span>
                      <span class="text-slate-700 font-bold break-all leading-none">#{{ $kw }}</span>
                    </div>
                    <div class="text-right shrink-0 ml-1">
                      <span class="font-extrabold text-slate-900">{{ $cnt }}</span>
                      <span class="text-slate-400 text-[7px] ml-0.5">({{ $pct }}%)</span>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          </div>
        </div>
        @endif

        <!-- Penyebutan Per Sumber -->
        @if(empty($toggles) || !empty($toggles['sumberBerita']) || !empty($toggles['sumberMedsos']))
        <div>
          <div class="flex items-center gap-1.5 mb-1.5 border-b border-outline-variant pb-0.5">
            <span class="material-symbols-outlined text-primary text-[14px]">analytics</span>
            <h2 class="font-bold text-[9.5px] uppercase tracking-widest text-slate-800">Penyebutan Per Sumber</h2>
          </div>
          <div class="glass-panel rounded-xl p-2.5 flex flex-col items-center border-glow shadow-sm bg-white font-sans">
            @php
              $srcColors = ['#0ea5e9', '#7c3aed', '#059669', '#ea580c', '#3b82f6'];
              $topSources = array_slice($sourceCounts, 0, 5, true);
              $totalSrc = max(array_sum($topSources), 1);
            @endphp
            <div class="w-full flex items-center justify-between gap-3">
              <div class="relative w-12 h-12 shrink-0">
                <svg viewBox="0 0 100 100" class="w-full h-full transform -rotate-90">
                  <circle cx="50" cy="50" r="38" fill="none" stroke="#e2e8f0" stroke-width="12"/>
                  @php
                    $runningOffsetSrc = 0;
                    $circ2Src = 2 * M_PI * 38;
                  @endphp
                  @foreach($topSources as $src => $cnt)
                    @php
                      $pctSrc = ($cnt / $totalSrc);
                      $dashArraySrc = ($pctSrc * $circ2Src);
                      $c = $srcColors[$loop->index % count($srcColors)];
                    @endphp
                    <circle cx="50" cy="50" r="38" fill="none" stroke="{{ $c }}" stroke-width="12"
                            stroke-dasharray="{{ $dashArraySrc }} {{ $circ2Src - $dashArraySrc }}"
                            stroke-dashoffset="-{{ $runningOffsetSrc }}"/>
                    @php
                      $runningOffsetSrc += $dashArraySrc;
                    @endphp
                  @endforeach
                </svg>
              </div>
              <div class="flex-grow space-y-0.5 text-[8.5px]">
                @foreach($topSources as $src => $cnt)
                  @php
                    $c = $srcColors[$loop->index % count($srcColors)];
                    $pct = round(($cnt / $totalSrc) * 100);
                  @endphp
                  <div class="flex items-center justify-between border-b border-slate-100 pb-0.5">
                    <div class="flex items-center gap-1">
                      <span class="w-1 h-1 rounded-full shrink-0" style="background-color: {{ $c }};"></span>
                      <span class="text-slate-700 font-bold break-all leading-none">{{ $src }}</span>
                    </div>
                    <div class="text-right shrink-0 ml-1">
                      <span class="font-extrabold text-slate-900 text-[8.5px]">{{ $cnt }}</span>
                      <span class="text-slate-400 text-[7px] ml-0.5">({{ $pct }}%)</span>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          </div>
        </div>
        @endif
      </div>
    </div>

    <!-- Footer -->
    <footer class="flex justify-between items-center border-t border-outline-variant pt-3 text-[10px] text-slate-400 font-bold shrink-0">
      <div>© {{ now()->format('Y') }} Arusbawah Intelligence. All rights reserved.</div>
      <div>Halaman 1 dari {{ empty($toggles) || !empty($toggles['sumberBerita']) || !empty($toggles['sumberMedsos']) || !empty($toggles['konteks']) || !empty($toggles['perKataKunci']) || !empty($toggles['beritaPopuler']) || !empty($toggles['beritaTerbaru']) ? '2' : '1' }}</div>
    </footer>
  </div>

  <!-- ==================== HALAMAN 2 (Hanya dirender jika ada komponen halaman 2 yang aktif) ==================== -->
  @if(empty($toggles) || !empty($toggles['sumberBerita']) || !empty($toggles['sumberMedsos']) || !empty($toggles['konteks']) || !empty($toggles['perKataKunci']) || !empty($toggles['beritaPopuler']) || !empty($toggles['beritaTerbaru']))
  <div class="a4-landscape flex flex-col p-10 justify-between">
    <!-- Main Content Grid -->
    <div class="grid grid-cols-12 gap-6 flex-grow overflow-hidden">
      <!-- Column 1: Sources & Viral Potential (33%) -->
      <div class="col-span-4 flex flex-col gap-5">
        <!-- Potensi Viral -->
        @if(empty($toggles) || !empty($toggles['konteks']))
        @php
          // Get portal articles with viral potential (estimated readers)
          $viralPortal = $articles->map(function($art) {
              $analysis = $art->aiAnalysisResult;
              $score = $analysis ? (int)($analysis->project_estimated_readers ?? 0) : 0;
              return [
                  'type' => 'Portal',
                  'source' => $art->source_name ?? 'Portal Berita',
                  'title' => $art->title,
                  'metric' => $score,
                  'metric_label' => number_format($score, 0, ',', '.') . ' pembaca',
                  'badge_class' => 'bg-sky-50 text-sky-700 border-sky-200/60',
              ];
          });

          // Get social media items with viral potential (like_count + share_count + comment_count)
          $viralSocial = $socialMediaItems->map(function($item) {
              $engagement = ($item->like_count ?? 0) + ($item->share_count ?? 0) + ($item->comment_count ?? 0);
              return [
                  'type' => 'Medsos',
                  'source' => ($item->platform ?? 'Medsos') . ' @' . ($item->author_name ?? 'user'),
                  'title' => $item->content ?? 'Konten Sosial',
                  'metric' => $engagement,
                  'metric_label' => number_format($engagement, 0, ',', '.') . ' interaksi',
                  'badge_class' => 'bg-slate-50 text-slate-700 border-slate-200/60',
              ];
          });

          // Combine, filter out 0 metric to show meaningful data first, then sort
          $viralList = $viralPortal->concat($viralSocial)->sortByDesc('metric')->take(3);
        @endphp
        <div>
          <div class="flex items-center gap-2 mb-3 border-b border-outline-variant pb-1.5">
            <span class="material-symbols-outlined text-amber-500 text-[18px]">local_fire_department</span>
            <h2 class="font-bold text-[11px] uppercase tracking-widest text-slate-800">Potensi Viral</h2>
          </div>
          <div class="bg-white border border-outline-variant rounded-xl p-3 flex flex-col gap-2.5 shadow-sm">
            @if($viralList->isEmpty() || $viralList->every(fn($i) => $i['metric'] == 0))
              <p class="text-slate-400 text-xs text-center py-4">Belum terdeteksi potensi viral signifikan.</p>
            @else
              @php
                $topViral = $viralList->first();
                $viralPortalCount = $viralList->where('type', 'Portal')->count();
                $viralSocialCount = $viralList->where('type', 'Medsos')->count();
                $dominantType = $viralSocialCount >= $viralPortalCount ? 'media sosial' : 'portal berita';
              @endphp

              <div class="space-y-2">
                @foreach($viralList as $item)
                  <div class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2">
                    <div class="flex items-center justify-between gap-2">
                      <span class="px-1.5 py-0.5 text-[8px] font-black rounded border {{ $item['badge_class'] }} uppercase tracking-wider font-semibold shrink-0">{{ $item['type'] }}</span>
                      <span class="text-[8px] font-bold text-slate-500 text-right">{{ $item['source'] }}</span>
                    </div>
                    <p class="mt-1 text-[9px] font-bold text-slate-800 leading-snug break-words whitespace-normal">{{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $item['title']))), 105) }}</p>
                    <div class="mt-1 flex items-center gap-1 text-[8px] font-black text-amber-600">
                      <span class="material-symbols-outlined text-[10px]">trending_up</span>
                      <span>{{ $item['metric_label'] }}</span>
                    </div>
                  </div>
                @endforeach
              </div>
            @endif
          </div>
        </div>
        @endif

        <!-- 5 Besar Medsos Negatif -->
        @if(empty($toggles) || !empty($toggles['beritaPopuler']))
        <div>
          <div class="flex items-center gap-2 mb-3 border-b border-outline-variant pb-1.5">
            <span class="material-symbols-outlined text-[#ef4444] text-[18px]">thumb_down</span>
            <h2 class="font-bold text-[11px] uppercase tracking-widest text-slate-800">5 Besar Medsos Negatif</h2>
          </div>
          <div class="border border-outline-variant rounded-xl overflow-hidden shadow-sm bg-white">
            <table class="w-full text-left text-[11px] table-fixed">
              <thead class="bg-slate-50 border-b border-outline-variant">
                <tr>
                  <th class="w-1/3 px-3 py-2 font-bold text-slate-700 uppercase text-[9px]">Platform</th>
                  <th class="w-1/3 px-3 py-2 font-bold text-slate-700 uppercase text-[9px]">Akun/User</th>
                  <th class="w-1/3 px-3 py-2 font-bold text-slate-700 uppercase text-[9px] text-right">Sentimen</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                @php
                  $negativeMedsos = $socialMediaItems->filter(function($item) {
                      $sentiment = optional($item->aiAnalysisResult)->sentiment ?? $item->sentiment;
                      return strtolower((string) $sentiment) === 'negative';
                  })->sortByDesc('created_at')->take(5);
                @endphp
                @if($negativeMedsos->isEmpty())
                  <tr><td colspan="3" class="px-3 py-3 text-center text-slate-400">Tidak ada konten medsos negatif.</td></tr>
                @else
                  @foreach($negativeMedsos as $item)
                    <tr class="hover:bg-slate-50/50 transition">
                      <td class="px-3 py-2.5 font-bold text-slate-800 break-words whitespace-normal">{{ $item->platform }}</td>
                      <td class="px-3 py-2.5 text-slate-600 font-medium break-words whitespace-normal">{{ $item->author_name ?? 'User' }}</td>
                      <td class="px-3 py-2.5 text-right shrink-0">
                        <span class="inline-block px-2 py-0.5 text-[9px] font-bold rounded-lg border bg-rose-50 text-rose-700 border-rose-200/60 font-sans">Negatif</span>
                      </td>
                    </tr>
                  @endforeach
                @endif
              </tbody>
            </table>
          </div>
        </div>
        @endif
      </div>

      <!-- Column 2: 5 Portal Negatif (33%) -->
      <div class="col-span-4 flex flex-col gap-5 border-l border-r border-outline-variant/60 px-6">
        <!-- Top 5 Negative Portal Articles -->
        @if(empty($toggles) || !empty($toggles['sumberMedsos']))
        <div>
          <div class="flex items-center gap-2 mb-3 border-b border-outline-variant pb-1.5">
            <span class="material-symbols-outlined text-[#ef4444] text-[18px]">thumb_down</span>
            <h2 class="font-bold text-[11px] uppercase tracking-widest text-slate-800">5 Portal Negatif</h2>
          </div>
          <div class="border border-outline-variant rounded-xl overflow-hidden shadow-sm bg-white">
            <table class="w-full text-left text-[10px] table-fixed">
              <thead class="bg-slate-50 border-b border-outline-variant">
                <tr>
                  <th class="w-[52%] px-3 py-2 font-bold text-slate-700 uppercase text-[8px]">Judul</th>
                  <th class="w-[28%] px-3 py-2 font-bold text-slate-700 uppercase text-[8px]">Portal</th>
                  <th class="w-[20%] px-3 py-2 font-bold text-slate-700 uppercase text-[8px] text-right">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                @php
                  // Filter strictly portal/news articles ($articles) with negative sentiment
                  $negativeArticles = $articles->filter(function($art) {
                      return optional($art->aiAnalysisResult)->sentiment === 'negative' || $art->sentiment === 'negative';
                  })->sortByDesc('published_at')->take(5);
                @endphp
                @if($negativeArticles->isEmpty())
                  <tr><td colspan="3" class="px-3 py-3 text-center text-slate-400">Tidak ada berita portal negatif.</td></tr>
                @else
                  @foreach($negativeArticles as $art)
                    <tr class="hover:bg-slate-50/50 transition">
                      <td class="px-3 py-2.5 font-bold text-[9px] text-slate-800 leading-snug break-words whitespace-normal">{{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $art->title))), 88) }}</td>
                      <td class="px-3 py-2.5 text-slate-600 font-medium break-words whitespace-normal">{{ \Illuminate\Support\Str::limit((string) ($art->source_name ?? 'Portal'), 18) }}</td>
                      <td class="px-3 py-2.5 text-right shrink-0">
                        <span class="inline-block px-1.5 py-0.5 text-[8px] font-bold rounded-lg border bg-rose-50 text-rose-700 border-rose-200/60">Negatif</span>
                      </td>
                    </tr>
                  @endforeach
                @endif
              </tbody>
            </table>
          </div>
        </div>
        @endif
      </div>

      <!-- Column 3: 5 Penyebutan Terpopuler (33%) -->
      <div class="col-span-4 flex flex-col gap-5 pl-6">
        <!-- Donut Komposisi Negatif per Kanal -->
        @if(empty($toggles) || !empty($toggles['grafikSentimen']))
        @php
          $negativePortalCount = $articles->filter(function ($art) {
              return optional($art->aiAnalysisResult)->sentiment === 'negative' || $art->sentiment === 'negative';
          })->count();

          $negativeInstagramCount = $socialMediaItems->filter(function ($item) {
              $sentiment = optional($item->aiAnalysisResult)->sentiment ?? $item->sentiment;
              return strtolower((string) $sentiment) === 'negative'
                  && in_array(strtolower((string) $item->platform), ['instagram', 'ig'], true);
          })->count();

          $negativeFacebookCount = $socialMediaItems->filter(function ($item) {
              $sentiment = optional($item->aiAnalysisResult)->sentiment ?? $item->sentiment;
              return strtolower((string) $sentiment) === 'negative'
                  && in_array(strtolower((string) $item->platform), ['facebook', 'fb'], true);
          })->count();

          $negativeTiktokCount = $socialMediaItems->filter(function ($item) {
              $sentiment = optional($item->aiAnalysisResult)->sentiment ?? $item->sentiment;
              return strtolower((string) $sentiment) === 'negative'
                  && in_array(strtolower((string) $item->platform), ['tiktok', 'tik tok'], true);
          })->count();

          $negativeChannelBreakdown = [
            ['label' => 'Portal', 'count' => $negativePortalCount, 'color' => '#0284c7'],
            ['label' => 'Instagram', 'count' => $negativeInstagramCount, 'color' => '#ec4899'],
            ['label' => 'Facebook', 'count' => $negativeFacebookCount, 'color' => '#2563eb'],
            ['label' => 'TikTok', 'count' => $negativeTiktokCount, 'color' => '#1e293b'],
          ];
          $negativeChannelTotal = max(array_sum(array_column($negativeChannelBreakdown, 'count')), 1);
          $negativeDonutCirc = 2 * M_PI * 28;
          $negativeDonutOffset = 0;
        @endphp
        <div>
          <div class="flex items-center gap-2 mb-3 border-b border-outline-variant pb-1.5">
            <span class="material-symbols-outlined text-rose-500 text-[18px]">donut_large</span>
            <h2 class="font-bold text-[11px] uppercase tracking-widest text-slate-800">Negatif per Kanal</h2>
          </div>
          <div class="border border-outline-variant rounded-xl shadow-sm bg-white p-3">
            <div class="flex gap-3 items-start">
              <div class="relative w-24 h-24 shrink-0 mx-auto">
                <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                  <circle cx="50" cy="50" r="28" fill="none" stroke="#e2e8f0" stroke-width="14"/>
                  @foreach($negativeChannelBreakdown as $segment)
                    @if($segment['count'] > 0)
                      @php
                        $dash = ($segment['count'] / $negativeChannelTotal) * $negativeDonutCirc;
                      @endphp
                      <circle
                        cx="50"
                        cy="50"
                        r="28"
                        fill="none"
                        stroke="{{ $segment['color'] }}"
                        stroke-width="14"
                        stroke-dasharray="{{ $dash }} {{ $negativeDonutCirc - $dash }}"
                        stroke-dashoffset="-{{ $negativeDonutOffset }}"
                      />
                      @php $negativeDonutOffset += $dash; @endphp
                    @endif
                  @endforeach
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                  <span class="text-[10px] font-black text-slate-900 leading-none">{{ number_format($negativeChannelTotal, 0, ',', '.') }}</span>
                  <span class="text-[4px] font-bold uppercase tracking-[0.16em] text-slate-400 mt-1">NEGATIF</span>
                </div>
              </div>

              <div class="flex-1 space-y-1.5">
                @foreach($negativeChannelBreakdown as $segment)
                  @php
                    $pct = (int) round(($segment['count'] / $negativeChannelTotal) * 100);
                  @endphp
                  <div class="border-b border-slate-100 pb-1 last:border-0 last:pb-0">
                    <div class="flex items-center justify-between gap-2 text-[8.5px]">
                      <div class="flex items-center gap-2 min-w-0">
                        <span class="w-2 h-2 rounded-full shrink-0" style="background: {{ $segment['color'] }}"></span>
                        <span class="font-bold text-slate-800">{{ $segment['label'] }}</span>
                      </div>
                      <div class="text-right shrink-0">
                        <span class="font-black text-slate-900">{{ number_format($segment['count'], 0, ',', '.') }}</span>
                        <span class="text-slate-400 ml-1">{{ $pct }}%</span>
                      </div>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          </div>
        </div>
        @endif

        <!-- 5 Penyebutan Terpopuler -->
        @if(empty($toggles) || !empty($toggles['beritaTerbaru']))
        <div>
          <div class="flex items-center gap-2 mb-3 border-b border-outline-variant pb-1.5">
            <span class="material-symbols-outlined text-primary text-[18px]">trending_up</span>
            <h2 class="font-bold text-[11px] uppercase tracking-widest text-slate-800">5 Terpopuler</h2>
          </div>
          <div class="border border-outline-variant rounded-xl shadow-sm bg-white p-3">
            @php
              $topReachList = $topReachArticles->take(5)->map(function ($art) {
                  $analysis = $art->aiAnalysisResult;
                  $reach = $analysis ? (int) ($analysis->officialArticleEstimatedReaders() ?? 0) : 0;

                  return [
                      'title' => trim(preg_replace('/\s+/', ' ', strip_tags((string) $art->title))),
                      'reach' => $reach,
                  ];
              })->values();

              $reachTotal = max($topReachList->sum('reach'), 1);
              $reachColors = ['#0284c7', '#2563eb', '#7c3aed', '#ec4899', '#f59e0b'];
              $reachCirc = 2 * M_PI * 28;
              $reachOffset = 0;
            @endphp

            @if($topReachList->isEmpty())
              <div class="py-6 text-center text-slate-400 text-xs">Tidak ada data.</div>
            @else
              <div class="flex gap-3 items-start">
                <div class="relative w-24 h-24 shrink-0 mx-auto">
                  <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                    <circle cx="50" cy="50" r="28" fill="none" stroke="#e2e8f0" stroke-width="14"/>
                    @foreach($topReachList as $index => $item)
                      @if($item['reach'] > 0)
                        @php
                          $dash = ($item['reach'] / $reachTotal) * $reachCirc;
                          $color = $reachColors[$index] ?? '#94a3b8';
                        @endphp
                        <circle
                          cx="50"
                          cy="50"
                          r="28"
                          fill="none"
                          stroke="{{ $color }}"
                          stroke-width="14"
                          stroke-dasharray="{{ $dash }} {{ $reachCirc - $dash }}"
                          stroke-dashoffset="-{{ $reachOffset }}"
                        />
                        @php $reachOffset += $dash; @endphp
                      @endif
                    @endforeach
                  </svg>
                  <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span class="text-[10px] font-black text-slate-900 leading-none">{{ number_format($reachTotal, 0, ',', '.') }}</span>
                    <span class="text-[4px] font-bold uppercase tracking-[0.16em] text-slate-400 mt-1">JANGKAUAN</span>
                  </div>
                </div>

                <div class="flex-1 space-y-1.5">
                  @foreach($topReachList as $index => $item)
                    @php
                      $color = $reachColors[$index] ?? '#94a3b8';
                      $pct = (int) round(($item['reach'] / $reachTotal) * 100);
                    @endphp
                    <div class="border-b border-slate-100 pb-1 last:border-0 last:pb-0">
                      <div class="flex items-start gap-2">
                        <span class="w-2 h-2 rounded-full shrink-0 mt-1" style="background: {{ $color }}"></span>
                        <div class="min-w-0 flex-1">
                          <div class="text-[8.5px] font-bold text-slate-800 leading-snug break-words whitespace-normal">
                            {{ \Illuminate\Support\Str::limit($item['title'], 50) }}
                          </div>
                          <div class="mt-0.5 flex items-center justify-between text-[8px]">
                            <span class="font-black text-primary">{{ number_format($item['reach'], 0, ',', '.') }}</span>
                            <span class="text-slate-400">{{ $pct }}%</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            @endif
          </div>
        </div>
        @endif
      </div>
    </div>

    <!-- Footer -->
    <footer class="flex justify-between items-center border-t border-outline-variant pt-4 text-[10px] text-slate-400 font-bold shrink-0">
      <div>© {{ now()->format('Y') }} Intelligence Systems Division</div>
      <div>Halaman 2 dari 2</div>
    </footer>
  </div>
  @endif

</body>
</html>
