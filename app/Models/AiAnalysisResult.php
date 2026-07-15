<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAnalysisResult extends Model
{
    protected $fillable = [
        'article_id',
        'social_media_item_id',
        'summary',
        'sentiment',
        'sentiment_score',
        'main_issue',
        'entities',
        'risk_level',
        'risk_reason',
        'reach_estimate',
        'reach_score_10',
        'reach_score_max',
        'reach_level',
        'local_relevance_score',
        'estimated_reach_band',
        'confidence_score',
        'confidence_level',
        'reach_trend',
        'reach_source',
        'reach_confidence',
        'reach_reason',
        'signals_used',
        'reasoning_summary',
        'limitations',
        'is_exact_reach',
        'reach_method',
        'potential_reach_score',
        'potential_reach_level',
        'potential_reach_band',
        'potential_estimated_readers',
        'project_estimated_readers',
        'project_reach_score',
        'project_reach_level',
        'project_reach_band',
        'analysis_status',
        'validation_errors',
        'recommendation',
        'raw_response',
    ];

    protected $casts = [
        'entities' => 'array',
        'sentiment_score' => 'float',
        'reach_estimate' => 'integer',
        'reach_score_10' => 'integer',
        'reach_score_max' => 'integer',
        'local_relevance_score' => 'integer',
        'confidence_score' => 'integer',
        'signals_used' => 'array',
        'is_exact_reach' => 'boolean',
        'potential_reach_score' => 'integer',
        'potential_reach_band' => 'string',
        'potential_estimated_readers' => 'integer',
        // LEGACY NAME: 'project_estimated_readers' is the database column name, 
        // but it semantically means the official estimated readers for the ARTICLE globally.
        // It does NOT vary by project.
        'project_estimated_readers' => 'integer',
        'project_reach_level' => 'string',
        'project_reach_band' => 'string',
        'project_reach_score' => 'integer',
        'potential_reach_level' => 'string',
        'analysis_status' => 'string',
        'validation_errors' => 'string',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function socialMediaItem(): BelongsTo
    {
        return $this->belongsTo(SocialMediaItem::class);
    }

    public function hasOfficialArticleReach(): bool
    {
        return $this->officialArticleEstimatedReaders() !== null;
    }

    public function hasCompleteOfficialAiResult(): bool
    {
        if ($this->analysis_status !== 'success') {
            return false;
        }

        if ($this->reach_method !== 'ai_reader_estimate_v1') {
            return false;
        }

        if ($this->project_estimated_readers === null || (int) $this->project_estimated_readers < 1) {
            return false;
        }

        if ($this->project_reach_score === null) {
            return false;
        }

        if ($this->project_reach_level === null || trim((string) $this->project_reach_level) === '') {
            return false;
        }

        if ($this->project_reach_band === null || trim((string) $this->project_reach_band) === '') {
            return false;
        }

        return true;
    }

    public function officialArticleEstimatedReaders(): ?int
    {
        return $this->hasCompleteOfficialAiResult()
            ? (int) $this->project_estimated_readers
            : null;
    }

    public static function officialProjectReachScoreForReaders(int $readers): int
    {
        return match (true) {
            $readers <= 20 => 1,
            $readers <= 40 => 2,
            $readers <= 70 => 3,
            $readers <= 100 => 4,
            $readers <= 150 => 5,
            $readers <= 200 => 6,
            $readers <= 350 => 7,
            $readers <= 600 => 8,
            $readers <= 999 => 9,
            default => 10,
        };
    }

    public static function officialProjectReachLevelForScore(int $score): string
    {
        return match (true) {
            $score <= 2 => 'Sangat rendah',
            $score <= 4 => 'Rendah',
            $score <= 6 => 'Sedang',
            $score <= 8 => 'Tinggi',
            $score <= 9 => 'Sangat tinggi',
            default => 'Luar biasa/nasional',
        };
    }

    public static function officialProjectReachBandForReaders(int $readers): string
    {
        return 'Perkiraan ' . number_format(max(1, $readers), 0, ',', '.') . ' pembaca';
    }

    public function scopeCompleteOfficialAiResult(Builder $query): Builder
    {
        return $query
            ->where('analysis_status', 'success')
            ->where('reach_method', 'ai_reader_estimate_v1')
            ->whereNotNull('project_estimated_readers')
            ->where('project_estimated_readers', '>=', 1)
            ->whereNotNull('project_reach_score')
            ->whereNotNull('project_reach_level')
            ->whereNotNull('project_reach_band');
    }

    /**
     * @deprecated Use hasOfficialArticleReach() instead.
     */
    public function hasOfficialProjectReach(): bool
    {
        return $this->hasOfficialArticleReach();
    }

    /**
     * @deprecated Use officialArticleEstimatedReaders() instead.
     */
    public function officialProjectEstimatedReaders(): ?int
    {
        return $this->officialArticleEstimatedReaders();
    }
}
