<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'social_media_features',
        'news_portal_features',
        'advantages',
        'is_active',
        'use_portal',
        'news_interval_minutes',
        'social_interval_minutes',
        'is_popular',
        'max_projects',
        'max_keywords_per_project',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'social_media_features' => 'array',
        'news_portal_features' => 'array',
        'advantages' => 'array',
        'is_active' => 'boolean',
        'use_portal' => 'boolean',
        'news_interval_minutes' => 'integer',
        'social_interval_minutes' => 'integer',
        'is_popular' => 'boolean',
        'max_projects' => 'integer',
        'max_keywords_per_project' => 'integer',
    ];

    public function actors(): BelongsToMany
    {
        return $this->belongsToMany(ApifyActor::class, 'package_actors')
            ->withPivot(['is_enabled', 'cost_per_run_usd', 'default_limit', 'memory_limit'])
            ->withTimestamps();
    }

    /**
     * Actor yang aktif (is_enabled = true) di paket ini.
     */
    public function enabledActors(): BelongsToMany
    {
        return $this->belongsToMany(ApifyActor::class, 'package_actors')
            ->withPivot(['is_enabled', 'cost_per_run_usd', 'default_limit', 'memory_limit'])
            ->withTimestamps()
            ->wherePivot('is_enabled', true);
    }

    /**
     * Project yang menggunakan paket ini.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Jumlah actor yang aktif di paket ini.
     */
    public function getEnabledActorCountAttribute(): int
    {
        return $this->actors()->wherePivot('is_enabled', true)->count();
    }

    /**
     * Mendapatkan cost efektif actor dalam konteks paket ini.
     * Cost hanya boleh dibaca dari pivot paket aktif.
     */
    public function getEffectiveCostForActor(ApifyActor $actor): ?float
    {
        $pivot = $this->actors()->where('apify_actors.id', $actor->id)->first()?->pivot;

        if (! $pivot || $pivot->cost_per_run_usd === null) {
            return null;
        }

        return (float) $pivot->cost_per_run_usd;
    }

    /**
     * Mendapatkan limit memory efektif actor dalam konteks paket ini.
     * Nilai wajib berasal dari pivot paket aktif.
     */
    public function getEffectiveMemoryLimitForActor(ApifyActor $actor): ?int
    {
        $pivot = $this->actors()->where('apify_actors.id', $actor->id)->first()?->pivot;

        if (! $pivot || $pivot->memory_limit === null) {
            return null;
        }

        return (int) $pivot->memory_limit;
    }

    /**
     * Mendapatkan limit hasil efektif actor dalam konteks paket ini.
     * Nilai wajib berasal dari pivot paket aktif.
     */
    public function getEffectiveLimitForActor(ApifyActor $actor): ?int
    {
        $pivot = $this->actors()->where('apify_actors.id', $actor->id)->first()?->pivot;

        if (! $pivot || $pivot->default_limit === null) {
            return null;
        }

        return (int) $pivot->default_limit;
    }
}
