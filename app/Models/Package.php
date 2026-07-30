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
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'social_media_features' => 'array',
        'news_portal_features' => 'array',
        'advantages' => 'array',
        'is_active' => 'boolean',
        'use_portal' => 'boolean',
    ];

    /**
     * Actor Apify yang terdaftar di paket ini.
     */
    public function actors(): BelongsToMany
    {
        return $this->belongsToMany(ApifyActor::class, 'package_actors')
            ->withPivot(['is_enabled', 'cost_per_run_usd'])
            ->withTimestamps();
    }

    /**
     * Actor yang aktif (is_enabled = true) di paket ini.
     */
    public function enabledActors(): BelongsToMany
    {
        return $this->belongsToMany(ApifyActor::class, 'package_actors')
            ->withPivot(['is_enabled', 'cost_per_run_usd'])
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
     * Jika ada override di pivot, gunakan itu. Jika tidak, pakai global actor.
     */
    public function getEffectiveCostForActor(ApifyActor $actor): ?float
    {
        $pivot = $this->actors()->where('apify_actors.id', $actor->id)->first()?->pivot;

        if ($pivot && $pivot->cost_per_run_usd !== null) {
            return (float) $pivot->cost_per_run_usd;
        }

        return $actor->maximum_cost_per_run_usd !== null ? (float) $actor->maximum_cost_per_run_usd : null;
    }
}
