<?php

namespace App\Console\Commands;

use App\Jobs\BackfillArticleReadersJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class BackfillArticleReadersCommand extends Command
{
    protected $signature = 'ai:backfill-article-readers 
                            {--dry-run : Jalankan tanpa mengubah data atau antrean (default)} 
                            {--execute : Eksekusi nyata untuk mengantrekan job} 
                            {--limit=10 : Batas maksimal artikel yang diproses} 
                            {--article-id= : Spesifik satu artikel ID}';

    protected $description = 'Backfill project_estimated_readers yang bernilai null (estimasi pembaca artikel umum)';

    public function handle()
    {
        $isDryRun = !$this->option('execute') || $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $specificArticleId = $this->option('article-id');

        $this->info("Memulai audit backfill article readers (Dry Run: " . ($isDryRun ? 'Ya' : 'Tidak') . ")");

        if (! $isDryRun && $this->isAiBackfillQueueBusy()) {
            $this->warn('Queue ai-backfill masih berisi job. Backfill ditunda agar tidak membuat duplikasi.');
            return 0;
        }

        $query = DB::table('ai_analysis_results')
            ->join('articles', 'ai_analysis_results.article_id', '=', 'articles.id')
            ->where('ai_analysis_results.analysis_status', 'success')
            ->where('ai_analysis_results.reach_method', 'ai_reader_estimate_v1')
            ->whereNotNull('ai_analysis_results.article_id')
            ->where('articles.url', 'not ilike', '%google.com%')
            ->whereNotNull('articles.content')
            ->whereRaw('LENGTH(articles.content) > 100') // Validasi panjang konten minimal
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('project_articles')
                  ->join('projects', 'project_articles.project_id', '=', 'projects.id')
                  ->whereColumn('project_articles.article_id', 'articles.id')
                  ->where('projects.is_active', true);
            });

        $query->where(function ($subQuery) {
            $subQuery->whereNull('ai_analysis_results.project_estimated_readers')
                ->orWhere(function ($completeQuery) {
                    $completeQuery->whereNotNull('ai_analysis_results.project_estimated_readers')
                        ->where(function ($reachQuery) {
                            $reachQuery->whereNull('ai_analysis_results.project_reach_score')
                                ->orWhereNull('ai_analysis_results.project_reach_level')
                                ->orWhereNull('ai_analysis_results.project_reach_band');
                        });
                });
        });

        if ($specificArticleId) {
            $query->where('ai_analysis_results.article_id', $specificArticleId);
        }

        $candidates = $query->select(
            'articles.id as article_id',
            'articles.title',
            'articles.url',
            'articles.content',
            'articles.source_name',
            'articles.published_at',
            'ai_analysis_results.project_estimated_readers',
            'ai_analysis_results.project_reach_score',
            'ai_analysis_results.project_reach_level',
            'ai_analysis_results.project_reach_band'
        )->distinct('articles.id')->limit($limit)->get();

        $this->info("Ditemukan " . $candidates->count() . " artikel kandidat.");

        if ($isDryRun) {
            foreach ($candidates as $row) {
                $action = $row->project_estimated_readers !== null
                    ? '[DRY RUN] Akan repair skor resmi'
                    : '[DRY RUN] Akan dispatch AI Job';
                $this->line("{$action} untuk Article ID: {$row->article_id} ({$row->title})");
            }
            return 0;
        }

        $count = 0;
        foreach ($candidates as $row) {
            // Mengambil satu project_id aktif secara acak/pertama untuk konteks job
            $firstActiveProjectId = DB::table('project_articles')
                ->join('projects', 'project_articles.project_id', '=', 'projects.id')
                ->where('project_articles.article_id', $row->article_id)
                ->where('projects.is_active', true)
                ->value('projects.id');

            if (!$firstActiveProjectId) {
                $this->warn("Skipped Article ID {$row->article_id}: tidak ada project aktif.");
                continue;
            }

            if ($row->project_estimated_readers !== null) {
                $this->info("Repair skor resmi untuk Article ID: {$row->article_id}");
                BackfillArticleReadersJob::dispatch([
                    'type' => 'article',
                    'id' => $row->article_id,
                    'project_id' => $firstActiveProjectId,
                    'title' => $row->title,
                    'content' => $row->content,
                    'url' => $row->url,
                    'source_name' => $row->source_name,
                    'published_at' => $row->published_at,
                ])->onQueue('ai-backfill');

                $count++;
                continue;
            }

            $this->info("Dispatching AI Job untuk Article ID: {$row->article_id}");
            BackfillArticleReadersJob::dispatch([
                'type' => 'article',
                'id' => $row->article_id,
                'project_id' => $firstActiveProjectId,
                'title' => $row->title,
                'content' => $row->content,
                'url' => $row->url,
                'source_name' => $row->source_name,
                'published_at' => $row->published_at,
            ])->onQueue('ai-backfill');

            $count++;
        }

        $this->info("Selesai. Telah mengantrekan {$count} jobs.");
        return 0;
    }

    protected function isAiBackfillQueueBusy(): bool
    {
        try {
            $redisQueue = Queue::connection('redis-ai');
            return method_exists($redisQueue, 'size') && $redisQueue->size('ai-backfill') > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
