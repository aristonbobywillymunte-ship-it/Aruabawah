<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Article extends Model
{
    protected $fillable = [
        'title',
        'content',
        'url',
        'canonical_url',
        'source_name',
        'author',
        'sentiment',
        'sentiment_score',
        'category',
        'published_at',
    ];



    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_articles')
            ->withTimestamps();
    }

    public function aiAnalysisResult(): HasOne
    {
        return $this->hasOne(AiAnalysisResult::class);
    }

    public function reachAssessments(): MorphMany
    {
        return $this->morphMany(ReachAssessment::class, 'assessable');
    }

    public function latestReachAssessment(): MorphOne
    {
        return $this->morphOne(ReachAssessment::class, 'assessable')->latestOfMany('calculated_at');
    }

    public function scopeWithCompleteOfficialAiResult(Builder $query): Builder
    {
        return $query->whereHas('aiAnalysisResult', function (Builder $ai) {
            $ai->completeOfficialAiResult();
        });
    }
}
