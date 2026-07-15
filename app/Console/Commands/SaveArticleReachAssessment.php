<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Project;
use App\Models\ReachAssessment;
use App\Services\ReachScoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class SaveArticleReachAssessment extends Command
{
    protected $signature = 'reach:save-article
                            {--project-id= : Specific project ID}
                            {--article-id= : Specific article ID}';

    protected $description = 'Calculate and store article reach assessment without AI, Telegram, or queue writes';

    public function __construct(private readonly ReachScoringService $reachScoringService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $projectId = (int) $this->option('project-id');
        $articleId = (int) $this->option('article-id');

        if ($projectId <= 0 || $articleId <= 0) {
            $this->error('project-id dan article-id wajib diisi.');
            return self::FAILURE;
        }

        $project = Project::find($projectId);
        if (! $project) {
            $this->error("Project {$projectId} tidak ditemukan.");
            return self::FAILURE;
        }

        $article = Article::find($articleId);
        if (! $article) {
            $this->error("Article {$articleId} tidak ditemukan.");
            return self::FAILURE;
        }

        $result = $this->reachScoringService->scoreArticle($project, $article);
        $signals = Arr::get($result, 'components', []);

        $assessment = ReachAssessment::updateOrCreate(
            [
                'project_id' => $project->id,
                'assessable_type' => Article::class,
                'assessable_id' => $article->id,
                'method' => $result['method'],
                'score_version' => '1.0',
            ],
            [
                'audience_capacity_score' => $result['audience_capacity_score'],
                'observed_consumption_score' => null,
                'interaction_score' => $result['interaction_score'],
                'diffusion_score' => $result['diffusion_score'],
                'media_context_score' => $result['media_context_score'],
                'potential_hybrid_score' => $result['potential_hybrid_score'],
                'potential_reach_score' => $result['potential_reach_score'],
                'potential_reach_level' => $result['potential_reach_level'],
                'local_relevance_score' => $result['local_relevance_score'],
                'relevance_status' => $result['relevance_status'],
                'adjusted_local_hybrid_score' => $result['adjusted_local_hybrid_score'],
                'adjusted_local_reach_score' => $result['adjusted_local_reach_score'],
                'adjusted_local_reach_level' => $result['adjusted_local_reach_level'],
                'confidence_score' => $result['confidence_score'],
                'confidence_level' => $result['confidence_level'],
                'is_exact_reach' => false,
                'signals_json' => $signals,
                'explanation' => $result['explanation'] ?? null,
                'calculated_at' => now(),
            ]
        );

        $this->line(json_encode([
            'status' => 'success',
            'reach_assessment_id' => $assessment->id,
            'article_id' => $article->id,
            'project_id' => $project->id,
            'method' => $assessment->method,
            'score_version' => $assessment->score_version,
            'audience_capacity_score' => $assessment->audience_capacity_score,
            'observed_consumption_score' => $assessment->observed_consumption_score,
            'interaction_score' => $assessment->interaction_score,
            'diffusion_score' => $assessment->diffusion_score,
            'media_context_score' => $assessment->media_context_score,
            'potential_hybrid_score' => $assessment->potential_hybrid_score,
            'potential_reach_score' => $assessment->potential_reach_score,
            'potential_reach_level' => $assessment->potential_reach_level,
            'local_relevance_score' => $assessment->local_relevance_score,
            'relevance_status' => $assessment->relevance_status,
            'adjusted_local_hybrid_score' => $assessment->adjusted_local_hybrid_score,
            'adjusted_local_reach_score' => $assessment->adjusted_local_reach_score,
            'adjusted_local_reach_level' => $assessment->adjusted_local_reach_level,
            'confidence_score' => $assessment->confidence_score,
            'confidence_level' => $assessment->confidence_level,
            'is_exact_reach' => $assessment->is_exact_reach,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
