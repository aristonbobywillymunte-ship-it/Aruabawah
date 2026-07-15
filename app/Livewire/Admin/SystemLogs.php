<?php

namespace App\Livewire\Admin;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Livewire\Component;

class SystemLogs extends Component
{
    public array $logFiles = [
        'all' => 'Semua Log Terbaru',
        'laravel.log' => 'Log Umum Laravel (laravel.log)',
        'laravel-queue.log' => 'Log Worker Antrean (laravel-queue.log)',
        'portal-manual.log' => 'Log Portal Manual (portal-manual.log)',
        'google-news.log' => 'Log Google News (google-news.log)',
        'social-media.log' => 'Log Sosial Media (social-media.log)',
        'telegram.log' => 'Log Telegram (telegram.log)',
        'news-scraping.log' => 'Log Scraping Berita (news-scraping.log)',
        'scraping-scheduler.log' => 'Log Scheduler Antrean (scraping-scheduler.log)',
        'ai-backfill-scheduler.log' => 'Log Backfill Scheduler (ai-backfill-scheduler.log)',
        'ai-health-check-scheduler.log' => 'Log AI Health Scheduler (ai-health-check-scheduler.log)',
        'ai-requeue-overdue.log' => 'Log Antrean Ulang AI (ai-requeue-overdue.log)',
    ];

    public array $statusOptions = [
        'all' => 'Semua Status',
        'success' => 'Sukses',
        'failed' => 'Gagal',
        'retry' => 'Retry',
        'started' => 'Mulai',
        'processing' => 'Berjalan',
        'blocked' => 'Diblok',
    ];

    public array $sourceOptions = [
        'all' => 'Semua Sumber',
        'telegram' => 'Telegram',
        'apify' => 'Apify',
        'social_media' => 'Sosial Media',
        'portal_manual' => 'Portal Manual',
        'google_news' => 'Google News',
        'ai' => 'AI',
        'scheduler' => 'Scheduler',
        'system' => 'Sistem',
    ];

    public string $selectedFile = 'all';
    public string $statusFilter = 'all';
    public string $sourceFilter = 'all';
    public string $projectFilter = 'all';
    public string $keywordFilter = '';
    public string $errorCodeFilter = '';
    public string $searchTerm = '';
    public int $maxLines = 200;
    public ?string $flashMessage = null;
    public string $flashType = 'success';
    public array $projectOptions = [];

    protected function adminOnly(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function mount(): void
    {
        $this->adminOnly();
        $this->loadDynamicLogFiles();
        $this->projectOptions = Project::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Project $project) => [
                'id' => (int) $project->id,
                'name' => $project->name,
            ])
            ->all();
    }

    protected function loadDynamicLogFiles(): void
    {
        $logPath = storage_path('logs');
        if (!File::exists($logPath)) {
            return;
        }

        $files = File::files($logPath);
        $dynamicLogFiles = [
            'all' => 'Semua Log Terbaru',
        ];

        $priorityFiles = [
            'laravel.log' => 'Log Umum Laravel',
            'telegram.log' => 'Log Telegram',
            'portal-manual.log' => 'Log Portal Manual',
            'google-news.log' => 'Log Google News',
            'social-media.log' => 'Log Sosial Media',
            'laravel-queue.log' => 'Log Worker Antrean',
            'news-scraping.log' => 'Log Scraping Berita',
            'scraping-scheduler.log' => 'Log Scheduler Antrean',
            'ai-backfill-scheduler.log' => 'Log Backfill Scheduler',
            'ai-health-check-scheduler.log' => 'Log AI Health Scheduler',
            'ai-requeue-overdue.log' => 'Log Antrean Ulang AI',
        ];

        foreach ($priorityFiles as $filename => $label) {
            if (File::exists($logPath . DIRECTORY_SEPARATOR . $filename)) {
                $dynamicLogFiles[$filename] = $label . ' (' . $filename . ')';
            }
        }

        foreach ($files as $file) {
            $filename = $file->getFilename();
            if (!str_ends_with($filename, '.log') || isset($dynamicLogFiles[$filename])) {
                continue;
            }

            $cleanName = ucwords(str_replace(['-', '_'], ' ', basename($filename, '.log')));
            $dynamicLogFiles[$filename] = 'Log ' . $cleanName . ' (' . $filename . ')';
        }

        $this->logFiles = $dynamicLogFiles;
    }

    public function clearLog(): void
    {
        $this->adminOnly();

        if ($this->selectedFile === 'all') {
            $this->notify('error', 'Pilih satu file log dulu untuk dibersihkan.');
            return;
        }

        if (!array_key_exists($this->selectedFile, $this->logFiles)) {
            $this->notify('error', 'File log tidak valid.');
            return;
        }

        $path = storage_path('logs/' . $this->selectedFile);

        if (File::exists($path)) {
            File::put($path, '');

            \App\Models\ScrapingItem::whereNotNull('error_message')->update(['error_message' => null]);
            \App\Models\AiProvider::whereNotNull('last_error')->update(['last_error' => null]);

            $this->notify('success', "File {$this->selectedFile} dan log database berhasil dibersihkan.");
        } else {
            $this->notify('error', 'File log tidak ditemukan.');
        }
    }

    protected function notify(string $type, string $message): void
    {
        $this->flashType = $type;
        $this->flashMessage = $message;
        $payload = [
            'type' => $type,
            'title' => $message,
            'message' => '',
        ];

        if (method_exists($this, 'dispatchBrowserEvent')) {
            $this->dispatchBrowserEvent('admin-toast', $payload);
        }

        $this->dispatch('admin-toast', payload: $payload);
    }

    public function getLogsProperty(): array
    {
        $this->adminOnly();

        $files = $this->selectedFile === 'all'
            ? array_keys(array_filter($this->logFiles, static fn ($label, $file) => $file !== 'all', ARRAY_FILTER_USE_BOTH))
            : [$this->selectedFile];

        $entries = [];
        foreach ($files as $file) {
            $entries = array_merge($entries, $this->readLogEntries($file));
        }

        usort($entries, static function (array $a, array $b): int {
            $aTs = $a['timestamp_sort'] ?? 0;
            $bTs = $b['timestamp_sort'] ?? 0;
            if ($aTs === $bTs) {
                return 0;
            }

            return $aTs < $bTs ? 1 : -1;
        });

        $entries = array_filter($entries, function (array $entry): bool {
            if ($this->statusFilter !== 'all' && $entry['status_key'] !== $this->statusFilter) {
                return false;
            }

            if ($this->sourceFilter !== 'all' && $entry['source_key'] !== $this->sourceFilter) {
                return false;
            }

            if ($this->projectFilter !== 'all') {
                $selectedProjectId = (int) $this->projectFilter;
                $entryProjectId = (int) ($entry['project_id'] ?? 0);
                if ($entryProjectId !== $selectedProjectId) {
                    return false;
                }
            }

            if (filled($this->keywordFilter)) {
                $needle = strtolower(trim($this->keywordFilter));
                $haystack = strtolower((string) ($entry['keyword_label'] ?? '') . ' ' . $entry['raw_line']);
                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }

            if (filled($this->errorCodeFilter)) {
                $needle = strtolower(trim($this->errorCodeFilter));
                $haystack = strtolower((string) ($entry['error_code'] ?? '') . ' ' . $entry['raw_line']);
                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }

            if (filled($this->searchTerm)) {
                $needle = strtolower(trim($this->searchTerm));
                $haystack = strtolower(implode(' ', [
                    $entry['timestamp_label'],
                    $entry['source_label'],
                    $entry['project_label'],
                    $entry['status_label'],
                    $entry['error_code'],
                    $entry['message'],
                    $entry['raw_line'],
                ]));
                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }

            return true;
        });

        return array_values($entries);
    }

    protected function readLogEntries(string $file): array
    {
        if (!array_key_exists($file, $this->logFiles)) {
            return [];
        }

        $path = storage_path('logs/' . $file);
        if (!File::exists($path) || File::size($path) === 0) {
            return [];
        }

        $lines = $this->tailLines($path, $this->maxLines);
        $entries = [];

        foreach ($lines as $line) {
            $entry = $this->parseLogLine($line, $file);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    protected function tailLines(string $path, int $maxLines): array
    {
        $maxLines = max(1, $maxLines);
        $lines = [];
        $handle = fopen($path, 'r');

        if (! $handle) {
            return [];
        }

        $lineCount = 0;
        $buffer = '';
        $pos = -2;

        while ($lineCount < $maxLines && fseek($handle, $pos, SEEK_END) !== -1) {
            $char = fgetc($handle);
            if ($char === "\n") {
                if (trim($buffer) !== '') {
                    $lines[] = strrev($buffer);
                    $lineCount++;
                }
                $buffer = '';
            } else {
                $buffer .= $char;
            }
            $pos--;
        }

        if (trim($buffer) !== '' && $lineCount < $maxLines) {
            $lines[] = strrev($buffer);
        }

        fclose($handle);

        return array_reverse($lines);
    }

    protected function parseLogLine(string $line, string $file): ?array
    {
        $rawLine = trim($line);
        if ($rawLine === '') {
            return null;
        }

        $timestampLabel = null;
        $timestampSort = 0;
        $level = 'info';
        $message = $rawLine;

        if (preg_match('/^\[(?<timestamp>[^\]]+)\]\s+(?:[^\.\:]+\.)?(?<level>[A-Z]+):\s+(?<message>.*)$/', $rawLine, $matches)) {
            $timestampLabel = $matches['timestamp'];
            $level = strtolower($matches['level']);
            $message = trim($matches['message']);

            try {
                $timestampSort = Carbon::parse($timestampLabel)->timestamp;
            } catch (\Throwable $e) {
                $timestampSort = File::lastModified(storage_path('logs/' . $file));
            }
        }

        $context = $this->extractContext($message);
        $message = $this->stripContextSuffix($message);

        $sourceKey = $this->inferSourceKey($file, $message, $context);
        $statusKey = $this->inferStatusKey($level, $message, $context);
        $errorCode = $this->inferErrorCode($message, $context);
        $projectLabel = $this->inferProjectLabel($message, $context);
        $keywordLabel = $this->inferKeywordLabel($message, $context);
        $messageShort = $this->shortenMessage($message, 180);
        $translatedMessage = $this->translateMessageToIndonesian($messageShort);

        return [
            'timestamp_sort' => $timestampSort,
            'timestamp_label' => $timestampLabel ?? '-',
            'file' => $file,
            'file_label' => $this->logFiles[$file] ?? $file,
            'source_key' => $sourceKey,
            'source_label' => $this->sourceOptions[$sourceKey] ?? ucfirst(str_replace('_', ' ', $sourceKey)),
            'status_key' => $statusKey,
            'status_label' => $this->statusOptions[$statusKey] ?? ucfirst($statusKey),
            'error_code' => $errorCode,
            'project_id' => $context['project_id'] ?? null,
            'project_label' => $projectLabel,
            'keyword_label' => $keywordLabel,
            'message' => $translatedMessage,
            'raw_line' => $rawLine,
            'level' => $level,
        ];
    }

    protected function translateMessageToIndonesian(string $message): string
    {
        $mappings = [
            'Fallback stage finished.' => 'Tahap cadangan selesai.',
            'Exit signal received. Terminating process for restart.' => 'Sinyal keluar diterima. Menghentikan proses untuk memulai ulang.',
            'Exit signal received. Terminating scheduler process.' => 'Sinyal keluar diterima. Menghentikan proses scheduler.',
            'Failed to create dispatch state:' => 'Gagal membuat status pengiriman:',
            'Calling actor' => 'Memanggil aktor Apify',
            'Run started:' => 'Proses pemindaian dimulai:',
            'Run status:' => 'Status pemindaian:',
            'Actor run did not succeed.' => 'Eksekusi aktor Apify tidak berhasil.',
            'Failed to fetch dataset' => 'Gagal mengambil data dataset',
            'Processing:' => 'Memproses:',
            'Processed:' => 'Selesai diproses:',
            'Failed:' => 'Gagal:',
            'Command skipped because settings are not ready.' => 'Perintah dilewati karena konfigurasi belum siap.',
            'Scraping candidate article details.' => 'Mengambil rincian detail artikel kandidat.',
            'Gagal mengambil konten penuh untuk URL:' => 'Gagal mengambil konten penuh untuk URL:',
            'No projects found.' => 'Tidak ada proyek yang ditemukan.',
            'No active Apify actors found.' => 'Tidak ada aktor Apify aktif yang ditemukan.',
            'Command skipped because settings are not ready.' => 'Perintah dilewati karena konfigurasi belum siap.',
            'AI Provider Test connection succeeded.' => 'Uji koneksi Penyedia AI berhasil.',
            'AI Provider Test connection failed.' => 'Uji koneksi Penyedia AI gagal.',
            'Telegram notification delivered successfully.' => 'Notifikasi Telegram berhasil dikirim.',
            'Telegram test send failed' => 'Uji kirim Telegram gagal.',
            'AiAnalysisJob analyzing item type:' => 'Pekerjaan AiAnalysis menganalisis tipe item:',
            'AI analysis result completed' => 'Hasil analisis AI selesai',
            'Success using provider:' => 'Sukses menggunakan penyedia AI:',
            '[Social] Project scan started.' => '[Sosial] Pemindaian proyek dimulai.',
            '[Social] Run started.' => '[Sosial] Pemindaian media sosial dimulai.',
            '[Social] Actor payload prepared.' => '[Sosial] Payload aktor siap dikirim.',
            '[Social] Actor run started.' => '[Sosial] Proses aktor Apify dimulai.',
            '[Social] Project scan completed.' => '[Sosial] Pemindaian proyek selesai.',
            '[Social] Project scan finished.' => '[Sosial] Pemindaian proyek selesai.',
            '[Social] Actor skipped: last run failed, cooldown applied.' => '[Sosial] Aktor dilewati: eksekusi terakhir gagal, masa tunggu (cooldown) diterapkan.',
            '[Social] Actor skipped: waiting recovery window.' => '[Sosial] Aktor dilewati: menunggu jendela pemulihan.',
            '[Social] Run finished.' => '[Sosial] Pemindaian media sosial selesai.',
            '[Pipeline] TelegramNotificationJob started' => '[Notifikasi] Pengiriman notifikasi dimulai.',
            '[Pipeline] Telegram notification skipped: Bot credentials are invalid or incomplete.' => '[Notifikasi] Pengiriman dilewati: Kredensial bot tidak valid.',
            '[Pipeline] Telegram notification skipped: No recipients found.' => '[Notifikasi] Pengiriman dilewati: Penerima tidak ditemukan.',
            'Already sent previously.' => 'Sudah terkirim sebelumnya.',
            'In 3-minute failure cooldown.' => 'Dalam masa jeda pemulihan gagal 3 menit.',
            'Waiting 1 minute (60 seconds) delay before next send...' => 'Menunggu jeda 1 menit sebelum mengirim ke penerima berikutnya...',
            'Sending alert message to Telegram Chat ID:' => 'Mengirim pesan peringatan ke ID Telegram:',
            'Telegram notification delivered successfully to' => 'Notifikasi Telegram berhasil dikirim ke',
            'Telegram notification failed for recipient' => 'Pengiriman notifikasi Telegram gagal untuk penerima',
            'deactivated permanently due to error:' => 'dinonaktifkan secara permanen karena kesalahan.',
            'placed on a 3-minute cooldown due to temporary error.' => 'dimasukkan ke masa jeda pemulihan gagal 3 menit karena kesalahan sementara.',
            '[Pipeline] Telegram notification failed' => '[Notifikasi] Pengiriman notifikasi gagal.',
        ];

        foreach ($mappings as $eng => $indo) {
            if (str_contains($message, $eng)) {
                return str_replace($eng, $indo, $message);
            }
        }

        return $message;
    }

    protected function extractContext(string $message): array
    {
        if (!preg_match('/(\{.*\})\s*$/', $message, $matches)) {
            return [];
        }

        $decoded = json_decode($matches[1], true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function stripContextSuffix(string $message): string
    {
        return trim((string) preg_replace('/\s+\{.*\}\s*$/', '', $message));
    }

    protected function inferSourceKey(string $file, string $message, array $context): string
    {
        $haystack = strtolower($file . ' ' . $message . ' ' . json_encode($context));

        return match (true) {
            str_contains($haystack, 'telegram') => 'telegram',
            str_contains($haystack, 'social-media') || str_contains($haystack, '[social]') => 'social_media',
            str_contains($haystack, 'google-news') || str_contains($haystack, '[googlenews]') || str_contains($haystack, 'google news') => 'google_news',
            str_contains($haystack, 'portal-manual') || str_contains($haystack, '[portal]') || str_contains($haystack, '[newsportal]') => 'portal_manual',
            str_contains($haystack, 'apify') => 'apify',
            str_contains($haystack, 'ai-backfill') || str_contains($haystack, 'ai-analysis') || str_contains($haystack, '[ai]') || str_contains($haystack, 'aianalysisjob') || str_contains($haystack, 'backfillarticle') => 'ai',
            str_contains($haystack, 'scheduler') => 'scheduler',
            default => 'system',
        };
    }

    protected function inferStatusKey(string $level, string $message, array $context): string
    {
        $haystack = strtolower($level . ' ' . $message . ' ' . json_encode($context));

        if (in_array($level, ['error', 'critical', 'alert', 'emergency'], true)) {
            return 'failed';
        }

        if ($this->contextHasPositiveErrorCount($context)) {
            return 'failed';
        }

        if (str_contains($haystack, 'success') || str_contains($haystack, 'sukses') || str_contains($haystack, 'done') || str_contains($haystack, 'terkirim') || str_contains($haystack, 'completed')) {
            return 'success';
        }

        if (str_contains($haystack, 'retry') || str_contains($haystack, 'menunggu') || str_contains($haystack, 'delayed')) {
            return 'retry';
        }

        if (str_contains($haystack, 'started')) {
            return 'started';
        }

        if (str_contains($haystack, 'processing') || str_contains($haystack, 'running')) {
            return 'processing';
        }

        if (str_contains($haystack, 'blocked') || str_contains($haystack, 'diblok') || str_contains($haystack, 'hard limit') || str_contains($haystack, 'platform-feature-disabled')) {
            return 'blocked';
        }

        if (str_contains($haystack, 'failed') || str_contains($haystack, 'gagal') || str_contains($haystack, 'abort') || str_contains($haystack, 'exception')) {
            return 'failed';
        }

        return 'success';
    }

    protected function contextHasPositiveErrorCount(array $context): bool
    {
        foreach (['error', 'errors', 'error_count', 'failed', 'failed_count'] as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }

            if (is_numeric($context[$key]) && (int) $context[$key] > 0) {
                return true;
            }
        }

        return false;
    }

    protected function inferErrorCode(string $message, array $context): string
    {
        $haystack = strtolower($message . ' ' . json_encode($context));

        return match (true) {
            str_contains($haystack, '401') => '401 unauthorized',
            str_contains($haystack, '403') => '403 forbidden',
            str_contains($haystack, '429') => '429 rate limit',
            str_contains($haystack, 'timeout') => 'timeout',
            str_contains($haystack, 'aborted') => 'aborted',
            str_contains($haystack, 'platform-feature-disabled') => 'platform-feature-disabled',
            str_contains($haystack, 'monthly usage hard limit exceeded') => 'monthly limit',
            str_contains($haystack, 'connection refused') => 'connection refused',
            str_contains($haystack, 'connection timed out') => 'connection timed out',
            default => '-',
        };
    }

    protected function inferProjectLabel(string $message, array $context): string
    {
        if (!empty($context['project_name'])) {
            return (string) $context['project_name'];
        }

        if (!empty($context['project_id'])) {
            return 'Project #' . $context['project_id'];
        }

        // Try parsing from message text: "Project: {Name} (ID: {Id})" or "Project: {Name}"
        if (preg_match('/Project:\s*(?<name>[^\(\,]+?)\s*\(ID:\s*(?<id>\d+)\)/i', $message, $matches)) {
            return trim($matches['name']);
        }
        if (preg_match('/Project:\s*(?<name>[^\,]+)/i', $message, $matches)) {
            return trim($matches['name']);
        }
        if (preg_match('/project_id:\s*(?<id>\d+)/i', $message, $matches)) {
            return 'Project #' . $matches['id'];
        }

        return '-';
    }

    protected function inferKeywordLabel(string $message, array $context): string
    {
        if (!empty($context['keyword'])) {
            return (string) $context['keyword'];
        }

        if (!empty($context['keywords']) && is_array($context['keywords'])) {
            return implode(', ', array_map('strval', $context['keywords']));
        }

        // Try parsing from message text: "Keyword: {Keyword}" or "keyword \"{Keyword}\""
        if (preg_match('/Keyword:\s*(?<keyword>[^\.\,]+)/i', $message, $matches)) {
            return trim($matches['keyword']);
        }
        if (preg_match('/keyword\s+"(?<keyword>[^"]+)"/i', $message, $matches)) {
            return trim($matches['keyword']);
        }

        return '-';
    }

    protected function shortenMessage(string $message, int $limit = 180): string
    {
        $message = trim($message);

        if (mb_strlen($message) <= $limit) {
            return $message;
        }

        return rtrim(mb_substr($message, 0, $limit - 1)) . '…';
    }

    public function render()
    {
        $this->adminOnly();

        return view('livewire.admin.system-logs', [
            'logs' => $this->logs,
        ]);
    }

    public function dehydrate(): void
    {
        $this->flashMessage = null;
    }
}
