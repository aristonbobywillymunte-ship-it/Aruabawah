<?php

namespace App\Console\Commands;

use App\Jobs\AiAnalysisJob;
use App\Models\AiAnalysisResult;
use App\Models\AiPromptTemplate;
use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestArticleAiAnalysis extends Command
{
    protected $signature = 'ai:test-article
                            {--project-id= : Specific project ID}
                            {--article-id= : Specific article ID}
                            {--no-notification : Suppress Telegram notification dispatch}
                            {--dry-run : Do not call AI or write DB}
                            {--force : Re-run and update existing AI analysis result}';

    protected $description = 'Test one existing article through the AI analysis pipeline without queueing Telegram';

    public function handle(): int
    {
        $projectId = (int) $this->option('project-id');
        $articleId = (int) $this->option('article-id');
        $dryRun = (bool) $this->option('dry-run');
        $suppressNotification = (bool) $this->option('no-notification');
        $force = (bool) $this->option('force');

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

        $analysis = AiAnalysisResult::where('article_id', $article->id)->first();
        if ($analysis && ! $force && ! $dryRun) {
            $this->line(json_encode([
                'status' => 'skipped_existing',
                'reason' => 'AI analysis result already exists',
                'article_id' => $article->id,
                'ai_analysis_result_id' => $analysis->id,
            ], JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $provider = AiProvider::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('id', 'asc')
            ->first();

        $template = AiPromptTemplate::where('source_type', 'article')
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if (! $provider || ! $template) {
            $this->error('AI provider atau prompt template artikel belum siap.');
            return self::FAILURE;
        }

        $providerInfo = [
            'id' => $provider->id,
            'name' => $provider->name,
            'provider_type' => $provider->provider_type,
            'model_name' => $provider->model_name,
            'is_active' => (bool) $provider->is_active,
            'is_default' => (bool) $provider->is_default,
            'api_key_ready' => filled($provider->api_key),
            'api_key_masked' => $this->maskKey((string) $provider->api_key),
        ];

        $templateInfo = [
            'id' => $template->id,
            'name' => $template->name,
            'source_type' => $template->source_type,
            'is_active' => (bool) $template->is_active,
            'is_default' => (bool) $template->is_default,
        ];

        if ($dryRun) {
            $this->line(json_encode([
                'status' => 'dry_run',
                'reason' => 'No AI call and no DB write executed',
                'project_id' => $project->id,
                'article_id' => $article->id,
                'provider' => $providerInfo,
                'template' => $templateInfo,
                'content_length' => mb_strlen(trim((string) $article->content)),
                'existing_analysis_id' => $analysis?->id,
                'force' => $force,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        if (mb_strlen(trim((string) $article->content)) < 500) {
            $this->warn('Konten artikel terlalu pendek untuk AI analysis.');
            return self::SUCCESS;
        }

        DB::beginTransaction();
        try {
            $job = new AiAnalysisJob([
                'type' => 'article',
                'id' => $article->id,
                'project_id' => $project->id,
                'title' => $article->title,
                'url' => $article->url,
                'content' => $article->content,
                'source_name' => $article->source_name,
                'published_at' => optional($article->published_at)?->toIso8601String(),
                'no_telegram' => $suppressNotification,
            ]);

            $job->handle();
            DB::commit();

            $analysis = AiAnalysisResult::where('article_id', $article->id)->first();
            if (! $analysis) {
                $this->warn('AI job selesai tetapi analysis result tidak ditemukan.');
                return self::SUCCESS;
            }

            $this->line(json_encode([
                'status' => 'success',
                'reason' => 'AI analysis executed successfully',
                'project_id' => $project->id,
                'article_id' => $article->id,
                'ai_analysis_result_id' => $analysis->id,
                'provider' => $providerInfo,
                'template' => $templateInfo,
                'sentiment_label' => $analysis->sentiment,
                'risk_level' => $analysis->risk_level,
                'potential_reach_score' => $analysis->potential_reach_score,
                'potential_reach_level' => $analysis->potential_reach_level,
                'potential_reach_band' => $analysis->potential_reach_band,
                'project_reach_score' => $analysis->project_reach_score,
                'project_reach_level' => $analysis->project_reach_level,
                'project_reach_band' => $analysis->project_reach_band,
                'local_relevance_score' => $analysis->local_relevance_score,
                'confidence_score' => $analysis->confidence_score,
                'confidence_level' => $analysis->confidence_level,
                'signals_used' => $analysis->signals_used,
                'reasoning_summary' => $analysis->reasoning_summary,
                'limitations' => $analysis->limitations,
                'reach_method' => $analysis->reach_method,
                'is_exact_reach' => $analysis->is_exact_reach,
                'analysis_status' => $analysis->analysis_status,
                'validation_errors' => $analysis->validation_errors,
                'summary' => $analysis->summary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function maskKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }

        if (mb_strlen($key) <= 8) {
            return str_repeat('*', mb_strlen($key));
        }

        return mb_substr($key, 0, 4) . str_repeat('*', max(4, mb_strlen($key) - 8)) . mb_substr($key, -4);
    }
}
