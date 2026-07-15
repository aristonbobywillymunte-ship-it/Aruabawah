<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Project;
use App\Services\ReachScoringService;
use Illuminate\Console\Command;

class TestArticleReachScoring extends Command
{
    protected $signature = 'reach:test-article
                            {--project-id= : Specific project ID}
                            {--article-id= : Specific article ID}';

    protected $description = 'Test hybrid reach scoring for one article without AI, Telegram, or queue writes';

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

        $this->line(json_encode([
            'status' => 'success',
            ...$result,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
