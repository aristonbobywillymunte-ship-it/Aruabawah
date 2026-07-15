<?php

namespace App\Services;

use App\Models\Article;
use App\Models\NewsSource;
use App\Models\Project;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class ReachScoringService
{
    public function scoreArticle(Project $project, Article $article): array
    {
        $source = $this->resolveSource($article);
        $topicTerms = $this->projectTerms($project);
        $content = trim((string) $article->content);
        $title = trim((string) $article->title);
        $fullText = mb_strtolower($title . ' ' . $content);

        $audienceCapacity = $this->audienceCapacityScore($source);
        $interaction = 0.0;
        $diffusion = $this->diffusionScore($project, $article, $topicTerms, $title, $fullText);
        $localRelevance = $this->localRelevanceScore($project, $title, $fullText, $source);
        $relevanceStatus = $this->relevanceStatus($localRelevance);
        $mediaContext = $this->mediaContextScore($source, $project, $title, $fullText, $topicTerms, $localRelevance);

        $potentialHybrid = round((0.30 * $audienceCapacity) + (0.25 * $interaction) + (0.25 * $diffusion) + (0.20 * $mediaContext), 2);
        $potentialReachScore = max(1, min(10, (int) ceil($potentialHybrid / 10)));
        $potentialReachLevel = $this->reachLevel($potentialReachScore);

        [$adjustedHybrid, $adjustedReachScore, $adjustedReachLevel] = $this->adjustedLocalReach($potentialHybrid, $localRelevance);

        $confidence = $this->confidenceScore($source, $diffusion, $mediaContext, $project, $topicTerms, $localRelevance);
        $confidenceLevel = $this->confidenceLevel($confidence);

        return [
            'article_id' => $article->id,
            'source_name' => $source?->name ?? ($article->source_name ?: 'unknown'),
            'audience_capacity_score' => round($audienceCapacity, 2),
            'interaction_score' => round($interaction, 2),
            'diffusion_score' => round($diffusion, 2),
            'media_context_score' => round($mediaContext, 2),
            'potential_hybrid_score' => $potentialHybrid,
            'potential_reach_score' => $potentialReachScore,
            'potential_reach_level' => $potentialReachLevel,
            'local_relevance_score' => round($localRelevance, 2),
            'relevance_status' => $relevanceStatus,
            'adjusted_local_hybrid_score' => $adjustedHybrid,
            'adjusted_local_reach_score' => $adjustedReachScore,
            'adjusted_local_reach_level' => $adjustedReachLevel,
            'hybrid_reach_score' => $adjustedHybrid,
            'reach_score' => $adjustedReachScore,
            'reach_level' => $adjustedReachLevel,
            'confidence_score' => $confidence,
            'confidence_level' => $confidenceLevel,
            'method' => 'samarinda_news_hybrid',
            'is_exact_reach' => false,
            'explanation' => $this->explanation(
                $source,
                $audienceCapacity,
                $interaction,
                $diffusion,
                $mediaContext,
                $potentialReachLevel,
                $adjustedReachLevel,
                $relevanceStatus,
                $confidenceLevel
            ),
            'components' => [
                'A' => round($audienceCapacity, 2),
                'E' => round($interaction, 2),
                'D' => round($diffusion, 2),
                'M' => round($mediaContext, 2),
                'local_relevance' => round($localRelevance, 2),
            ],
        ];
    }

    private function resolveSource(Article $article): ?NewsSource
    {
        $domain = $this->extractDomain((string) ($article->canonical_url ?: $article->url));
        if ($domain !== null) {
            $source = NewsSource::query()
                ->where('domain', $domain)
                ->orWhere('base_url', 'like', "%{$domain}%")
                ->first();

            if ($source) {
                return $source;
            }
        }

        $sourceName = trim((string) $article->source_name);
        if ($sourceName !== '') {
            $normalized = Str::lower($sourceName);
            $source = NewsSource::query()
                ->whereRaw('LOWER(name) = ?', [$normalized])
                ->orWhereRaw('LOWER(domain) = ?', [$normalized])
                ->first();

            if ($source) {
                return $source;
            }
        }

        return null;
    }

    private function audienceCapacityScore(?NewsSource $source): float
    {
        if (! $source) {
            return 25.0;
        }

        $weight = (float) ($source->local_reach_weight ?? 0);
        if ($weight <= 0) {
            return 30.0;
        }

        $raw = $weight * 10.0;
        $scopeCap = match ($source->media_scope) {
            'national' => 75.0,
            'regional_kaltim' => 100.0,
            'local_samarinda' => 100.0,
            'local_kabupaten' => 80.0,
            'niche_community' => 65.0,
            default => 75.0,
        };

        $typeCap = match ($source->source_type) {
            'national_local_channel' => 90.0,
            'local_media' => 100.0,
            'social_video' => 65.0,
            'radio_tv' => 85.0,
            'government_source' => 70.0,
            default => 75.0,
        };

        return max(0.0, min(100.0, min($raw, $scopeCap, $typeCap)));
    }

    private function diffusionScore(Project $project, Article $article, array $topicTerms, string $title, string $fullText): float
    {
        $terms = $this->diffusionTerms($project, $topicTerms, $title);
        if (empty($terms)) {
            return 0.0;
        }

        $publishedAt = $article->published_at
            ? Carbon::parse($article->published_at)
            : Carbon::parse($article->created_at ?? now());
        $windowStart = $publishedAt->copy()->subDays(3);
        $windowEnd = $publishedAt->copy()->addDays(3);

        $query = Article::query()
            ->whereKeyNot($article->id)
            ->whereBetween('published_at', [$windowStart, $windowEnd])
            ->whereHas('projects', fn ($q) => $q->where('projects.id', $project->id));

        $outletCount = 0;
        foreach ($query->get(['source_name', 'title', 'content']) as $candidate) {
            $candidateText = mb_strtolower((string) ($candidate->title ?? '') . ' ' . (string) ($candidate->content ?? ''));
            $overlapCount = 0;

            foreach ($terms as $term) {
                if (str_contains($candidateText, $term) && str_contains($fullText, $term)) {
                    $overlapCount++;
                }
            }

            if ($overlapCount >= 2) {
                $outletCount++;
            }
        }

        return match (true) {
            $outletCount <= 0 => 0.0,
            $outletCount === 1 => 20.0,
            $outletCount === 2 => 35.0,
            $outletCount <= 4 => 55.0,
            $outletCount <= 7 => 75.0,
            default => 90.0,
        };
    }

    private function mediaContextScore(?NewsSource $source, Project $project, string $title, string $fullText, array $topicTerms, float $localRelevance): float
    {
        $scopeScore = match ($source?->media_scope) {
            'national' => 60.0,
            'regional_kaltim' => 92.0,
            'local_samarinda' => 96.0,
            'local_kabupaten' => 84.0,
            'niche_community' => 55.0,
            default => 45.0,
        };

        $relevanceScore = $this->keywordRelevanceScore($project, $title, $fullText, $topicTerms);
        $credibilityScore = $this->credibilityScore($source);
        $freshnessScore = $this->freshnessScore($project, $title, $fullText);
        $contextBoost = min(100.0, $localRelevance * 0.15);

        return round(($scopeScore * 0.30) + ($relevanceScore * 0.30) + ($credibilityScore * 0.20) + ($freshnessScore * 0.10) + ($contextBoost * 0.10), 2);
    }

    private function keywordRelevanceScore(Project $project, string $title, string $fullText, array $topicTerms): float
    {
        $haystack = mb_strtolower($title . ' ' . $fullText);
        $matches = 0;
        foreach ($topicTerms as $term) {
            if ($term !== '' && str_contains($haystack, mb_strtolower($term))) {
                $matches++;
            }
        }

        $projectNameTerms = preg_split('/\s+/', mb_strtolower(trim($project->name ?: '')));
        foreach ($projectNameTerms ?: [] as $term) {
            $term = trim((string) $term);
            if ($term !== '' && strlen($term) > 3 && str_contains($haystack, $term)) {
                $matches += 0.5;
            }
        }

        $score = min(100.0, ($matches / max(1, count($topicTerms))) * 100.0);
        if ($score < 15.0 && Str::contains($haystack, ['samarinda', 'kaltim', 'kalimantan timur'])) {
            $score += 15.0;
        }

        return round(min(100.0, $score), 2);
    }

    private function credibilityScore(?NewsSource $source): float
    {
        if (! $source) {
            return 35.0;
        }

        $base = match ($source->dewan_pers_status) {
            'terverifikasi_faktual' => 90.0,
            'terverifikasi_administratif' => 75.0,
            'tidak_diketahui' => 50.0,
            'belum_terverifikasi' => 35.0,
            default => 50.0,
        };

        $typeAdjustment = match ($source->source_type) {
            'national_local_channel' => 3.0,
            'local_media' => 2.0,
            'radio_tv' => 1.0,
            'government_source' => 0.0,
            'social_video' => -20.0,
            default => -5.0,
        };

        return max(0.0, min(100.0, $base + $typeAdjustment));
    }

    private function localRelevanceScore(Project $project, string $title, string $fullText, ?NewsSource $source): float
    {
        $haystack = mb_strtolower($title . ' ' . $fullText);
        $score = 0.0;

        $keywords = array_unique(array_filter(array_map('trim', array_map('strval', $project->topics ?? []))));
        foreach ($keywords as $keyword) {
            $keywordLower = mb_strtolower($keyword);
            if ($keywordLower !== '' && str_contains($haystack, $keywordLower)) {
                $score += mb_strpos($keywordLower, ' ') !== false ? 22.0 : 14.0;
            }
        }

        foreach (['samarinda' => 18.0, 'kaltim' => 16.0, 'kalimantan timur' => 22.0, 'kaltimtimur' => 12.0] as $term => $weight) {
            if (str_contains($haystack, $term)) {
                $score += $weight;
            }
        }

        if ($source?->media_scope === 'local_samarinda') {
            $score += 18.0;
        } elseif ($source?->media_scope === 'regional_kaltim') {
            $score += 12.0;
        } elseif ($source?->media_scope === 'national') {
            $score += 4.0;
        }

        if (str_contains($haystack, 'seno aji') || str_contains($haystack, 'wagub')) {
            $score += 16.0;
        }

        return max(0.0, min(100.0, $score));
    }

    private function relevanceStatus(float $localRelevance): string
    {
        return match (true) {
            $localRelevance < 20 => 'out_of_scope',
            $localRelevance < 50 => 'partially_relevant',
            default => 'relevant',
        };
    }

    private function adjustedLocalReach(float $potentialHybrid, float $localRelevance): array
    {
        if ($localRelevance < 20) {
            $adjustedHybrid = min($potentialHybrid * 0.20, 19.99);
            $adjustedReachScore = max(1, min(2, (int) ceil($adjustedHybrid / 10)));
            $adjustedReachLevel = 'Low';

            return [round($adjustedHybrid, 2), $adjustedReachScore, $adjustedReachLevel];
        }

        if ($localRelevance < 50) {
            $adjustedHybrid = $potentialHybrid * 0.60;
        } elseif ($localRelevance < 70) {
            $adjustedHybrid = $potentialHybrid * 0.85;
        } else {
            $adjustedHybrid = $potentialHybrid;
        }

        $adjustedReachScore = max(1, min(10, (int) ceil($adjustedHybrid / 10)));
        $adjustedReachLevel = $this->reachLevel($adjustedReachScore);

        return [round($adjustedHybrid, 2), $adjustedReachScore, $adjustedReachLevel];
    }

    private function confidenceScore(?NewsSource $source, float $diffusion, float $mediaContext, Project $project, array $topicTerms, float $localRelevance): int
    {
        $dataCompleteness = 0.0;
        if ($source) {
            $dataCompleteness += 50.0;
        }
        if (($source?->local_reach_weight ?? 0) > 0) {
            $dataCompleteness += 15.0;
        }
        if (filled($project->name)) {
            $dataCompleteness += 10.0;
        }
        if (! empty($topicTerms)) {
            $dataCompleteness += 10.0;
        }
        if (filled($source?->media_scope)) {
            $dataCompleteness += 15.0;
        }

        $sourceQuality = $this->credibilityScore($source);

        $crossSourceAgreement = 0.0;
        if ($diffusion >= 90) {
            $crossSourceAgreement = 100.0;
        } elseif ($diffusion >= 75) {
            $crossSourceAgreement = 80.0;
        } elseif ($diffusion >= 55) {
            $crossSourceAgreement = 60.0;
        } elseif ($diffusion >= 35) {
            $crossSourceAgreement = 45.0;
        } elseif ($diffusion >= 20) {
            $crossSourceAgreement = 25.0;
        }

        if ($diffusion === 0.0) {
            $crossSourceAgreement = 0.0;
        }

        $score = (0.40 * $dataCompleteness) + (0.30 * $sourceQuality) + (0.30 * $crossSourceAgreement);

        if ($diffusion === 0.0) {
            $score = min($score, 55.0);
        } elseif ($diffusion > 0 && $localRelevance < 20.0) {
            $score = min($score, 55.0);
        } elseif ($diffusion > 0 && $localRelevance < 50.0) {
            $score = min($score, 65.0);
        } else {
            $score = min($score, 70.0);
        }

        return (int) max(0, min(100, round($score)));
    }

    private function reachLevel(int $score): string
    {
        return match (true) {
            $score <= 2 => 'Low',
            $score <= 4 => 'Local',
            $score <= 6 => 'Medium',
            $score <= 8 => 'High',
            default => 'Viral',
        };
    }

    private function confidenceLevel(int $score): string
    {
        return match (true) {
            $score >= 75 => 'High',
            $score >= 45 => 'Medium',
            default => 'Low',
        };
    }

    private function projectTerms(Project $project): array
    {
        $terms = [];

        if (is_array($project->topics ?? null)) {
            $terms = array_merge($terms, $project->topics);
        }

        $terms[] = $project->name;

        return array_values(array_filter(array_map(static function ($term) {
            $term = trim((string) $term);
            return $term === '' ? null : $term;
        }, $terms)));
    }

    private function diffusionTerms(Project $project, array $topicTerms, string $title): array
    {
        $terms = [];
        $candidates = preg_split('/\s+/', mb_strtolower($title)) ?: [];
        $strongMatches = 0;

        foreach ($candidates as $term) {
            $term = trim((string) $term);
            $term = mb_strtolower($term);
            if ($term === '' || mb_strlen($term) < 4) {
                continue;
            }

            if (in_array($term, ['dan', 'dari', 'yang', 'untuk', 'kaltim', 'samarinda', 'berita'], true)) {
                continue;
            }

            $terms[] = $term;
            if (str_contains(mb_strtolower($title), $term)) {
                $strongMatches++;
            }
        }

        foreach ($topicTerms as $topicTerm) {
            $topicTerm = mb_strtolower(trim((string) $topicTerm));
            if ($topicTerm === '') {
                continue;
            }

            if (str_contains(mb_strtolower($title), $topicTerm)) {
                $terms[] = $topicTerm;
                $strongMatches++;
            }
        }

        if ($strongMatches < 2) {
            return [];
        }

        return array_values(array_unique(array_filter($terms)));
    }

    private function freshnessScore(Project $project, string $title, string $fullText): float
    {
        $haystack = mb_strtolower($title . ' ' . $fullText);
        $score = 50.0;

        foreach (['samarinda', 'kaltim', 'kalimantan timur'] as $term) {
            if (str_contains($haystack, $term)) {
                $score += 10.0;
            }
        }

        foreach ($this->projectTerms($project) as $term) {
            if ($term !== '' && str_contains($haystack, mb_strtolower($term))) {
                $score += 5.0;
            }
        }

        return max(0.0, min(100.0, $score));
    }

    private function extractDomain(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return null;
        }

        return Str::lower(preg_replace('/^www\./i', '', $host));
    }

    private function explanation(?NewsSource $source, float $a, float $e, float $d, float $m, string $potentialLevel, string $adjustedLevel, string $relevanceStatus, string $confidenceLevel): string
    {
        $sourceName = $source?->name ?? 'fallback source';
        return sprintf(
            'Source %s memberi A=%.1f, E=%.1f, D=%.1f, M=%.1f; potential %s, adjusted %s, relevance %s, confidence %s.',
            $sourceName,
            $a,
            $e,
            $d,
            $m,
            $potentialLevel,
            $adjustedLevel,
            $relevanceStatus,
            $confidenceLevel
        );
    }
}
