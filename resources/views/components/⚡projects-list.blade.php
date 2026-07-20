<?php

use Livewire\Component;
use App\Models\Project;
use App\Models\Article;
use App\Models\AiAnalysisResult;
use App\Models\ApifyActor;
use App\Jobs\ApifyScrapingJob;
use App\Services\ContentMatchingService;
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

    // Form fields for new project
    public $name = '';
    public $topicsString = ''; // Reset default to empty string
    public $contextKeywords = '';
    public $excludeKeywords = '';
    public $selectedSources = ['Instagram', 'TikTok', 'Facebook', 'News'];
    public $isCreatingProject = false;
    public $showSuccessModal = false;
    public $lastCreatedProjectName = '';

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
    protected ?array $portalScanTimes = null;
    protected ?array $portalRunningProjectIds = null;
    protected ?array $socialActiveProjects = null;

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
            ->max(DB::raw('coalesce(completed_at, started_at, queued_at)'));
    }

    protected function latestSuccessfulSocialRunForProject(int $projectId): ?string
    {
        return DB::table('apify_dispatch_states')
            ->where('project_id', $projectId)
            ->whereIn(DB::raw('lower(platform)'), ['facebook', 'instagram', 'tiktok'])
            ->where('status', 'success')
            ->max('completed_at');
    }

    protected function latestSocialDataForProject(int $projectId): ?string
    {
        return DB::table('project_social_media_items')
            ->where('project_id', $projectId)
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
            ->withCount(['articles', 'socialMediaItems'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()->map(function($project) {
            $analyzedArticlesQuery = clone $project->articles();
            $analyzedArticlesQuery->whereHas('aiAnalysisResult', function($q) {
                $q->completeOfficialAiResult()
                  ->whereNotNull('summary')
                  ->whereNotNull('sentiment')
                  ->whereNotNull('risk_level');
            });
            $totalAiValid = (clone $analyzedArticlesQuery)->count();

            // Mutually exclusive rescrapeCount (Needs Rescrape if NOT display_ready)
            $rescrapeCount = DB::table('project_articles')
                ->join('articles', 'project_articles.article_id', '=', 'articles.id')
                ->where('project_articles.project_id', $project->id)
                ->where('project_articles.rescrape_status', 'needs_rescrape')
                ->whereNotExists(function($q) {
                    $q->select(DB::raw(1))
                      ->from('ai_analysis_results as ai')
                      ->whereColumn('ai.article_id', 'articles.id')
                      ->where('ai.analysis_status', 'success')
                      ->where('ai.reach_method', 'ai_reader_estimate_v1')
                      ->whereNotNull('ai.project_estimated_readers')
                      ->where('ai.project_estimated_readers', '>=', 1)
                      ->whereNotNull('ai.project_reach_score')
                      ->whereNotNull('ai.project_reach_level')
                      ->whereNotNull('ai.summary')
                      ->whereNotNull('ai.sentiment')
                      ->whereNotNull('ai.risk_level');
                })->count();

            // Mutually exclusive totalAiFailed (Failed / Skipped / Closed if NOT display_ready and NOT needs_rescrape)
            $totalAiFailed = DB::table('project_articles')
                ->join('articles', 'project_articles.article_id', '=', 'articles.id')
                ->leftJoin('ai_analysis_results as ai', 'articles.id', '=', 'ai.article_id')
                ->leftJoin('ai_analysis_dispatch_states as ds', 'articles.id', '=', 'ds.analyzable_id')
                ->where('project_articles.project_id', $project->id)
                ->where(function($q) {
                    $q->whereNull('project_articles.rescrape_status')
                      ->orWhere('project_articles.rescrape_status', '!=', 'needs_rescrape');
                })
                ->whereNotExists(function($q) {
                    $q->select(DB::raw(1))
                      ->from('ai_analysis_results as ai2')
                      ->whereColumn('ai2.article_id', 'articles.id')
                      ->where('ai2.analysis_status', 'success')
                      ->where('ai2.reach_method', 'ai_reader_estimate_v1')
                      ->whereNotNull('ai2.project_estimated_readers')
                      ->where('ai2.project_estimated_readers', '>=', 1)
                      ->whereNotNull('ai2.project_reach_score')
                      ->whereNotNull('ai2.project_reach_level')
                      ->whereNotNull('ai2.summary')
                      ->whereNotNull('ai2.sentiment')
                      ->whereNotNull('ai2.risk_level');
                })
                ->where(function($q) {
                    $q->whereIn('ai.analysis_status', ['failed', 'invalid_ai_reach'])
                      ->orWhereIn('ds.last_error_code', ['empty_content', 'invalid_content', 'orphan_dispatch_state', 'stale_orphan', 'stale_dispatch']);
                })
                ->count();

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

            $mentions = $project->articles_count;
            $reachQuery = AiAnalysisResult::query()
                ->completeOfficialAiResult()
                ->whereIn('article_id', (clone $analyzedArticlesQuery)->select('articles.id'));
            $officialReach = (clone $reachQuery)->sum('project_estimated_readers');
            $hasOfficialReach = (clone $reachQuery)->exists();
            $reach = $hasOfficialReach ? number_format($officialReach, 0, ',', '.') : 'Belum tersedia';

            $lastPortalTime = DB::table('project_articles')
                ->where('project_id', $project->id)
                ->max('created_at');
                
            $lastMedsosTime = $this->latestSocialUpdateForProject($project->id);

            $lastPortalScanTime = $this->latestPortalScanForProject($project->id);
            $lastPortalUpdate = $lastPortalScanTime
                ? \Carbon\Carbon::parse($lastPortalScanTime)->locale('id')->diffForHumans()
                : ($lastPortalTime
                ? \Carbon\Carbon::parse($lastPortalTime)->locale('id')->diffForHumans() 
                : 'Belum ada data');
                
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
            ];
        });
    }

    public function createProject()
    {
        $this->validate([
            'name' => 'required|min:3|unique:projects,name',
            'topicsString' => 'required',
        ]);

        // Validate JSON string
        if (str_starts_with(trim($this->topicsString), '{') || str_starts_with(trim($this->topicsString), '[')) {
            $this->addError('topicsString', 'Format JSON tidak diperbolehkan. Gunakan kata kunci yang dipisahkan koma.');
            return;
        }

        // Parse comma-separated topics
        $topics = $this->parseTopicsString(false);

        if (empty($topics)) {
            $this->addError('topicsString', 'Topik wajib diisi minimal satu kata kunci valid.');
            return;
        }

        $project = Project::create([
            'name' => $this->name,
            'topics' => array_values($topics),
        ]);

        // Auto-assign project to the creator if they are a regular user
        $user = auth()->user();
        if ($user && !$user->isAdmin()) {
            $project->users()->attach($user->id);
        }

        $matchResult = app(ContentMatchingService::class)->matchExistingContentForProject($project);

        $this->lastCreatedProjectName = $this->name;
        $this->showSuccessModal = true;
        session()->flash(
            'message',
            'Proyek berhasil dibuat. Data lama yang cocok: '
            . ($matchResult['articles_linked'] ?? 0) . ' artikel, '
            . ($matchResult['social_linked'] ?? 0) . ' medsos.'
        );
        $this->notifyProjectAction(
            'Proyek berhasil dibuat. Data lama yang cocok: '
            . ($matchResult['articles_linked'] ?? 0) . ' artikel, '
            . ($matchResult['social_linked'] ?? 0) . ' medsos.'
        );
        
        $this->reset(['name', 'topicsString', 'contextKeywords', 'excludeKeywords']);
        $this->selectedSources = ['Instagram', 'TikTok', 'Facebook', 'News'];
    }

    public function editProject($id)
    {
        $project = Project::accessibleBy(auth()->user())->findOrFail($id);
        $this->editProjectId = $project->id;
        $this->editName = $project->name;
        $this->editTopicsString = implode(', ', $project->topics ?? []);
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
            'name'   => $this->editName,
            'topics' => $topics,
        ]);

        $matchingService = app(ContentMatchingService::class);
        $matchResult = $matchingService->matchExistingContentForProject($project);
        $socialSyncResult = $matchingService->syncProjectSocialContent($project);

        $this->showEditModal = false;
        $this->editProjectId = null;
        session()->flash(
            'message',
            'Proyek berhasil diperbarui. Data lama yang cocok: '
            . ($matchResult['articles_linked'] ?? 0) . ' artikel, '
            . ($matchResult['social_linked'] ?? 0) . ' medsos. '
            . 'Social disinkronkan ulang: '
            . ($socialSyncResult['attached'] ?? 0) . ' tertaut, '
            . ($socialSyncResult['detached'] ?? 0) . ' dilepas.'
        );
        $this->notifyProjectAction(
            'Proyek berhasil diperbarui. Data lama yang cocok: '
            . ($matchResult['articles_linked'] ?? 0) . ' artikel, '
            . ($matchResult['social_linked'] ?? 0) . ' medsos. '
            . 'Social disinkronkan ulang: '
            . ($socialSyncResult['attached'] ?? 0) . ' tertaut, '
            . ($socialSyncResult['detached'] ?? 0) . ' dilepas.'
        );
    }

    // Trashed projects modal state
    public $showTrashedModal = false;

    public function closeModals()
    {
        $this->showSuccessModal = false;
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

            \Illuminate\Support\Facades\DB::table('project_articles')
                ->where('project_id', $project->id)
                ->delete();

            \Illuminate\Support\Facades\DB::table('project_social_media_items')
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
    class="{{ $projectId ? 'h-full flex flex-col overflow-hidden' : '' }}"
    x-data="{
        showSuccess: @entangle('showSuccessModal'),
        showEdit: @entangle('showEditModal'),
        showTrashed: @entangle('showTrashedModal'),
        showConfirm: @entangle('showConfirmModal'),
        toastVisible: false,
        toastType: @entangle('toastType'),
        toastMessage: @entangle('toastMessage'),
        toastTimer: null,
        updateScrollLock() {
            document.body.style.overflow = (this.showSuccess || this.showEdit || this.showTrashed || this.showConfirm) ? 'hidden' : '';
        },
        showToast(message = null, type = null) {
            if (message) this.toastMessage = message;
            if (type) this.toastType = type;
            if (!this.toastMessage) return;
            this.toastVisible = true;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => this.toastVisible = false, 3000);
        }
    }"
    x-effect="updateScrollLock()"
    x-init="
        updateScrollLock();
        window.addEventListener('project-scroll-unlock', () => { document.body.style.overflow = ''; });
        window.addEventListener('project-action-toast', event => showToast(event.detail?.message, event.detail?.type));
        $cleanup(() => {
            clearTimeout(toastTimer);
            document.body.style.overflow = '';
        });
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
                                    <polygon points="21,4 39,38 3,38" fill="none" stroke="#c0392b" stroke-width="4" stroke-linejoin="round"/>
                                    <line x1="11" y1="28" x2="31" y2="28" stroke="#c0392b" stroke-width="4" stroke-linecap="round"/>
                                </svg>
                            @endif
                            <div class="flex flex-col text-left">
                                <span class="text-sm font-black tracking-wider leading-none text-slate-800 uppercase">{{ \App\Helpers\AppBrandingHelper::getAppName() }}</span>
                                <span class="text-[7.5px] font-bold text-slate-400 uppercase tracking-widest leading-none mt-0.5">Media Intelligence</span>
                            </div>
                        </a>


                    </div>

                    <!-- User Profile and Actions -->
                    <div class="ml-auto flex shrink-0 items-center gap-4">
                        <!-- Notifikasi Dropdown Component -->
                        <livewire:notification-dropdown />
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

                @if($isCreatingProject)
                    <!-- Back to Projects Link -->
                    <div class="mb-6 max-w-3xl mx-auto">
                        <button 
                            wire:click="$set('isCreatingProject', false)"
                            class="flex items-center gap-2 text-slate-500 hover:text-slate-800 text-sm font-medium transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            <span>Daftar Proyek</span>
                        </button>
                    </div>

                    <!-- Full Page Add Project Form (matching screenshot) -->
                    <div class="bg-white border border-[#E0E0E0] rounded-2xl shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)] max-w-3xl mx-auto overflow-hidden">
                        <div class="p-8 space-y-6">
                            <div>
                                <h2 class="text-xl font-hanken font-bold text-slate-900 mb-1">Daftar Proyek</h2>
                                <p class="text-slate-500 text-sm">Atur parameter pencarian untuk mulai mengumpulkan data yang relevan.</p>
                            </div>

                            <div class="w-full border-b border-slate-200"></div>

                            <form wire:submit.prevent="createProject" class="space-y-6">
                                <!-- Project Name Field -->
                                <div class="space-y-2">
                                    <label class="text-sm font-bold text-slate-800 block">Nama Proyek</label>
                                    <input 
                                        wire:model="name" 
                                        type="text" 
                                        placeholder="Contoh: Analisis Sentimen BUMN 2025"
                                        class="w-full bg-[#F8F9FA] border border-slate-350 focus:border-primary focus:ring-1 focus:ring-primary rounded-custom px-4 py-3 text-sm text-slate-850 placeholder-[#727785] transition"
                                    >
                                    @error('name') <span class="text-red-500 text-xs font-medium">{{ $message }}</span> @enderror
                                </div>

                                <!-- Main Keywords Field -->
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <label class="text-sm font-bold text-slate-800 block">Kata Kunci Utama</label>
                                        <span class="px-2.5 py-0.5 text-[10px] font-bold bg-red-50 text-red-500 border border-red-100 rounded-full">Wajib</span>
                                    </div>
                                    <p class="text-xs text-slate-400">
                                        Kata kunci atau frasa utama untuk proyek Anda. Penyebutan yang mengandung kata kunci ini akan dikumpulkan.
                                    </p>
                                    <input 
                                        wire:model="topicsString" 
                                        type="text" 
                                        placeholder="Contoh: Nike, Adidas, Puma"
                                        class="w-full bg-[#F8F9FA] border border-slate-350 focus:border-primary focus:ring-1 focus:ring-primary rounded-custom px-4 py-3 text-sm text-slate-850 placeholder-[#727785] transition"
                                    >
                                    <p class="text-[10px] text-slate-400 mt-1">Tidak peka huruf besar/kecil. Pisahkan dengan Koma atau tekan Enter untuk banyak kata kunci.</p>
                                    <div class="mt-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-4" x-data="{
                                        topics() {
                                            return $wire.topicsString ? $wire.topicsString.split(',').map(t => t.trim()).filter(Boolean) : [];
                                        },
                                        toHashtag(topic) {
                                            const clean = topic
                                                .replace(/^#+/, '')
                                                .replace(/['’‘`]/g, '')
                                                .replace(/\s+/g, '');
                                            return clean ? `#${clean}` : '';
                                        }
                                    }">
                                        <div class="flex items-center justify-between gap-3 mb-3">
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Preview Hashtag</span>
                                            <span class="text-[10px] font-semibold text-slate-500">Hasil akhir saat disimpan</span>
                                        </div>
                                        <div class="flex flex-wrap gap-2 text-xs">
                                            <template x-for="topic in topics()" :key="topic">
                                                <span
                                                    class="px-3 py-1.5 rounded-full border border-[#1fa387]/20 bg-[#1fa387]/5 text-[#1fa387] font-bold"
                                                    x-text="toHashtag(topic)"
                                                ></span>
                                            </template>
                                            <span x-show="!$wire.topicsString" class="text-xs text-slate-400 italic">Belum ada keyword.</span>
                                        </div>
                                    </div>
                                    @error('topicsString') <span class="text-red-500 text-xs font-medium">{{ $message }}</span> @enderror
                                </div>

                                <!-- Advanced Settings Accordion -->
                                <div x-data="{ open: false }" class="border border-slate-200 rounded-2xl overflow-hidden text-left bg-white shadow-sm">
                                    <button 
                                        type="button"
                                        @click="open = !open" 
                                        class="w-full flex items-center justify-between px-6 py-4 bg-[#F8F9FA] text-[#1fa387] hover:text-[#1fa387]/90 text-sm font-semibold transition-all border-b border-slate-100"
                                    >
                                        <span class="flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                                            <span>Pengaturan Lanjutan</span>
                                        </span>
                                        <svg class="w-4 h-4 text-[#1fa387] transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    
                                    <div x-show="open" class="p-6 space-y-6" x-data="{
                                        getTags() {
                                            return $wire.topicsString ? $wire.topicsString.split(',').map(t => t.trim()).filter(Boolean) : [];
                                        }
                                    }">
                                        <!-- Detail Filter Kata Kunci -->
                                        <div class="space-y-4">
                                            <div class="flex justify-between items-center">
                                                <h4 class="text-xs font-bold text-slate-800">Detail Filter Kata Kunci</h4>
                                            </div>

                                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 bg-slate-50/50 border border-slate-100 rounded-2xl p-6 relative">
                                                <!-- Trash bin delete icon -->
                                                <button type="button" @click="$wire.topicsString = ''; $wire.contextKeywords = ''; $wire.excludeKeywords = '';" class="absolute top-4 right-4 text-slate-400 hover:text-red-500 transition-colors" title="Hapus Filter">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                                                </button>

                                                <!-- UTAMA Column -->
                                                <div class="space-y-2">
                                                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">UTAMA</label>
                                                    <div class="flex flex-wrap gap-2 min-h-[44px] items-center">
                                                        <template x-for="tag in getTags()" :key="tag">
                                                            <span class="px-3 py-1 bg-[#1fa387]/5 text-[#1fa387] border border-[#1fa387]/20 rounded-xl text-xs font-bold" x-text="tag"></span>
                                                        </template>
                                                        <span x-show="getTags().length === 0" class="text-xs text-slate-400 italic">Belum ada kata kunci...</span>
                                                    </div>
                                                </div>

                                                <!-- Konteks Column -->
                                                <div class="space-y-2">
                                                    <div class="flex items-center gap-1.5">
                                                        <label class="text-xs font-bold text-slate-800">Konteks</label>
                                                        <span class="text-[9px] font-bold bg-slate-100 text-slate-400 px-1.5 py-0.5 rounded-full uppercase">Opsional</span>
                                                    </div>
                                                    <p class="text-[10px] text-slate-400 leading-tight">Semua kata kunci ini harus muncul agar penyebutan dikumpulkan.</p>
                                                    <input 
                                                        wire:model="contextKeywords" 
                                                        type="text" 
                                                        placeholder="Misal: shoes"
                                                        class="w-full bg-white border border-slate-200 focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387] rounded-xl px-3 py-2 text-xs text-slate-800 placeholder-slate-400 transition"
                                                    >
                                                    <p class="text-[9px] text-slate-400">Pisahkan dengan koma.</p>
                                                </div>

                                                <!-- Dikecualikan Column -->
                                                <div class="space-y-2">
                                                    <div class="flex items-center gap-1.5">
                                                        <label class="text-xs font-bold text-slate-800">Dikecualikan</label>
                                                        <span class="text-[9px] font-bold bg-slate-100 text-slate-400 px-1.5 py-0.5 rounded-full uppercase">Opsional</span>
                                                    </div>
                                                    <p class="text-[10px] text-slate-400 leading-tight">Penyebutan tidak akan dikumpulkan jika mengandung kata kunci ini.</p>
                                                    <input 
                                                        wire:model="excludeKeywords" 
                                                        type="text" 
                                                        placeholder="Misal: fake"
                                                        class="w-full bg-white border border-slate-200 focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387] rounded-xl px-3 py-2 text-xs text-slate-800 placeholder-slate-400 transition"
                                                    >
                                                    <p class="text-[9px] text-slate-400">Pisahkan dengan koma.</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Sumber Data Section -->
                                        <div class="space-y-4 pt-4 border-t border-slate-100">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <h4 class="text-xs font-bold text-slate-800">Sumber Data</h4>
                                                    <p class="text-[10px] text-slate-400">Pilih satu atau lebih sumber media yang ingin dipantau</p>
                                                </div>
                                                <button type="button" @click="$wire.selectedSources = []" class="text-xs text-red-500 font-bold hover:underline cursor-pointer">Hapus Semua</button>
                                            </div>

                                            <div class="space-y-4" x-data="{
                                                toggleSource(source) {
                                                    let list = [...$wire.selectedSources];
                                                    if (list.includes(source)) {
                                                        list = list.filter(s => s !== source);
                                                    } else {
                                                        list.push(source);
                                                    }
                                                    $wire.selectedSources = list;
                                                }
                                            }">
                                                <label class="flex items-center justify-between gap-3 cursor-pointer group">
                                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                                        <input wire:model.live="selectedSources" value="Instagram" type="checkbox" class="rounded border-blue-300 text-blue-600 focus:ring-blue-500 w-5 h-5">
                                                        <div class="w-10 h-10 rounded-xl bg-fuchsia-500 shadow-sm shadow-fuchsia-500/20 flex items-center justify-center shrink-0" style="background-color: #e1306c;">
                                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                                <rect x="4.25" y="4.25" width="15.5" height="15.5" rx="5"></rect>
                                                                <circle cx="12" cy="12" r="3.15"></circle>
                                                                <circle cx="17.1" cy="6.9" r="1.05" fill="currentColor" stroke="none"></circle>
                                                            </svg>
                                                        </div>
                                                        <span class="text-sm font-semibold text-slate-700 truncate">Instagram</span>
                                                    </div>
                                                    <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">0</span>
                                                </label>

                                                <label class="flex items-center justify-between gap-3 cursor-pointer group">
                                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                                        <input wire:model.live="selectedSources" value="TikTok" type="checkbox" class="rounded border-blue-300 text-blue-600 focus:ring-blue-500 w-5 h-5">
                                                        <div class="w-10 h-10 rounded-xl bg-slate-950 shadow-sm shadow-slate-900/20 flex items-center justify-center shrink-0" style="background-color: #000000;">
                                                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24">
                                                                <path fill="currentColor" d="M15.8 5.2c.7.8 1.7 1.4 2.8 1.6v2.7c-1 0-2-.2-2.9-.6v5.1c0 2.9-2.4 5.3-5.3 5.3S5.1 17 5.1 14.1s2.4-5.3 5.3-5.3c.2 0 .4 0 .6.1v2.8c-.2 0-.4-.1-.6-.1-1.3 0-2.3 1.1-2.3 2.4s1 2.4 2.3 2.4 2.4-1 2.4-2.4V4.4h2.9c.1.3.1.5.1.8z"/>
                                                                <path fill="currentColor" d="M15.6 4.4c.2.9.7 1.8 1.4 2.5.8.7 1.6 1.2 2.6 1.4V5.6c-.7-.2-1.3-.5-1.8-.9-.5-.4-1-.9-1.3-1.5h-.9z"/>
                                                            </svg>
                                                        </div>
                                                        <span class="text-sm font-semibold text-slate-700 truncate">TikTok</span>
                                                    </div>
                                                    <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">29</span>
                                                </label>

                                                <label class="flex items-center justify-between gap-3 cursor-pointer group">
                                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                                        <input wire:model.live="selectedSources" value="Facebook" type="checkbox" class="rounded border-blue-300 text-blue-600 focus:ring-blue-500 w-5 h-5">
                                                        <div class="w-10 h-10 rounded-xl bg-blue-600 shadow-sm shadow-blue-600/20 flex items-center justify-center shrink-0">
                                                            <svg class="w-5 h-5 fill-current text-white" viewBox="0 0 24 24">
                                                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path>
                                                            </svg>
                                                        </div>
                                                        <span class="text-sm font-semibold text-slate-700 truncate">Facebook</span>
                                                    </div>
                                                    <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">60</span>
                                                </label>

                                                <label class="flex items-center justify-between gap-3 cursor-pointer group">
                                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                                        <input wire:model.live="selectedSources" value="Portal" type="checkbox" class="rounded border-blue-300 text-blue-600 focus:ring-blue-500 w-5 h-5">
                                                        <div class="w-10 h-10 rounded-xl bg-emerald-500 shadow-sm shadow-emerald-500/20 flex items-center justify-center shrink-0">
                                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                                                            </svg>
                                                        </div>
                                                        <span class="text-sm font-semibold text-slate-700 truncate">Portal News</span>
                                                    </div>
                                                    <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">176</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer buttons (Greenish Qola style) -->
                                <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                                    <!-- Batal Button -->
                                    <button 
                                        type="button" 
                                        wire:click="$set('isCreatingProject', false)"
                                        class="px-6 py-2.5 bg-[#1fa387] hover:bg-[#1a8b73] text-white font-bold rounded-custom text-sm transition-all"
                                    >
                                        Batal
                                    </button>
                                    <!-- Buat Proyek Button -->
                                    <button 
                                        type="submit" 
                                        class="px-6 py-2.5 bg-[#1fa387] hover:bg-[#1a8b73] text-white font-bold rounded-custom text-sm transition-all shadow-sm"
                                    >
                                        Buat Proyek
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @else
                    <!-- Title Section -->
                    <section class="mb-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-hanken font-bold text-slate-900 mb-1">Daftar Proyek Anda</h1>
                            <p class="text-slate-500 text-sm">Kelola dan pantau seluruh kampanye media monitoring Anda secara real-time.</p>
                        </div>
                        <div>
                            <button 
                                wire:click="$set('showTrashedModal', true)"
                                class="px-4 py-2 border border-slate-300 hover:border-slate-400 text-slate-600 hover:text-slate-800 rounded-xl text-sm font-semibold transition bg-white shadow-sm flex items-center gap-2 cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                                <span>Lihat Proyek Dihapus</span>
                            </button>
                        </div>
                    </section>

                    @php
                        $projects = $this->getProjects();
                    @endphp

                    @if(!auth()->user()?->isAdmin() && empty($projects))
                        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-600 shadow-sm">
                            Belum ada project yang diberikan ke akun Anda.
                        </div>
                    @endif

                    <!-- Project Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-start">
                        <!-- Create Project Card -->
                        @if(auth()->check())
                            <div 
                                wire:click="$set('isCreatingProject', true)"
                                class="dashed-border bg-white rounded-2xl border-2 border-dashed border-slate-300 p-6 flex flex-col self-start min-h-[880px] cursor-pointer hover:bg-white/50 transition-all duration-300 shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)] hover:shadow-[0_8px_30px_rgba(0,0,0,0.06)]"
                            >
                                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 4v16m8-8H4" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                    </svg>
                                </div>
                                <h3 class="text-xl font-hanken font-bold text-slate-800 mb-2">Buat Proyek Baru</h3>
                                <p class="text-slate-400 text-sm max-w-[220px] leading-relaxed">
                                    Tambahkan monitoring media online, cetak, dan media sosial baru
                                </p>
                            </div>
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
                                class="bg-white rounded-2xl border border-slate-200 p-6 flex flex-col self-start shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)] hover:shadow-[0_8px_30px_rgba(0,0,0,0.06)] transition-all"
                            >
                                <!-- Card Header -->
                                <div class="flex items-start justify-between mb-8">
                                    <div class="flex items-center gap-3">
                                        <div class="px-2 py-1 rounded bg-primary/10 text-primary font-bold text-[10px] tracking-widest border border-primary/20">
                                            {{ sprintf('%02d', $idx + 1) }}
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-1.5 mb-0.5">
                                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">PROYEK</span>
                                                <span class="text-[9px] text-slate-300">•</span>
                                                <span class="text-[9px] text-slate-400 font-bold">Dibuat: {{ $projectCreatedAt }}</span>
                                            </div>
                                            <h2 class="text-xl font-hanken font-extrabold text-[#1fa387] uppercase leading-tight">{{ $project['name'] }}</h2>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <!-- Edit Button -->
                                        <button 
                                            wire:click="editProject({{ $project['id'] }})"
                                            title="Edit Proyek"
                                            class="text-slate-300 hover:text-blue-500 transition-colors cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                            </svg>
                                        </button>
                                        <!-- Delete Button -->
                                        <button 
                                            wire:click="confirmDeleteProject({{ $project['id'] }})"
                                            title="Nonaktifkan Proyek"
                                            class="text-slate-300 hover:text-red-500 transition-colors cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                            </svg>
                                        </button>
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
                @endif
            </main>

            <!-- Success Modal Overlay -->
            @if($showSuccessModal)
                <div 
                    x-data="{ show: true }" 
                    x-show="show"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                >
                    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden p-8 border border-slate-100 text-center space-y-6">
                        <!-- Checkmark Icon -->
                        <div class="mx-auto w-12 h-12 bg-[#1fa387] text-white rounded-full flex items-center justify-center shadow-sm shadow-[#1fa387]/20">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>

                        <div class="space-y-2">
                            <h3 class="text-lg font-bold text-slate-800">Berhasil</h3>
                            <p class="text-sm text-slate-500 leading-relaxed max-w-xs mx-auto">
                                Proyek "<span class="font-bold text-slate-800">{{ $lastCreatedProjectName }}</span>" telah berhasil dibuat dan siap untuk dipantau.
                            </p>
                        </div>

                        <div class="pt-2 border-t border-slate-100">
                            <button 
                                type="button" 
                                wire:click="closeModals"
                                class="px-6 py-2.5 bg-[#1fa387] hover:bg-[#1a8b73] text-white font-bold rounded-custom text-sm transition-all"
                            >
                                OK, Terima Kasih
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Edit Project Modal -->
            @if($showEditModal)
                <div
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                >
                    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl border border-slate-100 overflow-hidden">
                        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="text-base font-hanken font-bold text-slate-900">Edit Proyek</h3>
                            <button wire:click="closeModals" class="text-slate-400 hover:text-slate-600 transition-colors cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                            </button>
                        </div>
                        <form wire:submit.prevent="updateProject" class="px-8 py-6 space-y-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nama Proyek</label>
                                <input
                                    type="text"
                                    wire:model="editName"
                                    class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
                                    placeholder="Nama proyek"
                                />
                                @error('editName')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Topik / Kata Kunci</label>
                                <input
                                    type="text"
                                    wire:model="editTopicsString"
                                    class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
                                    placeholder="pisahkan dengan koma, contoh: pilkada, banjir jakarta"
                                />
                                <p class="mt-1 text-xs text-slate-400">Pisahkan beberapa kata kunci dengan koma. Saat disimpan, keyword akan dinormalisasi ke hashtag.</p>
                                <div class="mt-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-4" x-data="{
                                    topics() {
                                        return $wire.editTopicsString ? $wire.editTopicsString.split(',').map(t => t.trim()).filter(Boolean) : [];
                                    },
                                    toHashtag(topic) {
                                        const clean = topic
                                            .replace(/^#+/, '')
                                            .replace(/['’‘`]/g, '')
                                            .replace(/\s+/g, '');
                                        return clean ? `#${clean}` : '';
                                    }
                                }">
                                    <div class="flex items-center justify-between gap-3 mb-3">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Preview Hashtag</span>
                                        <span class="text-[10px] font-semibold text-slate-500">Hasil akhir saat disimpan</span>
                                    </div>
                                    <div class="flex flex-wrap gap-2 text-xs">
                                        <template x-for="topic in topics()" :key="topic">
                                            <span class="px-3 py-1.5 rounded-full border border-[#1fa387]/20 bg-[#1fa387]/5 text-[#1fa387] font-bold" x-text="toHashtag(topic)"></span>
                                        </template>
                                        <span x-show="!$wire.editTopicsString" class="text-xs text-slate-400 italic">Belum ada keyword.</span>
                                    </div>
                                </div>
                                @error('editTopicsString')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex gap-3 pt-2">
                                <button
                                    type="submit"
                                    style="background-color: #1fa387;"
                                    class="flex-1 px-6 py-3 text-white font-bold rounded-xl text-sm transition-all hover:opacity-90"
                                >
                                    Simpan Perubahan
                                </button>
                                <button
                                    type="button"
                                    wire:click="closeModals"
                                    class="px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition-all"
                                >
                                    Batal
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

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
                    <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl border border-slate-100 overflow-hidden">
                        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="text-base font-hanken font-bold text-slate-900">Daftar Proyek Dihapus</h3>
                            <button wire:click="closeModals" class="text-slate-400 hover:text-slate-600 transition-colors cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                            </button>
                        </div>
                        <div class="p-8 space-y-4 max-h-[400px] overflow-y-auto">
                            @php
                                $trashed = $this->getTrashedProjects();
                            @endphp
                            @if($trashed->isEmpty())
                                <p class="text-slate-500 text-sm text-center py-6">Tidak ada proyek yang dinonaktifkan.</p>
                            @else
                                <div class="divide-y divide-slate-100">
                                    @foreach($trashed as $tp)
                                        <div class="py-4 flex items-center justify-between first:pt-0 last:pb-0 gap-4">
                                            <div class="text-left flex-1 min-w-0">
                                                <h4 class="text-sm font-bold text-slate-800">{{ $tp->name }}</h4>
                                                <p class="text-xs text-slate-400 mt-1">Kata kunci: {{ implode(', ', $tp->topics ?? []) }}</p>
                                                <p class="text-[10px] text-slate-400 mt-0.5">Dinonaktifkan pada: {{ $tp->deleted_at->format('Y-m-d H:i:s') }}</p>
                                            </div>
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <button
                                                    wire:click="confirmRestoreProject({{ $tp->id }})"
                                                    style="background-color: #1fa387;"
                                                    class="px-4 py-2 text-white text-xs font-bold rounded-xl hover:opacity-90 transition cursor-pointer"
                                                >
                                                    Aktifkan
                                                </button>
                                                <button
                                                    wire:click="confirmForceDeleteProject({{ $tp->id }})"
                                                    class="px-4 py-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-xl hover:bg-rose-100 transition cursor-pointer"
                                                >
                                                    Hapus
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="px-8 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                            <button
                                type="button"
                                wire:click="closeModals"
                                class="px-5 py-2 bg-slate-200 hover:bg-slate-350 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer"
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
                    @keydown.escape.window="$wire.closeModals()"
                    class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-950/30 backdrop-blur-sm"
                >
                    <div 
                        @click.outside="$wire.closeModals()"
                        class="bg-white rounded-3xl w-full max-w-sm shadow-xl border border-slate-100/50 overflow-hidden transform transition-all duration-300 scale-100 relative"
                    >
                        <!-- Close Button (X) -->
                        <button 
                            type="button" 
                            wire:click="closeModals" 
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
                                wire:click="closeModals"
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
                                <span>{{ $confirmAction === 'delete' ? 'Nonaktifkan' : ($confirmAction === 'force_delete' ? 'Hapus Permanen' : 'Aktifkan') }}</span>
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
                class="fixed right-6 top-6 z-[70] w-[360px] max-w-[calc(100vw-3rem)] rounded-2xl bg-white border border-slate-100 shadow-2xl shadow-slate-900/10 overflow-hidden"
                style="display: none;"
            >
                <div class="flex items-start gap-3 p-4">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0" :class="toastType === 'error' ? 'bg-rose-50 text-rose-600' : 'bg-[#1fa387]/10 text-[#1fa387]'">
                        <span class="font-black text-lg" x-text="toastType === 'error' ? '!' : '✓'"></span>
                    </div>
                    <div class="min-w-0 pt-0.5">
                        <p class="text-sm font-extrabold text-slate-900" x-text="toastType === 'error' ? 'Aksi gagal' : 'Berhasil'"></p>
                        <p class="mt-0.5 text-xs font-medium text-slate-500 leading-relaxed" x-text="toastMessage"></p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="max-w-[1400px] mx-auto px-6 border-t border-slate-200 flex flex-col md:flex-row justify-between items-center gap-4 mt-auto py-6 w-full">
                <p class="text-xs text-slate-400 font-medium">© 2026 Arusbawah Media Intelligence. All rights reserved.</p>
            </footer>
        </div>
    @endif
</div>
