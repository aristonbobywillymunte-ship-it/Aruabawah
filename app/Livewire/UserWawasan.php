<?php

namespace App\Livewire;

use App\Models\AiAnalysisResult;
use App\Models\Article;
use App\Models\Project;
use App\Services\ContentMatchingService;
use Livewire\Component;

class UserWawasan extends Component
{
    public function getGlobalWawasan()
    {
        $user = auth()->user();
        if (!$user) return [];

        $projects = Project::accessibleBy($user)->where('is_active', true)->get();
        $matchingService = app(ContentMatchingService::class);

        $totalProjects = $projects->count();
        $matchedArticleIds = [];

        Article::query()
            ->select(['id', 'title', 'content'])
            ->chunkById(300, function ($articles) use ($projects, $matchingService, &$matchedArticleIds) {
                foreach ($articles as $article) {
                    $content = ($article->title ?? '') . "\n" . ($article->content ?? '');
                    foreach ($projects as $project) {
                        if ($matchingService->matchesProjectContent($project, $content)) {
                            $matchedArticleIds[] = $article->id;
                            break;
                        }
                    }
                }
            });

        $matchedArticleIds = array_values(array_unique($matchedArticleIds));
        $articlesQuery = Article::query()
            ->whereIn('id', $matchedArticleIds)
            ->whereHas('aiAnalysisResult');

        $totalMentions = (clone $articlesQuery)->count();

        $baseQueryWithAI = (clone $articlesQuery)->join('ai_analysis_results as ai', 'articles.id', '=', 'ai.article_id');

        $pos = (clone $baseQueryWithAI)->where('ai.sentiment', 'positive')->count();
        $neu = (clone $baseQueryWithAI)->where('ai.sentiment', 'neutral')->count();
        $neg = (clone $baseQueryWithAI)->where('ai.sentiment', 'negative')->count();

        $pos_pct = $totalMentions > 0 ? round(($pos / $totalMentions) * 100) : 0;
        $neu_pct = $totalMentions > 0 ? round(($neu / $totalMentions) * 100) : 0;
        $neg_pct = $totalMentions > 0 ? round(($neg / $totalMentions) * 100) : 0;

        $reputation_score = $totalMentions > 0 ? round((($pos + ($neu * 0.5)) / $totalMentions) * 100) : 100;

        $recentAlerts = AiAnalysisResult::where('sentiment', 'negative')
            ->whereHas('article', function($q) use ($matchedArticleIds) {
                $q->whereIn('id', $matchedArticleIds);
            })
            ->with('article')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'total_projects' => $totalProjects,
            'total_mentions' => $totalMentions,
            'pos_pct' => $pos_pct,
            'neu_pct' => $neu_pct,
            'neg_pct' => $neg_pct,
            'reputation_score' => $reputation_score,
            'alerts' => $recentAlerts,
        ];
    }

    public function render()
    {
        $data = $this->getGlobalWawasan();
        return view('livewire.user-wawasan', ['data' => $data]);
    }
}
