<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Project;
use App\Models\AiAnalysisResult;
use App\Services\ContentMatchingService;
use Illuminate\Support\Facades\DB;

/**
 * @deprecated Legacy Livewire class retained for tests/backward compatibility only.
 * UI aktif memakai `resources/views/components/⚡projects-list.blade.php` (Volt/inline component).
 */
class ProjectsList extends Component
{
    public bool $isCreatingProject = false;
    public bool $showSuccessModal  = false;
    public string $lastCreatedProjectName = '';
    public $projectId = null;

    // Form fields
    public string $name        = '';
    public string $description = '';
    public string $topicsString    = '';
    public string $contextKeywords = '';
    public string $excludeKeywords = '';
    public array  $selectedSources = [];

    protected $rules = [
        'name'         => 'required|string|max:255',
        'topicsString' => 'required|string',
    ];

    protected function currentUser()
    {
        return auth()->user();
    }

    protected function isAdmin(): bool
    {
        return (bool) $this->currentUser()?->isAdmin();
    }

    protected function accessibleProjectQuery()
    {
        $user = $this->currentUser();
        abort_unless($user, 403, 'Autentikasi diperlukan.');

        return Project::accessibleBy($user);
    }

    protected function resolveProjectOrDefault(?int $projectId = null): ?int
    {
        $query = $this->accessibleProjectQuery()->where('is_active', true);

        if ($projectId) {
            $project = (clone $query)->find($projectId);
            abort_unless($project, 403, 'Anda tidak memiliki akses ke project ini.');

            return $project->id;
        }

        return (clone $query)->orderByDesc('created_at')->value('id');
    }

    public function render()
    {
        return view('components.⚡projects-list');
    }

    // ─── Queries (scoped by role) ────────────────────────────────────────

    /**
     * Ambil project sesuai role user yang login:
     *  - Admin → semua project
     *  - User  → hanya project yang di-assign via pivot project_user
     */
    public function getProjects(): array
    {
        $projects = $this->accessibleProjectQuery()
            ->where('is_active', true)
            ->withCount(['articles', 'socialMediaItems'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $officialReachByProject = $this->getOfficialProjectReachMap($projects->pluck('id')->all());
        $aiSummaryByProject = $this->getProjectAiSummaryMap($projects->pluck('id')->all());
        $rescrapeByProject = $this->getRescrapeBreakdownByProject($projects->pluck('id')->all());

        return $projects->map(function($project) use ($officialReachByProject, $aiSummaryByProject, $rescrapeByProject) {
            $summary = $aiSummaryByProject[$project->id] ?? [
                'total_ai_valid' => 0,
                'total_ai_failed' => 0,
                'positive_count' => 0,
                'negative_count' => 0,
                'high_critical_risk_count' => 0,
            ];

            $totalAiValid = (int) ($summary['total_ai_valid'] ?? 0);
            $totalAiFailed = (int) ($summary['total_ai_failed'] ?? 0);
            $positive = (int) ($summary['positive_count'] ?? 0);
            $negative = (int) ($summary['negative_count'] ?? 0);
            $highCriticalRisk = (int) ($summary['high_critical_risk_count'] ?? 0);
            if ($totalAiValid > 0) {
                $posPercent = round(($positive / $totalAiValid) * 100);
                $negPercent = round(($negative / $totalAiValid) * 100);
            } else {
                $posPercent = 0;
                $negPercent = 0;
            }

            // Hanya tampilkan jumlah penyebutan yang sudah divalidasi oleh AI
            $totalArticlesFound = (int) $project->articles_count;
            $readyToShow = (int) $totalAiValid;
            $rescrapeCount = (int) ($rescrapeByProject[$project->id]['needs_rescrape_only'] ?? 0);
            $rescrapeOverlap = (int) ($rescrapeByProject[$project->id]['display_ready_overlap'] ?? 0);
            $pendingAi = DB::table('ai_analysis_dispatch_states')
                ->where('project_id', $project->id)
                ->whereIn('status', ['queued', 'processing', 'retry_wait'])
                ->count();
            $officialReach = $officialReachByProject[$project->id] ?? null;
            $reach = $officialReach !== null
                ? number_format($officialReach, 0, ',', '.')
                : 'Belum tersedia';

            return [
                'id' => $project->id,
                'name' => $project->name,
                'mentions' => number_format($readyToShow, 0, ',', '.'),
                'total_articles_found' => $totalArticlesFound,
                'reach' => $reach,
                'positive' => $posPercent . '%',
                'negative' => $negPercent . '%',
                'topics' => $project->topics ?? [],
                'ai_valid' => $readyToShow,
                'ai_failed' => $totalAiFailed,
                'ai_pending' => $pendingAi,
                'ai_rescrape' => $rescrapeCount,
                'ai_rescrape_overlap' => $rescrapeOverlap,
                'high_risk' => $highCriticalRisk,
            ];
        })->toArray();
    }

    /**
     * Agregasi ringkasan AI per project tanpa query per kartu.
     *
     * @param array<int> $projectIds
     * @return array<int, array<string, int>>
     */
    protected function getProjectAiSummaryMap(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('project_articles')
            ->join('articles', 'project_articles.article_id', '=', 'articles.id')
            ->leftJoin('ai_analysis_results as ai', 'articles.id', '=', 'ai.article_id')
            ->whereIn('project_articles.project_id', $projectIds)
            ->groupBy('project_articles.project_id')
            ->orderBy('project_articles.project_id')
            ->get([
                'project_articles.project_id',
                DB::raw("COUNT(DISTINCT CASE WHEN ai.analysis_status = 'success' AND ai.reach_method = 'ai_reader_estimate_v1' AND ai.project_estimated_readers IS NOT NULL AND ai.project_estimated_readers >= 1 AND ai.project_reach_score IS NOT NULL AND ai.project_reach_level IS NOT NULL AND ai.project_reach_band IS NOT NULL AND ai.summary IS NOT NULL AND ai.sentiment IS NOT NULL AND ai.risk_level IS NOT NULL THEN articles.id END) as total_ai_valid"),
                DB::raw("COUNT(DISTINCT CASE WHEN ai.analysis_status = 'failed' THEN articles.id END) as total_ai_failed"),
                DB::raw("COUNT(DISTINCT CASE WHEN ai.analysis_status = 'success' AND ai.reach_method = 'ai_reader_estimate_v1' AND ai.project_estimated_readers IS NOT NULL AND ai.project_estimated_readers >= 1 AND ai.project_reach_score IS NOT NULL AND ai.project_reach_level IS NOT NULL AND ai.project_reach_band IS NOT NULL AND ai.summary IS NOT NULL AND ai.sentiment IS NOT NULL AND ai.risk_level IS NOT NULL AND ai.sentiment = 'positive' THEN articles.id END) as positive_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN ai.analysis_status = 'success' AND ai.reach_method = 'ai_reader_estimate_v1' AND ai.project_estimated_readers IS NOT NULL AND ai.project_estimated_readers >= 1 AND ai.project_reach_score IS NOT NULL AND ai.project_reach_level IS NOT NULL AND ai.project_reach_band IS NOT NULL AND ai.summary IS NOT NULL AND ai.sentiment IS NOT NULL AND ai.risk_level IS NOT NULL AND ai.sentiment = 'negative' THEN articles.id END) as negative_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN ai.analysis_status = 'success' AND ai.reach_method = 'ai_reader_estimate_v1' AND ai.project_estimated_readers IS NOT NULL AND ai.project_estimated_readers >= 1 AND ai.project_reach_score IS NOT NULL AND ai.project_reach_level IS NOT NULL AND ai.project_reach_band IS NOT NULL AND ai.summary IS NOT NULL AND ai.sentiment IS NOT NULL AND ai.risk_level IS NOT NULL AND ai.risk_level IN ('high', 'critical') THEN articles.id END) as high_critical_risk_count"),
            ]);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->project_id] = [
                'total_ai_valid' => (int) $row->total_ai_valid,
                'total_ai_failed' => (int) $row->total_ai_failed,
                'positive_count' => (int) $row->positive_count,
                'negative_count' => (int) $row->negative_count,
                'high_critical_risk_count' => (int) $row->high_critical_risk_count,
            ];
        }

        return $map;
    }

    /**
     * Ambil jangkauan resmi per project dari AI valid, satu query teragregasi.
     *
     * @param array<int> $projectIds
     * @return array<int, int>
     */
    protected function getOfficialProjectReachMap(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = AiAnalysisResult::query()
            ->join('articles', 'ai_analysis_results.article_id', '=', 'articles.id')
            ->join('project_articles', 'articles.id', '=', 'project_articles.article_id')
            ->whereIn('project_articles.project_id', $projectIds)
            ->completeOfficialAiResult()
            ->groupBy('project_articles.project_id')
            ->orderBy('project_articles.project_id')
            ->get([
                'project_articles.project_id',
                DB::raw('SUM(ai_analysis_results.project_estimated_readers) as official_reach'),
            ]);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->project_id] = (int) $row->official_reach;
        }

        return $map;
    }

    /**
     * Count project-article links flagged for safe rescrape.
     *
     * @param array<int> $projectIds
     * @return array<int, int>
     */
    protected function getRescrapeBreakdownByProject(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('project_articles')
            ->leftJoin('articles', 'project_articles.article_id', '=', 'articles.id')
            ->leftJoin('ai_analysis_results as ai', 'articles.id', '=', 'ai.article_id')
            ->whereIn('project_articles.project_id', $projectIds)
            ->where('project_articles.rescrape_status', 'needs_rescrape')
            ->groupBy('project_articles.project_id')
            ->orderBy('project_articles.project_id')
            ->get([
                'project_articles.project_id',
                DB::raw("COUNT(DISTINCT CASE WHEN ai.analysis_status = 'success' AND ai.reach_method = 'ai_reader_estimate_v1' AND ai.project_estimated_readers IS NOT NULL AND ai.project_estimated_readers >= 1 AND ai.project_reach_score IS NOT NULL AND ai.project_reach_level IS NOT NULL AND ai.project_reach_band IS NOT NULL AND ai.summary IS NOT NULL AND ai.sentiment IS NOT NULL AND ai.risk_level IS NOT NULL THEN project_articles.article_id END) as display_ready_overlap"),
                DB::raw("COUNT(DISTINCT CASE WHEN NOT (ai.analysis_status = 'success' AND ai.reach_method = 'ai_reader_estimate_v1' AND ai.project_estimated_readers IS NOT NULL AND ai.project_estimated_readers >= 1 AND ai.project_reach_score IS NOT NULL AND ai.project_reach_level IS NOT NULL AND ai.project_reach_band IS NOT NULL AND ai.summary IS NOT NULL AND ai.sentiment IS NOT NULL AND ai.risk_level IS NOT NULL) THEN project_articles.article_id END) as needs_rescrape_only"),
            ]);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->project_id] = [
                'display_ready_overlap' => (int) $row->display_ready_overlap,
                'needs_rescrape_only' => (int) $row->needs_rescrape_only,
            ];
        }

        return $map;
    }

    public function mount()
    {
        $this->projectId = $this->resolveProjectOrDefault($this->projectId);
    }

    public function updatedProjectId($value): void
    {
        $this->projectId = $this->resolveProjectOrDefault($value);
    }

    // ─── Actions (admin only) ────────────────────────────────────────────

    public function createProject(): void
    {
        $this->validate();

        $topics  = array_filter(array_map('trim', explode(',', $this->topicsString)));
        $project = Project::create([
            'name'        => $this->name,
            'description' => $this->description,
            'topics'      => $topics,
        ]);

        // Hubungkan proyek baru dengan user pembuatnya agar dapat diakses
        $project->users()->attach(auth()->id());

        app(ContentMatchingService::class)->matchExistingContentForProject($project);

        $this->reset(['name', 'description', 'topicsString', 'contextKeywords', 'excludeKeywords', 'selectedSources']);
        $this->isCreatingProject      = false;
        $this->showSuccessModal       = true;
        $this->lastCreatedProjectName = $project->name;
    }

    public function deleteProject(int $projectId): void
    {
        // Hanya admin yang boleh menonaktifkan project
        abort_unless($this->isAdmin(), 403, 'Hanya admin yang dapat menonaktifkan project.');

        $project = $this->accessibleProjectQuery()->findOrFail($projectId);
        $project->deactivate();

        if ((int) $this->projectId === (int) $project->id) {
            $this->projectId = null;
        }

        $this->dispatch('$refresh');
    }
}
