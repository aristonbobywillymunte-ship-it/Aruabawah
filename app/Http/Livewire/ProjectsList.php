<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Project;
use App\Models\Article;
use App\Models\SocialMediaItem;
use App\Models\AiAnalysisResult;
use App\Jobs\BootstrapNewProjectScrapingJob;
use App\Services\ContentMatchingService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class ProjectsList extends Component
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

    protected function parseOptionalKeywordString(string $value): array
    {
        $items = array_map('trim', explode(',', $value));
        $items = array_filter($items);

        return array_values(array_unique($items));
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
        $project = Project::accessibleBy(auth()->user())->where('is_active', true)->find($projectId);

        if (! $project) {
            return null;
        }

        $query = SocialMediaItem::query()
            ->where(function ($contentQuery) use ($project) {
                foreach ($project->scrapeKeywordVariants() as $index => $keyword) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $contentQuery->{$method}(function ($inner) use ($keyword) {
                        $inner->where('content', 'ilike', '%' . $keyword . '%')
                            ->orWhere('raw_json', 'ilike', '%' . $keyword . '%')
                            ->orWhere('author_name', 'ilike', '%' . $keyword . '%');
                    });
                }
            });

        foreach ($project->scrapeExcludeKeywords() as $keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->whereRaw('LOWER(COALESCE(content, \'\')) NOT LIKE ?', ['%' . strtolower($keyword) . '%'])
                  ->whereRaw('LOWER(COALESCE(raw_json, \'\')) NOT LIKE ?', ['%' . strtolower($keyword) . '%'])
                  ->whereRaw('LOWER(COALESCE(author_name, \'\')) NOT LIKE ?', ['%' . strtolower($keyword) . '%']);
            });
        }

        return $query->max('posted_at');
    }

    protected function latestSocialUpdateForProject(int $projectId): ?string
    {
        return $this->latestSuccessfulSocialRunForProject($projectId)
            ?? $this->latestSocialRunForProject($projectId)
            ?? $this->latestSocialDataForProject($projectId);
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
            return 'Mengecek ' . ucfirst($platforms[0]) . '...';
        }

        return 'Mengecek ' . $count . ' kanal...';
    }

    public function getProjects()
    {
        return Project::accessibleBy(auth()->user())
            ->where('is_active', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()->map(function($project) {
            $matchedCounts = app(ContentMatchingService::class)->countMatchingContentForProject($project);
            $primaryKeywords = $project->scrapeKeywordVariants();
            $contextKeywords = $project->scrapeContextKeywordVariants();
            $matchKeywords = array_values(array_unique(array_filter(array_merge($primaryKeywords, $contextKeywords))));
            $articleQuery = Article::query()
                ->withCompleteOfficialAiResult()
                ->where(function ($contentQuery) use ($matchKeywords) {
                    foreach ($matchKeywords as $index => $keyword) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $contentQuery->{$method}(function ($inner) use ($keyword) {
                        $inner->where('title', 'ilike', '%' . $keyword . '%')
                            ->orWhere('content', 'ilike', '%' . $keyword . '%')
                            ->orWhere('excerpt', 'ilike', '%' . $keyword . '%')
                            ->orWhere('articles.summary', 'ilike', '%' . $keyword . '%');
                    });
                }
            });

            foreach ($project->scrapeExcludeKeywords() as $keyword) {
                $articleQuery->where(function ($q) use ($keyword) {
                    $q->whereRaw('LOWER(COALESCE(title, \'\')) NOT LIKE ?', ['%' . strtolower($keyword) . '%'])
                      ->whereRaw('LOWER(COALESCE(content, \'\')) NOT LIKE ?', ['%' . strtolower($keyword) . '%']);
                });
            }

            $analyzedArticlesQuery = clone $articleQuery;
            $analyzedArticlesQuery->whereHas('aiAnalysisResult', function($q) {
                $q->completeOfficialAiResult()
                  ->whereNotNull('summary')
                  ->whereNotNull('sentiment')
                  ->whereNotNull('risk_level');
            });
            $totalAiValid = (clone $analyzedArticlesQuery)->count();

            // Mutually exclusive rescrapeCount (Needs Rescrape if NOT display_ready)
            $rescrapeCount = 0;

            // Mutually exclusive totalAiFailed (Failed / Skipped / Closed if NOT display_ready and NOT needs_rescrape)
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

            $lastPortalTime = (clone $articleQuery)->max('published_at');
            $lastMedsosTime = \App\Models\SocialMediaItem::query()
                ->where(function ($contentQuery) use ($project) {
                    foreach ($project->scrapeKeywordVariants() as $index => $keyword) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $contentQuery->{$method}(function ($inner) use ($keyword) {
                            $inner->where('content', 'ilike', '%' . $keyword . '%')
                                ->orWhere('raw_json', 'ilike', '%' . $keyword . '%')
                                ->orWhere('author_name', 'ilike', '%' . $keyword . '%');
                        });
                    }
                })
                ->where(function ($q) use ($project) {
                    foreach ($project->scrapeExcludeKeywords() as $keyword) {
                        $q->whereRaw('LOWER(COALESCE(content, \'\')) NOT LIKE ?', ['%' . strtolower($keyword) . '%'])
                          ->whereRaw('LOWER(COALESCE(raw_json, \'\')) NOT LIKE ?', ['%' . strtolower($keyword) . '%'])
                          ->whereRaw('LOWER(COALESCE(author_name, \'\')) NOT LIKE ?', ['%' . strtolower($keyword) . '%']);
                    }
                })
                ->max('posted_at');

            $lastPortalScanTime = $this->latestPortalScanForProject($project->id);
            $lastPortalUpdate = $lastPortalScanTime
                ? \Carbon\Carbon::parse($lastPortalScanTime)->locale('id')->diffForHumans()
                : ($lastPortalTime
                ? \Carbon\Carbon::parse($lastPortalTime)->locale('id')->diffForHumans() 
                : 'Belum ada data');
                
            $lastMedsosRunAt = $this->latestSuccessfulSocialRunForProject($project->id)
                ?? $this->latestSocialRunForProject($project->id)
                ?? $lastMedsosTime;

            $lastMedsosUpdate = $lastMedsosRunAt
                ? \Carbon\Carbon::parse($lastMedsosRunAt)->locale('id')->diffForHumans()
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
        })->toArray();
    }

    public function mount()
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
        $query = Project::accessibleBy(auth()->user())->where('is_active', true);

        if ($projectId) {
            $project = (clone $query)->find($projectId);
            abort_unless($project, 403, 'Anda tidak memiliki akses ke project ini.');

            return $project->id;
        }

        return null;
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
        $topics = array_map('trim', explode(',', $this->topicsString));
        $topics = array_filter($topics); // remove empty elements
        $topics = array_unique($topics); // remove duplicates
        $topics = array_values($topics);

        if (empty($topics)) {
            $this->addError('topicsString', 'Topik wajib diisi minimal satu kata kunci valid.');
            return;
        }

        $project = Project::create([
            'name' => $this->name,
            'topics' => array_values($topics),
            'context_keywords' => $this->parseOptionalKeywordString((string) $this->contextKeywords),
            'exclude_keywords' => $this->parseOptionalKeywordString((string) $this->excludeKeywords),
        ]);

        // Auto-assign project to the creator if they are a regular user
        $user = auth()->user();
        if ($user && !$user->isAdmin()) {
            $project->users()->attach($user->id);
        }

        $resyncResult = app(ContentMatchingService::class)->resyncProjectContent($project);
        BootstrapNewProjectScrapingJob::dispatch($project->id)->onQueue('news');

        $this->lastCreatedProjectName = $this->name;
        $this->showSuccessModal = true;
        session()->flash(
            'message',
            'Proyek berhasil dibuat. Data lama yang sesuai filter: '
            . (($resyncResult['match']['articles_linked'] ?? 0)) . ' artikel, '
            . (($resyncResult['match']['social_linked'] ?? 0)) . ' medsos.'
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

        $topics = array_values(array_unique(array_filter(array_map('trim', explode(',', $this->editTopicsString)))));

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

    public function render()
    {
        return view('components.⚡projects-list');
    }
}
