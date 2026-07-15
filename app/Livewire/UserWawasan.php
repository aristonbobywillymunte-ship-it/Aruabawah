<?php

namespace App\Livewire;

use Livewire\Component;
class UserWawasan extends Component
{
    public function getGlobalWawasan()
    {
        $user = auth()->user();
        if (!$user) return [];

        $projects = \App\Models\Project::accessibleBy($user)->pluck('id');
        
        $totalProjects = $projects->count();
        
        $articlesQuery = \App\Models\Article::whereHas('projects', function($q) use ($projects) {
            $q->whereIn('projects.id', $projects);
        })->whereHas('aiAnalysisResult');

        $totalMentions = (clone $articlesQuery)->count();

        $baseQueryWithAI = (clone $articlesQuery)->join('ai_analysis_results as ai', 'articles.id', '=', 'ai.article_id');
        
        $pos = (clone $baseQueryWithAI)->where('ai.sentiment', 'positive')->count();
        $neu = (clone $baseQueryWithAI)->where('ai.sentiment', 'neutral')->count();
        $neg = (clone $baseQueryWithAI)->where('ai.sentiment', 'negative')->count();

        $pos_pct = $totalMentions > 0 ? round(($pos / $totalMentions) * 100) : 0;
        $neu_pct = $totalMentions > 0 ? round(($neu / $totalMentions) * 100) : 0;
        $neg_pct = $totalMentions > 0 ? round(($neg / $totalMentions) * 100) : 0;

        $reputation_score = $totalMentions > 0 ? round((($pos + ($neu * 0.5)) / $totalMentions) * 100) : 100;

        $recentAlerts = \App\Models\AiAnalysisResult::where('sentiment', 'negative')
            ->whereHas('article', function($q) use ($projects) {
                $q->whereHas('projects', function($sq) use ($projects) {
                    $sq->whereIn('projects.id', $projects);
                });
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
