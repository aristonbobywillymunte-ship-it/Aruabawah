<?php

use Livewire\Component;
use App\Models\Project;
use App\Models\Article;
use App\Models\AiAnalysisResult;
use App\Models\ApifyActor;
use App\Jobs\ApifyScrapingJob;
use App\Jobs\BootstrapNewProjectScrapingJob;
use App\Services\ContentMatchingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;

new class extends Component
{
    #[Url(as: 'project')]
    public $projectId;

    public function getDecodedProjectId()
    {
        if (is_numeric($this->projectId)) {
            return (int) $this->projectId;
        }
        $decoded = base64_decode($this->projectId, true);
        if ($decoded !== false && is_numeric($decoded)) {
            return (int) $decoded;
        }
        return $this->projectId;
    }


    // Projects lazy-load state
    public bool $projectsLoaded = false;
    public array $projects = [];

    // Edit project state
    public $showEditModal = false;
    public $editProjectId = null;
    public $editName = '';
    public $editTopicsString = '';
    public $showConfirmModal = false;
    public $confirmAction = null;
    public $confirmProjectId = null;
    public $confirmProjectName = '';
    public $confirmTitle = '';
    public $confirmMessage = '';
    public $toastType = null;
    public $toastMessage = '';
    public $showTrashedModal = false;
    protected ?array $portalScanTimes = null;
    protected ?array $portalRunningProjectIds = null;
    protected ?array $socialActiveProjects = null;

    protected function projectsCacheKey(): string
    {
        $userId = (int) (auth()->id() ?? 0);
        return 'projects_list:' . $userId . ':' . md5(json_encode([
            'project' => $this->getDecodedProjectId(),
            'user'    => $userId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected function forgetProjectsCache(): void
    {
        Cache::forget($this->projectsCacheKey());
    }

    protected function parseMultiChatIds(string $value): array
    {
        $normalized = str_replace([';', ' '], ',', $value);
        $items = array_map('trim', explode(',', $normalized));
        $items = array_map(fn($item) => ltrim($item, '-'), $items);
        $items = array_filter($items);
        return array_values(array_unique($items));
    }

    public function mount(): void
    {
        $this->projectId = $this->resolveProjectOrDefault($this->getDecodedProjectId());
    }

    public function updatedProjectId($value): void
    {
        $this->projectId = $this->resolveProjectOrDefault($this->getDecodedProjectId());
    }

    protected function resolveProjectOrDefault($projectId = null): ?int
    {
        if ($projectId !== null && !is_numeric($projectId)) {
            $decoded = base64_decode($projectId, true);
            if ($decoded !== false && is_numeric($decoded)) {
                $projectId = (int) $decoded;
            }
        }
        $projectId = $projectId ? (int) $projectId : null;
        if ($projectId) {
            $exists = Project::accessibleBy(auth()->user())
                ->where('is_active', true)
                ->where('id', $projectId)
                ->exists();
            if (!$exists) {
                return null;
            }
        }
        return $projectId;
    }

    public function loadProjects(): void
    {
        $this->projectsLoaded = true;
        $this->projects = $this->getProjects();
    }

    public function runScraping($id): void
    {
        $project = Project::accessibleBy(auth()->user())->findOrFail($id);
        BootstrapNewProjectScrapingJob::dispatch($project->id);
        $this->showConfirmModal = false;
        $this->resetConfirmState();
        $this->notifyProjectAction("Scraping '{$project->name}' telah didaftarkan ke antrean background!");
        $this->forgetProjectsCache();
    }

    public function syncProject($id): void
    {
        $project = Project::accessibleBy(auth()->user())->findOrFail($id);
        $resyncResult = app(ContentMatchingService::class)->resyncProjectContent($project);
        $totalSynced = $resyncResult['total_synced'] ?? 0;
        $this->showConfirmModal = false;
        $this->resetConfirmState();
        $this->notifyProjectAction("Sinkronisasi '{$project->name}' berhasil. {$totalSynced} konten terhubung.");
        $this->forgetProjectsCache();
        $this->projects = $this->getProjects();
    }

    public function closeConfirmModal(): void
    {
        $this->showConfirmModal = false;
        $this->resetConfirmState();
    }

    protected function parseOptionalKeywordString(string $value): array
    {
        $items = array_map('trim', explode(',', $value));
        $items = array_filter($items);

        return array_values(array_unique($items));
    }

    protected function normalizeKeywordToHashtag(string $keyword): string
    {
        $keyword = trim($keyword);
        $keyword = preg_replace('/\s+/', ' ', $keyword) ?? $keyword;
        $keyword = str_replace(["'", "’", "‘", "`"], '', $keyword);
        $keyword = trim($keyword, " \t\n\r\0\x0B#");
        $keyword = preg_replace('/[^\p{L}\p{N}\s_]+/u', '', $keyword) ?? $keyword;
        $keyword = preg_replace('/\s+/u', '', $keyword) ?? $keyword;

        return $keyword === '' ? '' : '#' . $keyword;
    }

    protected function parseTopicsString(bool $normalize = false): array
    {
        $topics = array_map('trim', explode(',', (string) $this->topicsString));
        $topics = array_filter($topics);

        if ($normalize) {
            $topics = array_map(fn ($topic) => $this->normalizeKeywordToHashtag($topic), $topics);
            $topics = array_filter($topics);
        }

        return array_values(array_unique($topics));
    }

    protected function parseTopicsStringFromString(string $value, bool $normalize = false): array
    {
        $topics = array_map('trim', explode(',', $value));
        $topics = array_filter($topics);

        if ($normalize) {
            $topics = array_map(fn ($topic) => $this->normalizeKeywordToHashtag($topic), $topics);
            $topics = array_filter($topics);
        }

        return array_values(array_unique($topics));
    }

    protected function hydratePortalScanState(): void
    {
        if ($this->portalScanTimes !== null && $this->portalRunningProjectIds !== null) {
            return;
        }

        $this->portalScanTimes = [];
        $this->portalRunningProjectIds = [];
        $logPath = storage_path('logs/portal-manual.log');

        if (! is_readable($logPath)) {
            return;
        }

        $activeRunStartedAt = null;
        $activeRunFinishedAt = null;
        $latestActiveProjectId = null;
        $latestActiveProjectTime = null;
        $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach (array_slice($lines, -2000) as $line) {
            if (str_contains($line, '[Portal] Run started.')) {
                if (preg_match('/^\[(?<time>[^\]]+)\]/', $line, $match)) {
                    $activeRunStartedAt = $match['time'];
                    $activeRunFinishedAt = null;
                    $latestActiveProjectId = null;
                    $latestActiveProjectTime = null;
                }

                continue;
            }

            if (str_contains($line, '[Portal] Run finished.')) {
                if (preg_match('/^\[(?<time>[^\]]+)\]/', $line, $match)) {
                    $activeRunFinishedAt = $match['time'];
                    $latestActiveProjectId = null;
                    $latestActiveProjectTime = null;
                }

                continue;
            }

            if (! str_contains($line, '[Portal] Project keyword processed.')
                && ! str_contains($line, '[Portal] Scraping candidate article details.')) {
                continue;
            }

            if (! preg_match('/^\[(?<time>[^\]]+)\].*"project_id":(?<project_id>\d+)/', $line, $match)) {
                continue;
            }

            $projectId = (int) $match['project_id'];
            $this->portalScanTimes[$projectId] = $match['time'];

            if ($activeRunStartedAt && (! $activeRunFinishedAt || $match['time'] > $activeRunFinishedAt)) {
                try {
                    $loggedAt = \Carbon\Carbon::parse($match['time']);

                    if ($loggedAt->diffInMinutes(now()) <= 5) {
                        $latestActiveProjectId = $projectId;
                        $latestActiveProjectTime = $match['time'];
                    }
                } catch (\Throwable $e) {
                    // If the log timestamp cannot be parsed, avoid showing a stale running indicator.
                }
            }
        }

        $this->portalRunningProjectIds = $latestActiveProjectId && $latestActiveProjectTime
            ? [$latestActiveProjectId]
            : [];
    }

    protected function latestPortalScanForProject(int $projectId): ?string
    {
        $this->hydratePortalScanState();

        return $this->portalScanTimes[$projectId] ?? null;
    }

    protected function isPortalScanRunningForProject(int $projectId): bool
    {
        $this->hydratePortalScanState();

        return in_array($projectId, $this->portalRunningProjectIds ?? [], true);
    }

    protected function hydrateSocialActiveState(): void
    {
        if ($this->socialActiveProjects !== null) {
            return;
        }

        $freshThreshold = \Illuminate\Support\Carbon::now()->subMinutes(20);

        $states = DB::table('apify_dispatch_states')
            ->whereIn('status', ['queued', 'processing', 'retry_wait'])
            ->whereIn(DB::raw('lower(platform)'), ['facebook', 'instagram', 'tiktok'])
            ->where(function ($query) use ($freshThreshold) {
                $query->where('updated_at', '>=', $freshThreshold)
                    ->orWhere('started_at', '>=', $freshThreshold)
                    ->orWhere('queued_at', '>=', $freshThreshold);
            })
            ->orderByDesc('updated_at')
            ->get(['project_id', 'platform', 'status', 'updated_at']);

        $projects = [];

        foreach ($states as $state) {
            $projectId = (int) $state->project_id;

            if ($projectId <= 0) {
                continue;
            }

            $platform = strtolower((string) $state->platform);

            $projects[$projectId] ??= [
                'platforms' => [],
                'statuses' => [],
            ];

            if (! in_array($platform, $projects[$projectId]['platforms'], true)) {
                $projects[$projectId]['platforms'][] = $platform;
            }

            if (! in_array((string) $state->status, $projects[$projectId]['statuses'], true)) {
                $projects[$projectId]['statuses'][] = (string) $state->status;
            }
        }

        $this->socialActiveProjects = $projects;
    }

    protected function latestSocialRunForProject(int $projectId): ?string
    {
        return DB::table('apify_dispatch_states')
            ->where('project_id', $projectId)
            ->whereIn(DB::raw('lower(platform)'), ['facebook', 'instagram', 'tiktok'])
            // Abaikan aktivitas comment scraper — ini proses internal, bukan "data medsos baru"
            ->whereNotIn('actor_id', function ($sub) {
                $sub->select('id')
                    ->from('apify_actors')
                    ->whereRaw("lower(function_type) = 'comment scraper'");
            })
            ->max(DB::raw('coalesce(completed_at, started_at, queued_at)'));
    }

    protected function latestSuccessfulSocialRunForProject(int $projectId): ?string
    {
        return DB::table('apify_dispatch_states')
            ->where('project_id', $projectId)
            ->whereIn(DB::raw('lower(platform)'), ['facebook', 'instagram', 'tiktok'])
            ->where('status', 'success')
            // Abaikan aktivitas comment scraper — ini proses internal, bukan "data medsos baru"
            ->whereNotIn('actor_id', function ($sub) {
                $sub->select('id')
                    ->from('apify_actors')
                    ->whereRaw("lower(function_type) = 'comment scraper'");
            })
            ->max('completed_at');
    }

    protected function latestSocialDataForProject(int $projectId): ?string
    {
        return DB::table('social_media_items')
            ->where('project_id', $projectId)
            // Hanya hitung postingan yang sudah selesai diproses (comments_checked = true)
            // agar waktu tidak bergerak hanya karena ada post baru yang masih menunggu komentar
            ->where('comments_checked', true)
            ->max('created_at');
    }

    protected function latestSocialUpdateForProject(int $projectId): ?string
    {
        return $this->latestSocialDataForProject($projectId)
            ?? $this->latestSuccessfulSocialRunForProject($projectId)
            ?? $this->latestSocialRunForProject($projectId);
    }

    protected function isSocialScanRunningForProject(int $projectId): bool
    {
        $this->hydrateSocialActiveState();

        return isset($this->socialActiveProjects[$projectId]);
    }

    protected function activeSocialPlatformsForProject(int $projectId): array
    {
        $this->hydrateSocialActiveState();

        return $this->socialActiveProjects[$projectId]['platforms'] ?? [];
    }

    protected function socialRunningLabelForProject(int $projectId): string
    {
        $platforms = $this->activeSocialPlatformsForProject($projectId);
        $count = count($platforms);

        if ($count <= 0) {
            return 'Data Medsos Terakhir';
        }

        if ($count === 1) {
            return strtoupper($platforms[0]).' sedang berjalan';
        }

        return $count.' kanal medsos berjalan';
    }

    public function getProjects()
    {
        return Project::accessibleBy(auth()->user())
            ->where('is_active', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()->map(function($project) {
            $matchedCounts = [
                'articles' => (int) $project->articles()->count(),
                'social' => (int) $project->socialMediaItems()->count(),
            ];

            $analyzedArticlesQuery = Article::query()
                ->select('articles.*')
                ->join('project_articles', 'articles.id', '=', 'project_articles.article_id')
                ->where('project_articles.project_id', $project->id)
                ->whereHas('aiAnalysisResult', function($q) {
                    $q->completeOfficialAiResult()
                      ->whereNotNull('summary')
                      ->whereNotNull('sentiment')
                      ->whereNotNull('risk_level');
                });
            $totalAiValid = (clone $analyzedArticlesQuery)->count();
            $rescrapeCount = 0;
            $totalAiFailed = 0;

            $pendingAi = DB::table('ai_analysis_dispatch_states')
                ->where('project_id', $project->id)
                ->whereIn('status', ['queued', 'processing', 'retry_wait'])
                ->count();

            if ($totalAiValid > 0) {
                // Join ai_analysis_results to get the REAL sentiment, not the raw scrape sentiment
                $analyzedArticlesQueryWithAI = (clone $analyzedArticlesQuery)
                    ->join('ai_analysis_results as ai', 'articles.id', '=', 'ai.article_id');
                    
                $positive = (clone $analyzedArticlesQueryWithAI)->where('ai.sentiment', 'positive')->count();
                $negative = (clone $analyzedArticlesQueryWithAI)->where('ai.sentiment', 'negative')->count();
                $highCriticalRisk = (clone $analyzedArticlesQueryWithAI)->whereIn('ai.risk_level', ['high', 'critical'])->count();
                
                $posPercent = round(($positive / $totalAiValid) * 100);
                $negPercent = round(($negative / $totalAiValid) * 100);
            } else {
                $posPercent = 0;
                $negPercent = 0;
                $highCriticalRisk = 0;
            }

            $mentions = $matchedCounts['articles'] ?? 0;
            $reachQuery = AiAnalysisResult::query()
                ->completeOfficialAiResult()
                ->whereIn('article_id', (clone $analyzedArticlesQuery)->select('articles.id'));
            $officialReach = (clone $reachQuery)->sum('project_estimated_readers');
            $hasOfficialReach = (clone $reachQuery)->exists();
            $reach = $hasOfficialReach ? number_format($officialReach, 0, ',', '.') : 'Belum tersedia';

            $lastMedsosTime = $this->latestSocialUpdateForProject($project->id)
                ?? $project->socialMediaItems()->max('posted_at')
                ?? $project->socialMediaItems()->max('created_at');

            $lastPortalScanTime = $this->latestPortalScanForProject($project->id);
            $lastPortalUpdate = $lastPortalScanTime
                ? \Carbon\Carbon::parse($lastPortalScanTime)->locale('id')->diffForHumans()
                : 'Belum ada data';
                
            $lastMedsosUpdate = $lastMedsosTime
                ? \Carbon\Carbon::parse($lastMedsosTime)->locale('id')->diffForHumans() 
                : 'Belum ada data';

            return [
                'id' => $project->id,
                'name' => $project->name,
                'mentions' => number_format($mentions, 0, ',', '.'),
                'reach' => $reach,
                'positive' => $posPercent . '%',
                'negative' => $negPercent . '%',
                'topics' => $project->topics ?? [],
                'ai_valid' => $totalAiValid,
                'ai_failed' => $totalAiFailed,
                'ai_pending' => $pendingAi,
                'ai_rescrape' => $rescrapeCount,
                'high_risk' => $highCriticalRisk,
                'created_at' => $project->created_at ? $project->created_at->format('d M Y H:i') : '—',
                'last_portal_update' => $lastPortalUpdate,
                'portal_is_running' => $this->isPortalScanRunningForProject($project->id),
                'last_medsos_update' => $lastMedsosUpdate,
                'medsos_is_running' => $this->isSocialScanRunningForProject($project->id),
                'medsos_running_label' => $this->socialRunningLabelForProject($project->id),
                'articles_count' => $matchedCounts['articles'] ?? 0,
                'social_count' => $matchedCounts['social'] ?? 0,
            ];
        });
    }

    public function editProject($id)
    {
        $project = Project::accessibleBy(auth()->user())->findOrFail($id);
        $this->editProjectId = $project->id;
        $this->editName = $project->name;
        $this->editTopicsString = implode(', ', $project->topics ?? []);
        $this->contextKeywords = implode(', ', $project->context_keywords ?? []);
        $this->excludeKeywords = implode(', ', $project->exclude_keywords ?? []);
        $this->showEditModal = true;
    }

    public function updateProject()
    {
        $this->validate([
            'editName'         => 'required|min:3|unique:projects,name,' . $this->editProjectId,
            'editTopicsString' => 'required',
        ], [
            'editName.required'         => 'Nama proyek wajib diisi.',
            'editName.min'              => 'Nama proyek minimal 3 karakter.',
            'editName.unique'           => 'Nama proyek sudah digunakan.',
            'editTopicsString.required' => 'Topik/kata kunci wajib diisi.',
        ]);

        $project = Project::accessibleBy(auth()->user())->findOrFail($this->editProjectId);

        // Validate JSON string
        if (str_starts_with(trim($this->editTopicsString), '{') || str_starts_with(trim($this->editTopicsString), '[')) {
            $this->addError('editTopicsString', 'Format JSON tidak diperbolehkan. Gunakan kata kunci yang dipisahkan koma.');
            return;
        }

        $topics = $this->parseTopicsStringFromString($this->editTopicsString, false);

        if (empty($topics)) {
            $this->addError('editTopicsString', 'Topik wajib diisi minimal satu kata kunci valid.');
            return;
        }

        $project->update([
            'name' => $this->editName,
            'topics' => $topics,
            'context_keywords' => $this->parseOptionalKeywordString((string) $this->contextKeywords),
            'exclude_keywords' => $this->parseOptionalKeywordString((string) $this->excludeKeywords),
        ]);

        $resyncResult = app(ContentMatchingService::class)->resyncProjectContent($project);

        $this->showEditModal = false;
        $this->editProjectId = null;
        session()->flash(
            'message',
            'Proyek berhasil diperbarui. Data lama yang sesuai filter: '
            . (($resyncResult['match']['articles_linked'] ?? 0)) . ' artikel, '
            . (($resyncResult['match']['social_linked'] ?? 0)) . ' medsos. '
            . 'Social disinkronkan ulang: '
            . (($resyncResult['social_sync']['attached'] ?? 0)) . ' tertaut, '
            . (($resyncResult['social_sync']['detached'] ?? 0)) . ' dilepas.'
        );
        $this->notifyProjectAction(
            'Proyek berhasil diperbarui. Data lama yang sesuai filter: '
            . (($resyncResult['match']['articles_linked'] ?? 0)) . ' artikel, '
            . (($resyncResult['match']['social_linked'] ?? 0)) . ' medsos. '
            . 'Social disinkronkan ulang: '
            . (($resyncResult['social_sync']['attached'] ?? 0)) . ' tertaut, '
            . (($resyncResult['social_sync']['detached'] ?? 0)) . ' dilepas.'
        );
    }

    // Trashed projects modal state

    public function closeModals()
    {

        $this->showEditModal = false;
        $this->showTrashedModal = false;
        $this->showConfirmModal = false;
        $this->resetConfirmState();
    }

    protected function resetConfirmState(): void
    {
        $this->confirmAction = null;
        $this->confirmProjectId = null;
        $this->confirmProjectName = '';
        $this->confirmTitle = '';
        $this->confirmMessage = '';
    }

    protected function notifyProjectAction(string $message, string $type = 'success'): void
    {
        $this->toastType = $type;
        $this->toastMessage = $message;
        $this->dispatch('project-action-toast', type: $type, message: $message);
        $this->dispatch('project-scroll-unlock');
    }

    public function confirmDeleteProject($id)
    {
        $project = Project::accessibleBy(auth()->user())->findOrFail($id);

        $this->confirmAction = 'delete';
        $this->confirmProjectId = $project->id;
        $this->confirmProjectName = $project->name;
        $this->confirmTitle = 'Nonaktifkan proyek?';
        $this->confirmMessage = 'Proyek hanya disembunyikan dari monitoring aktif. Data portal dan media sosial tetap tersimpan.';
        $this->showConfirmModal = true;
    }

    public function confirmRestoreProject($id)
    {
        $project = Project::accessibleBy(auth()->user())
            ->onlyTrashed()
            ->findOrFail($id);

        $this->confirmAction = 'restore';
        $this->confirmProjectId = $project->id;
        $this->confirmProjectName = $project->name;
        $this->confirmTitle = 'Aktifkan kembali proyek?';
        $this->confirmMessage = 'Proyek akan kembali tampil dan bisa dipantau lagi dengan data sumber yang sudah ada.';
        $this->showConfirmModal = true;
    }

    public function confirmForceDeleteProject($id)
    {
        $project = Project::accessibleBy(auth()->user())
            ->onlyTrashed()
            ->findOrFail($id);

        $this->confirmAction = 'force_delete';
        $this->confirmProjectId = $project->id;
        $this->confirmProjectName = $project->name;
        $this->confirmTitle = 'Hapus permanen proyek?';
        $this->confirmMessage = 'Proyek akan dihapus permanen dari daftar. Data artikel dan hasil monitoring yang sudah tersimpan tidak ikut dihapus.';
        $this->showConfirmModal = true;
    }

    public function confirmRunScraping($id)
    {
        $project = Project::accessibleBy(auth()->user())->findOrFail($id);

        $this->confirmAction = 'run_scraping';
        $this->confirmProjectId = $project->id;
        $this->confirmProjectName = $project->name;
        $this->confirmTitle = 'Jalankan Scraping Sekarang?';
        $this->confirmMessage = "Apakah Anda yakin ingin menjalankan scraping langsung untuk proyek '{$project->name}'? Tindakan ini akan langsung mencari portal berita dan media sosial, serta mengonsumsi kuota API/Apify Anda.";
        $this->showConfirmModal = true;
    }

    public function confirmSyncProject($id)
    {
        $project = Project::accessibleBy(auth()->user())->findOrFail($id);

        $this->confirmAction = 'sync_project';
        $this->confirmProjectId = $project->id;
        $this->confirmProjectName = $project->name;
        $this->confirmTitle = 'Sinkronisasi Ulang Konten?';
        $this->confirmMessage = "Apakah Anda yakin ingin mensinkronisasi ulang seluruh konten artikel dan media sosial untuk proyek '{$project->name}' berdasarkan kata kunci terbaru?";
        $this->showConfirmModal = true;
    }

    public function runConfirmedProjectAction()
    {
        if ($this->confirmAction === 'delete' && $this->confirmProjectId) {
            $this->deleteProject($this->confirmProjectId);
            return;
        }

        if ($this->confirmAction === 'restore' && $this->confirmProjectId) {
            $this->restoreProject($this->confirmProjectId);
            return;
        }

        if ($this->confirmAction === 'force_delete' && $this->confirmProjectId) {
            $this->forceDeleteProject($this->confirmProjectId);
            return;
        }

        if ($this->confirmAction === 'run_scraping' && $this->confirmProjectId) {
            $this->runScraping($this->confirmProjectId);
            return;
        }

        if ($this->confirmAction === 'sync_project' && $this->confirmProjectId) {
            $this->syncProject($this->confirmProjectId);
            return;
        }

        $this->showConfirmModal = false;
        $this->resetConfirmState();
        $this->notifyProjectAction('Aksi proyek tidak valid.', 'error');
    }

    public function deleteProject($id)
    {
        $project = Project::accessibleBy(auth()->user())->findOrFail($id);
        // Proyek hanya dinonaktifkan sebagai konteks monitoring.
        // Data sumber (portal/sosmed) tetap disimpan dan tidak ikut dihapus.
        $project->update(['is_active' => false]);
        $project->delete();

        if ((int) $this->getDecodedProjectId() === (int) $project->id) {
            $this->projectId = null;
        }

        $this->showConfirmModal = false;
        $this->resetConfirmState();
        session()->flash('message', 'Proyek berhasil dinonaktifkan. Data sumber tetap tersimpan.');
        $this->notifyProjectAction('Proyek dinonaktifkan. Data sumber tetap aman.');
    }

    public function getTrashedProjects()
    {
        return Project::accessibleBy(auth()->user())
            ->onlyTrashed()
            ->get();
    }

    public function restoreProject($id)
    {
        $project = Project::accessibleBy(auth()->user())
            ->onlyTrashed()
            ->findOrFail($id);
            
        $project->restore();
        $project->update(['is_active' => true]);

        $this->showConfirmModal = false;
        session()->flash('message', 'Proyek berhasil diaktifkan kembali.');
        $this->notifyProjectAction('Proyek aktif kembali dan siap dipantau.');
        $this->showTrashedModal = false;
        $this->resetConfirmState();
    }

    public function forceDeleteProject($id)
    {
        $project = Project::accessibleBy(auth()->user())
            ->onlyTrashed()
            ->findOrFail($id);

            \Illuminate\Support\Facades\DB::transaction(function () use ($project) {
                \Illuminate\Support\Facades\DB::table('project_user')
                    ->where('project_id', $project->id)
                    ->delete();

            if (\Illuminate\Support\Facades\Schema::hasTable('ai_analysis_dispatch_states')) {
                \Illuminate\Support\Facades\DB::table('ai_analysis_dispatch_states')
                    ->where('project_id', $project->id)
                    ->delete();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('apify_dispatch_states')) {
                \Illuminate\Support\Facades\DB::table('apify_dispatch_states')
                    ->where('project_id', $project->id)
                    ->delete();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('project_telegram_recipients')) {
                \Illuminate\Support\Facades\DB::table('project_telegram_recipients')
                    ->where('project_id', $project->id)
                    ->delete();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('reach_assessments')) {
                \Illuminate\Support\Facades\DB::table('reach_assessments')
                    ->where('project_id', $project->id)
                    ->delete();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('candidate_links')) {
                \Illuminate\Support\Facades\DB::table('candidate_links')
                    ->where('project_id', $project->id)
                    ->delete();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('articles')) {
                \Illuminate\Support\Facades\DB::table('articles')
                    ->where('project_id', $project->id)
                    ->update(['project_id' => null]);
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('social_media_items')) {
                \Illuminate\Support\Facades\DB::table('social_media_items')
                    ->where('project_id', $project->id)
                    ->update(['project_id' => null]);
            }

            $project->forceDelete();
        });

        if ((int) $this->getDecodedProjectId() === (int) $project->id) {
            $this->projectId = null;
        }

        $this->showConfirmModal = false;
        $this->showTrashedModal = false;
        $this->resetConfirmState();
        session()->flash('message', 'Proyek berhasil dihapus permanen. Data artikel tetap tersimpan.');
        $this->notifyProjectAction('Proyek dihapus permanen. Data artikel tetap aman.');
    }
};
?>

<div
    class="{{ $projectId ? 'w-full' : '' }}"
    x-data="{

        showTrashed: @entangle('showTrashedModal'),
        showConfirm: @entangle('showConfirmModal'),
        toastVisible: false,
        toastType: @entangle('toastType'),
        toastMessage: @entangle('toastMessage'),
        toastTimer: null,
        detailModalOpen: false,
        showViralModal: false,
        openedFromViral: false,
        showAiSummaryModal: false,
        scrolledDown: false,
        mobileFilterOpen: false,
        detailTitle: '',
        detailSource: '',
        detailDate: '',
        detailUrl: '',
        detailContent: '',
        detailAiSummary: '',
        detailAiRecommendation: '',
        detailSentiment: '',
        detailCategory: '',
        detailReach: '',
        detailLevel: '',
        detailScore: '',
        detailFormattedDate: '',
        detailLikes: 0,
        detailComments: 0,
        detailHashtags: [],
        updateScrollLock() {
            const shouldLock = (this.showSuccess || this.showEdit || this.showTrashed || this.showConfirm || this.detailModalOpen || this.showViralModal || this.showAiSummaryModal);
            document.body.style.overflow = shouldLock ? 'hidden' : '';
            document.documentElement.style.overflow = shouldLock ? 'hidden' : '';
        },
        showToast(message = null, type = null) {
            if (message) this.toastMessage = message;
            if (type) this.toastType = type;
            if (!this.toastMessage) return;
            this.toastVisible = true;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => this.toastVisible = false, 3000);
        },
        init() {
            window.openDashboardDetail = (title, source, date, url, content, summary, rec, sentiment, category, reach, level, score, formattedDate, likes = 0, comments = 0, hashtags = []) => {
                this.detailTitle = title;
                this.detailSource = source;
                this.detailDate = date;
                this.detailUrl = url;
                this.detailContent = content;
                this.detailAiSummary = summary;
                this.detailAiRecommendation = rec;
                this.detailSentiment = sentiment;
                this.detailCategory = category;
                this.detailReach = reach;
                this.detailLevel = level;
                this.detailScore = score;
                this.detailFormattedDate = formattedDate;
                this.detailLikes = likes;
                this.detailComments = comments;
                this.detailHashtags = hashtags;
                this.showAiSummaryModal = false;
                this.detailModalOpen = true;
            };
            window.closeDashboardDetail = () => {
                this.detailModalOpen = false;
                if (this.openedFromViral) {
                    this.showViralModal = true;
                    this.openedFromViral = false;
                }
            };
        }
    }"
    x-effect="updateScrollLock()"
    x-init="
        updateScrollLock();
        window.addEventListener('project-scroll-unlock', () => { document.body.style.overflow = ''; });
        window.addEventListener('project-action-toast', event => showToast(event.detail?.message, event.detail?.type));
    "
>
    @if($projectId)
        <livewire:media-dashboard :projectId="$projectId" />
    @else
        <div class="min-h-screen bg-surface-studio text-slate-800 flex flex-col font-sans">
            <!-- Header -->
            <header class="w-full bg-white border-b border-slate-200 sticky top-0 z-50">
                <div class="max-w-[1400px] mx-auto px-6 h-20 flex items-center justify-between">
                    <!-- Brand & Nav -->
            <div class="flex items-center gap-6 h-full">
                        <!-- Brand Logo Arusbawah -->
                        <a href="{{ route('home') }}" class="flex items-center gap-2 font-sans cursor-pointer">
                            @if($customLogo = \App\Helpers\AppBrandingHelper::getAppLogoPath())
                                <img src="{{ asset('storage/' . $customLogo) }}" class="h-8 max-w-[120px] object-contain transition-transform hover:scale-105 duration-300">
                            @else
                                <svg width="28" height="28" viewBox="0 0 42 42" fill="none" xmlns="http://www.w3.org/2000/svg" class="transition-transform hover:scale-105 duration-300">
                                    <polygon points="21,4 39,38 3,38" fill="none" stroke="#1fa387" stroke-width="4" stroke-linejoin="round"/>
                                    <line x1="11" y1="28" x2="31" y2="28" stroke="#1fa387" stroke-width="4" stroke-linecap="round"/>
                                </svg>
                            @endif
                            <div class="flex flex-col text-left">
                                <span class="text-sm font-black tracking-wider leading-none text-slate-800 uppercase">{{ \App\Helpers\AppBrandingHelper::getAppName() }}</span>
                                <span class="text-[7.5px] font-bold text-slate-400 uppercase tracking-widest leading-none mt-0.5">Media Intelligence</span>
                            </div>
                        </a>

                        @if(auth()->check() && auth()->user()->isAdmin())
                        <div class="h-6 w-px bg-slate-200 hidden md:block ml-2"></div>
                        <nav class="hidden md:flex items-center gap-6 ml-2">
                            <a href="{{ route('admin.dashboard') }}" wire:navigate class="text-sm font-semibold text-slate-600 hover:text-[#1fa387] transition-colors flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px]">admin_panel_settings</span>
                                Admin Panel
                            </a>
                            <a href="{{ route('admin.pipeline-monitor') }}" wire:navigate class="text-sm font-semibold text-slate-600 hover:text-[#1fa387] transition-colors flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px]">queue</span>
                                Pipeline Antrian
                            </a>
                        </nav>
                        @endif

                    </div>

                    <!-- User Profile and Actions -->
                    <div class="ml-auto flex shrink-0 items-center gap-4">
                        <div class="relative" x-data="{ open: false }">
                            <button
                                type="button"
                                @click="open = !open"
                                class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-full pl-1 pr-3 py-1 cursor-pointer hover:bg-slate-100 transition-colors active:scale-95"
                            >
                                <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                    </svg>
                                </div>
                                <span class="text-xs font-medium text-slate-600">{{ auth()->user()->email }}</span>
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M19 9l-7 7-7-7" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                </svg>
                            </button>

                            <!-- Dropdown Menu -->
                            <div 
                                x-show="open" 
                                @click.away="open = false"
                                style="display: none;"
                                class="absolute right-0 mt-2 w-56 bg-white rounded-xl border border-slate-100 shadow-lg z-[60] py-2"
                            >
                                <a wire:navigate class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors" href="{{ route('password.change') }}">
                                    <span class="material-symbols-outlined text-slate-400 text-lg">person</span>
                                    <span>Ganti Password</span>
                                </a>
                                <div class="my-1 border-t border-slate-100"></div>
                                <form method="POST" action="{{ url('/logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 transition-colors text-left">
                                        <span class="material-symbols-outlined text-lg">logout</span>
                                        <span>Logout</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="max-w-[1400px] mx-auto px-6 py-10 flex-grow w-full">
                
                <!-- Toast Alerts -->
                @if (session()->has('message'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-600 rounded flex items-center justify-between shadow-sm">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-sm font-medium">{{ session('message') }}</span>
                        </div>
                        <button @click="show = false" class="text-emerald-500 hover:text-emerald-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                @endif
                    <!-- Title Section -->
                    <section class="mb-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-hanken font-bold text-slate-900 mb-1">Daftar Proyek Anda</h1>
                            <p class="text-slate-500 text-sm">Kelola dan pantau seluruh kampanye media monitoring Anda secara real-time.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            @if(auth()->check() && auth()->user()->isUser())
                                <a 
                                    href="{{ route('admin.clients') }}" 
                                    wire:navigate
                                    class="px-4 py-2 bg-white border border-slate-300 hover:border-[#1fa387] text-slate-700 hover:text-[#1fa387] rounded-xl text-sm font-semibold transition shadow-sm flex items-center gap-2"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    <span>Kelola Client</span>
                                </a>
                            @endif
                            @if(auth()->check() && !auth()->user()->isClient())
                            <button 
                                wire:click="openTrashedProjectsModal"
                                wire:loading.attr="disabled"
                                wire:target="openTrashedProjectsModal"
                                class="px-4 py-2 border border-slate-300 hover:border-slate-400 text-slate-600 hover:text-slate-800 rounded-xl text-sm font-semibold transition bg-white shadow-sm flex items-center gap-2 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
                            >
                                <!-- Spinner berputar saat proses request dikirim -->
                                <svg wire:loading wire:target="openTrashedProjectsModal" class="animate-spin w-4 h-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <!-- Icon Trash biasa yang disembunyikan saat loading -->
                                <svg wire:loading.remove wire:target="openTrashedProjectsModal" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                                <span>Proyek Dinonaktifkan</span>
                            </button>
                            @endif
                        </div>
                    </section>

                    <div wire:init="loadProjects">
                        @if($projectsLoaded)
                            @if(!auth()->user()?->isAdmin() && empty($projects))
                                <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-600 shadow-sm">
                                    Belum ada project yang diberikan ke akun Anda.
                                </div>
                            @endif

                            <!-- Project Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch">
                                <!-- Create Project Card -->
                                @if(auth()->check())
                                    <a 
                                        href="{{ route('projects.create') }}" 
                                        wire:navigate
                                        class="dashed-border bg-white rounded-2xl border-2 border-dashed border-slate-300 p-6 flex flex-col items-center justify-center text-center hover:bg-white/50 transition-all duration-300 shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)] hover:shadow-[0_8px_30px_rgba(0,0,0,0.06)]"
                                        style="min-height: 620px; height: 100%; display: flex; text-decoration: none;"
                                    >
                                        <div class="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-5 shadow-sm">
                                            <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 4v16m8-8H4" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                            </svg>
                                        </div>
                                        <h3 class="text-xl font-hanken font-bold text-slate-800 mb-2">Buat Proyek Baru</h3>
                                        <p class="text-slate-400 text-sm max-w-[220px] leading-relaxed">
                                            Tambahkan monitoring media online, cetak, dan media sosial baru
                                        </p>
                                    </a>
                                @endif

                                <!-- Dynamic Projects List -->
                                @foreach($projects as $idx => $project)
                                    @php
                                        $projectCreatedAt = $project['created_at'] ?? '—';
                                        $portalIsRunning = (bool) ($project['portal_is_running'] ?? false);
                                        $lastPortalUpdate = $project['last_portal_update'] ?? 'Belum ada data';
                                        $medsosIsRunning = (bool) ($project['medsos_is_running'] ?? false);
                                        $medsosRunningLabel = $project['medsos_running_label'] ?? 'Data Medsos Terakhir';
                                        $lastMedsosUpdate = $project['last_medsos_update'] ?? 'Belum ada data';
                                    @endphp
                                    <article
                                        x-data="{
                                            showRiskStats: JSON.parse(localStorage.getItem('project-risk-stats-{{ $project['id'] }}') || 'false'),
                                            toggleRiskStats() {
                                                this.showRiskStats = !this.showRiskStats;
                                                localStorage.setItem('project-risk-stats-{{ $project['id'] }}', JSON.stringify(this.showRiskStats));
                                            }
                                        }"
                                        class="bg-white rounded-2xl border border-slate-200 p-6 flex flex-col justify-between shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)] hover:shadow-[0_8px_30px_rgba(0,0,0,0.06)] transition-all h-full"
                                        style="min-height: 620px;"
                                    >
                                <!-- Card Header -->
                                <div class="flex items-start justify-between mb-8">
                                    <div class="flex items-center gap-3">
                                        <div class="px-2 py-1 rounded bg-primary/10 text-primary font-bold text-[10px] tracking-widest border border-primary/20">
                                            {{ sprintf('%02d', $idx + 1) }}
                                        </div>
                                        <div>
                                            <div class="flex items-center flex-wrap gap-1.5 mb-0.5">
                                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest whitespace-nowrap">PROYEK</span>
                                                <span class="text-[9px] text-slate-300 whitespace-nowrap">•</span>
                                                <span class="text-[9px] text-slate-400 font-bold whitespace-nowrap">Dibuat: {{ $projectCreatedAt }}</span>
                                                @if(!empty($project['package_name']))
                                                    <span class="text-[9px] text-slate-300 whitespace-nowrap">•</span>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-[#1fa387] text-white font-extrabold text-[9px] uppercase tracking-widest shadow-sm whitespace-nowrap">
                                                        <svg class="w-3 h-3 text-[#FFD700] drop-shadow-[0_0_5px_rgba(255,215,0,0.8)] animate-pulse" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                                                        {{ $project['package_name'] }}
                                                    </span>
                                                @endif
                                            </div>
                                            <h2 class="text-xl font-hanken font-extrabold text-[#1fa387] uppercase leading-tight">{{ $project['name'] }}</h2>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <!-- Play/Scraping Button -->
                                        <button 
                                            wire:click="confirmRunScraping({{ $project['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="confirmRunScraping({{ $project['id'] }})"
                                            title="Jalankan Scraping Sekarang (Portal & Medsos)"
                                            class="text-slate-300 hover:text-emerald-500 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <!-- Normal Icon (Play SVG) -->
                                            <svg wire:loading.remove wire:target="confirmRunScraping({{ $project['id'] }})" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M8 5v14l11-7z"></path>
                                            </svg>
                                            <!-- Loading Spinner -->
                                            <svg wire:loading wire:target="confirmRunScraping({{ $project['id'] }})" class="animate-spin w-4 h-4 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>

                                        <!-- Edit Button -->
                                        <button 
                                            wire:click="$dispatch('open-project-edit', { projectId: {{ $project['id'] }} })"
                                            title="Edit Proyek"
                                            class="text-slate-300 hover:text-blue-500 transition-colors cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                            </svg>
                                        </button>
                                        <!-- Delete Button - hidden for Clients without permission -->
                                        @php
                                            $authUser = auth()->user();
                                            $canDeactivate = !$authUser->isClient() || optional($authUser->clientSettings)->can_delete_projects;
                                        @endphp
                                        @if($canDeactivate)
                                        <button 
                                            wire:click="confirmDeleteProject({{ $project['id'] }})"
                                            title="Nonaktifkan Proyek"
                                            class="text-slate-300 hover:text-red-500 transition-colors cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                            </svg>
                                        </button>
                                        @endif
                                    </div>
                                </div>

                                <!-- Last Update Info -->
                                <div class="grid grid-cols-2 gap-3 mb-6 bg-slate-50/60 rounded-xl p-3 border border-slate-100/80 text-[10px] font-semibold text-slate-500">
                                    <div class="flex items-center gap-1.5">
                                        <span class="relative inline-flex h-5 w-5 items-center justify-center rounded-full {{ $portalIsRunning ? 'bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200' : 'text-slate-400' }}">
                                            @if($portalIsRunning)
                                                <span class="absolute inline-flex h-5 w-5 animate-ping rounded-full bg-emerald-400/60"></span>
                                                <span class="absolute inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500 shadow-[0_0_14px_rgba(16,185,129,0.85)]"></span>
                                            @endif
                                            <span class="material-symbols-outlined relative text-[14px]">language</span>
                                        </span>
                                        <div>
                                            <p class="text-[8px] uppercase tracking-wider font-bold leading-none mb-1 {{ $portalIsRunning ? 'text-emerald-500' : 'text-slate-400' }}">
                                                {{ $portalIsRunning ? 'Scan Portal Berjalan' : 'Data Portal Terakhir' }}
                                            </p>
                                            <p class="font-bold leading-none {{ $portalIsRunning ? 'text-emerald-700' : 'text-slate-700' }}">{{ $lastPortalUpdate }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 border-l border-slate-200/80 pl-3">
                                        <span class="relative inline-flex h-5 w-5 items-center justify-center rounded-full {{ $medsosIsRunning ? 'bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200' : 'text-slate-400' }}">
                                            @if($medsosIsRunning)
                                                <span class="absolute inline-flex h-5 w-5 animate-ping rounded-full bg-emerald-400/60"></span>
                                                <span class="absolute inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500 shadow-[0_0_14px_rgba(16,185,129,0.85)]"></span>
                                            @endif
                                            <span class="material-symbols-outlined relative text-[14px]">group</span>
                                            </span>
                                        <div>
                                            <p class="text-[8px] uppercase tracking-wider font-bold leading-none mb-1 {{ $medsosIsRunning ? 'text-emerald-500' : 'text-slate-400' }}">
                                                {{ $medsosIsRunning ? 'Scan Medsos Berjalan' : 'Data Medsos Terakhir' }}
                                            </p>
                                            <p class="font-bold leading-none {{ $medsosIsRunning ? 'text-emerald-700' : 'text-slate-700' }}">{{ $medsosIsRunning ? $medsosRunningLabel : $lastMedsosUpdate }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Metrics Grid -->
                                <div class="grid grid-cols-2 gap-4 mb-3">
                                    <!-- Artikel Siap Ditampilkan -->
                                    <div class="bg-slate-50/50 rounded-xl p-4 border border-slate-100">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">ARTIKEL SIAP DITAMPILKAN</span>
                                        </div>
                                        <p class="text-xl font-extrabold text-slate-900 mb-0.5">{{ $project['ai_valid'] }}</p>
                                    </div>
                                    
                                    <!-- Jangkauan (Reach) -->
                                    <div class="bg-slate-50/50 rounded-xl p-4 border border-slate-100">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">JANGKAUAN</span>
                                            <span class="text-[10px] font-bold text-emerald-500 flex items-center">
                                                <svg class="w-2.5 h-2.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                                </svg>
                                                100%
                                            </span>
                                        </div>
                                        <p class="text-xl font-extrabold text-slate-900 mb-0.5">{{ $project['reach'] }}</p>
                                        <p class="text-[9px] text-slate-400 font-mono">vs kemarin</p>
                                    </div>

                                    <!-- Positif -->
                                    <div class="bg-slate-50/50 rounded-xl p-4 border border-slate-100">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">POSITIF</span>
                                        </div>
                                        <p class="text-xl font-extrabold text-emerald-500 mb-0.5">{{ $project['positive'] }}</p>
                                        <p class="text-[9px] text-slate-400 font-mono">analisis sentimen</p>
                                    </div>

                                    <!-- Negatif -->
                                    <div class="bg-slate-50/50 rounded-xl p-4 border border-slate-100">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">NEGATIF</span>
                                        </div>
                                        <p class="text-xl font-extrabold text-rose-500 mb-0.5">{{ $project['negative'] }}</p>
                                        <p class="text-[9px] text-slate-400 font-mono">analisis risiko</p>
                                    </div>
                                </div>

                                <!-- AI & Risk Stats -->
                                <button
                                    type="button"
                                    @click="toggleRiskStats()"
                                    class="-mt-1 mb-2 inline-flex items-center gap-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest transition leading-none cursor-pointer hover:text-slate-500"
                                >
                                    <span x-text="showRiskStats ? 'Sembunyikan' : 'Tampilkan'"></span>
                                </button>
                                <div
                                    x-show="showRiskStats"
                                    x-cloak
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                    x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                                    class="bg-slate-50 border border-slate-100 rounded-xl p-3 -mt-2 mb-0 origin-top"
                                >
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0">STATUS AI & RISIKO</p>
                                    <div class="grid grid-cols-3 gap-2 text-center">
                                        <div>
                                            <p class="text-sm font-bold text-slate-700">{{ $project['ai_valid'] }}</p>
                                            <p class="text-[9px] text-slate-400">Siap Ditampilkan</p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-amber-500">{{ $project['ai_pending'] }}</p>
                                            <p class="text-[9px] text-slate-400 cursor-help" title="Artikel yang sedang menunggu pemrosesan atau validasi AI.">Analisis AI ⓘ</p>
                                        </div>
                                        <div class="border-l border-slate-200 pl-2">
                                            <p class="text-sm font-bold {{ $project['high_risk'] > 0 ? 'text-rose-600' : 'text-slate-400' }}">{{ $project['high_risk'] }}</p>
                                            <p class="text-[9px] text-slate-400">High Risk</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Topics -->
                                <div class="mb-8 mt-1">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">TOPIK POPULER</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($project['topics'] as $topic)
                                            <span class="px-2 py-1 bg-primary/10 text-primary text-[10px] font-semibold rounded-md">
                                                {{ $topic }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Action -->
                                <div class="mt-auto" x-data="{ openingProject: false }">
                                    <a 
                                        href="{{ route('home', ['project' => base64_encode($project['id']), 'tab' => base64_encode('penyebutan')]) }}"
                                        @click="openingProject = true"
                                        class="block w-full py-3 border border-primary text-primary rounded-xl text-center text-sm font-bold hover:bg-primary/5 cursor-pointer transition-colors"
                                    >
                                        <span x-show="!openingProject" class="inline-flex items-center justify-center gap-2">
                                            Detail Proyek
                                        </span>
                                        <span x-cloak x-show="openingProject" class="inline-flex items-center justify-center gap-2">
                                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                            </svg>
                                            Membuka...
                                        </span>
                                    </a>
                                </div>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-start">
                                @if(auth()->check())
                                    <div class="dashed-border bg-white rounded-2xl border-2 border-dashed border-slate-300 p-6 flex flex-col self-start min-h-[880px] cursor-default animate-pulse shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)]">
                                        <div class="w-12 h-12 rounded-full bg-slate-100 mb-6"></div>
                                        <div class="h-6 w-40 rounded bg-slate-100 mb-2"></div>
                                        <div class="h-4 w-56 rounded bg-slate-100"></div>
                                    </div>
                                @endif
                                @for($i = 0; $i < 4; $i++)
                                    <div class="bg-white rounded-2xl border border-slate-200 p-6 flex flex-col self-start min-h-[880px] animate-pulse shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)]">
                                        <div class="h-4 w-24 rounded bg-slate-100 mb-6"></div>
                                        <div class="h-7 w-3/4 rounded bg-slate-100 mb-4"></div>
                                        <div class="h-40 rounded bg-slate-100 mb-4"></div>
                                        <div class="h-10 rounded bg-slate-100 mt-auto"></div>
                                    </div>
                                @endfor
                            </div>
                        @endif
                    </div>
                @endif
            </main>


            <livewire:project-edit-modal />

            <!-- Trashed Projects Modal -->
            @if($showTrashedModal)
                <div
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                >
                    <div 
                        @click.outside="!$wire.showConfirmModal && $wire.closeModals()"
                        class="bg-white rounded-3xl w-full max-w-4xl shadow-2xl border border-slate-100/80 overflow-hidden transform transition-all duration-300 scale-100 flex flex-col h-[600px]"
                    >
                        <!-- Modal Header -->
                        <div class="px-8 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 shrink-0">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 shadow-sm border border-slate-200/50">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-hanken font-extrabold text-slate-900 leading-tight">Proyek Dinonaktifkan</h3>
                                    <p class="text-[11px] text-slate-400 mt-0.5 font-medium">Pulihkan kembali proyek atau hapus secara permanen untuk membersihkan data.</p>
                                </div>
                            </div>
                            <button wire:click="closeModals" class="text-slate-400 hover:text-slate-650 hover:bg-slate-100 p-2 rounded-full transition duration-150 cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                            </button>
                        </div>

                        <!-- Modal Body (Fix Height & Scrollable) -->
                        <div class="flex-1 overflow-y-auto px-8 py-6">
                            @php
                                $trashed = $this->getTrashedProjects();
                            @endphp
                            @if($trashed->isEmpty())
                                <div class="flex flex-col items-center justify-center h-full text-center">
                                    <div class="w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center text-slate-350 border border-slate-100 mb-4">
                                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                                    </div>
                                    <h4 class="text-sm font-bold text-slate-800 mb-1">Tidak ada proyek yang dinonaktifkan</h4>
                                    <p class="text-xs text-slate-450 max-w-[280px] leading-relaxed">Semua proyek Anda saat ini dalam status aktif dan berjalan normal.</p>
                                </div>
                            @else
                                <div class="divide-y divide-slate-100">
                                    @foreach($trashed as $idx => $tp)
                                        <div class="py-5 flex items-center justify-between gap-6 px-4 rounded-2xl transition duration-150 {{ $idx % 2 === 0 ? 'bg-[#F8F9FA]' : 'bg-white' }} hover:bg-slate-50 border border-transparent hover:border-slate-100">
                                            <div class="flex items-start gap-3 flex-1 min-w-0">
                                                <!-- Index Badge (Identical to active project card index) -->
                                                <div class="px-2 py-1 rounded bg-[#1fa387]/10 text-[#1fa387] font-bold text-[10px] tracking-widest border border-[#1fa387]/20 shrink-0">
                                                    {{ sprintf('%02d', $idx + 1) }}
                                                </div>
                                                
                                                <div class="text-left min-w-0 space-y-1">
                                                    <div class="flex items-center gap-1.5 leading-none">
                                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">PROYEK</span>
                                                        <span class="text-[8px] text-slate-300">•</span>
                                                        <span class="text-[9px] text-slate-400 font-bold">Dibuat: {{ $tp->created_at ? $tp->created_at->format('d M Y H:i') : '—' }}</span>
                                                    </div>
                                                    
                                                    <!-- Uppercase Tosca Title (Identical to active project card title) -->
                                                    <h4 class="text-sm font-extrabold text-[#1fa387] truncate uppercase tracking-tight">{{ $tp->name }}</h4>
                                                    
                                                    <!-- Keywords List -->
                                                    <div class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400">
                                                        <span>Kata kunci:</span>
                                                        <span class="font-semibold text-slate-600 truncate">{{ implode(', ', $tp->topics ?? []) }}</span>
                                                    </div>

                                                    <p class="text-[9px] text-rose-500 font-bold">Dinonaktifkan pada: {{ $tp->deleted_at->locale('id')->isoFormat('D MMM YYYY, HH:mm') }}</p>
                                                </div>
                                            </div>

                                            <!-- Action Buttons (Rata Kanan) -->
                                            <div class="flex items-center gap-3 flex-shrink-0">
                                                <button
                                                    wire:click="confirmRestoreProject({{ $tp->id }})"
                                                    class="px-5 py-2.5 bg-[#1fa387] hover:bg-[#1a8b73] text-white text-xs font-extrabold rounded-xl transition duration-150 cursor-pointer shadow-sm active:scale-[0.98]"
                                                >
                                                    Aktifkan
                                                </button>
                                                <button
                                                    wire:click="confirmForceDeleteProject({{ $tp->id }})"
                                                    class="px-5 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-650 text-xs font-extrabold rounded-xl transition duration-150 cursor-pointer active:scale-[0.98]"
                                                >
                                                    Hapus
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Modal Footer -->
                        <div class="px-8 py-5 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 shrink-0">
                            <button
                                type="button"
                                wire:click="closeModals"
                                class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 active:scale-[0.98] text-slate-600 font-bold rounded-xl text-xs transition duration-150 cursor-pointer"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Confirm Project Action Modal -->
            @if($showConfirmModal)
                <div
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    @keydown.escape.window="$wire.closeConfirmModal()"
                    class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                >
                    <div 
                        @click.outside.stop="$wire.closeConfirmModal()"
                        class="bg-white rounded-3xl w-full max-w-sm shadow-xl border border-slate-100/50 overflow-hidden transform transition-all duration-300 scale-100 relative"
                    >
                        <!-- Close Button (X) -->
                        <button 
                            type="button" 
                            wire:click="closeConfirmModal" 
                            wire:loading.attr="disabled"
                            class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 hover:bg-slate-50 p-1.5 rounded-full transition duration-150 cursor-pointer disabled:opacity-50"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"></path></svg>
                        </button>

                        <!-- Top Header with soft icon -->
                        <div class="pt-8 pb-2 flex flex-col items-center justify-center">
                            <div class="w-14 h-14 rounded-full {{ in_array($confirmAction, ['delete', 'force_delete'], true) ? 'bg-rose-50/60 text-rose-500' : 'bg-emerald-50/60 text-emerald-500' }} flex items-center justify-center">
                                @if(in_array($confirmAction, ['delete', 'force_delete'], true))
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"></path></svg>
                                @else
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"></path></svg>
                                @endif
                            </div>
                        </div>

                        <!-- Modal Body -->
                        <div class="px-6 pb-6 pt-3 text-center space-y-2.5">
                            <h3 class="text-base font-sans font-black text-slate-800 tracking-tight">{{ $confirmTitle }}</h3>
                            <p class="text-sm font-bold text-slate-600">{{ $confirmProjectName }}</p>
                            <p class="text-[11px] text-slate-400 leading-relaxed px-1">{{ $confirmMessage }}</p>
                        </div>
                        
                        <!-- Actions Footer -->
                        <div class="px-6 py-4 bg-slate-50/50 border-t border-slate-100/60 flex gap-3">
                            <button
                                type="button"
                                wire:click="closeConfirmModal"
                                wire:loading.attr="disabled"
                                class="flex-1 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 active:scale-[0.98] text-slate-600 font-bold rounded-xl text-xs transition duration-150 cursor-pointer disabled:opacity-50 text-center"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                wire:click="runConfirmedProjectAction"
                                wire:loading.attr="disabled"
                                class="flex-1 py-2.5 {{ in_array($confirmAction, ['delete', 'force_delete'], true) ? 'bg-rose-600 hover:bg-rose-700 shadow-rose-100' : 'bg-[#1fa387] hover:bg-[#1a8b73] shadow-emerald-100' }} text-white font-bold rounded-xl text-xs transition duration-150 active:scale-[0.98] cursor-pointer shadow-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-1.5"
                            >
                                <svg wire:loading wire:target="runConfirmedProjectAction" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span>
                                    {{ match($confirmAction) {
                                        'delete' => 'Nonaktifkan',
                                        'force_delete' => 'Hapus Permanen',
                                        'restore' => 'Aktifkan',
                                        'run_scraping' => 'Jalankan Scraping',
                                        'sync_project' => 'Sinkronkan',
                                        default => 'Konfirmasi'
                                    } }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- User Project Toast -->
            <div
                x-show="toastVisible"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-x-6"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 translate-x-6"
                class="fixed right-6 top-6 z-[70] w-[360px] max-w-[calc(100vw-3rem)] rounded-2xl border shadow-2xl overflow-hidden text-white"
                :class="toastType === 'error' ? 'bg-rose-500 border-rose-600 shadow-rose-900/20' : 'bg-[#1fa387] border-[#188c73] shadow-[#1fa387]/20'"
                style="display: none;"
            >
                <div class="flex items-start gap-3 p-4">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-white/20">
                        <span class="font-black text-lg" x-text="toastType === 'error' ? '!' : '✓'"></span>
                    </div>
                    <div class="min-w-0 pt-0.5">
                        <p class="text-sm font-extrabold" x-text="toastType === 'error' ? 'Aksi gagal' : 'Berhasil'"></p>
                        <p class="mt-0.5 text-xs font-medium opacity-90 leading-relaxed" x-text="toastMessage"></p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="max-w-[1400px] mx-auto px-6 border-t border-slate-200 flex flex-col md:flex-row justify-between items-center gap-4 mt-auto py-6 w-full">
                <p class="text-xs text-slate-400 font-medium">© 2026 Arusbawah Media Intelligence. All rights reserved.</p>
            </footer>
        </div>
</div>
