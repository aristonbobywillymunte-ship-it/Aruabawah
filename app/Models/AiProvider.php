<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    protected $fillable = [
        'name',
        'provider_type',
        'base_url',
        'api_key',
        'model_name',
        'temperature',
        'max_tokens',
        'requests_per_minute',
        'custom_headers',
        'custom_body_template',
        'is_active',
        'is_default',
        'last_tested_at',
        'last_test_status',
        'last_error',
        'priority',
        'cooldown_until',
        'last_failure_code',
        'capabilities',
    ];

    protected $casts = [
        'temperature' => 'float',
        'max_tokens' => 'integer',
        'requests_per_minute' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'last_tested_at' => 'datetime',
        'cooldown_until' => 'datetime',
        'priority' => 'integer',
        'capabilities' => 'array',
    ];

    public function isEligibleForUse(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->cooldown_until && $this->cooldown_until->isFuture()) {
            return false;
        }

        return true;
    }

    public static function syncDefaultToEligible(): ?self
    {
        $currentDefault = static::query()
            ->where('is_default', true)
            ->first();

        if ($currentDefault && $currentDefault->isEligibleForUse()) {
            return $currentDefault->refresh();
        }

        $eligible = static::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('cooldown_until')
                    ->orWhere('cooldown_until', '<=', now());
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        if ($eligible->isEmpty()) {
            static::query()->where('is_default', true)->update(['is_default' => false]);
            return null;
        }

        $preferred = $eligible->first();
        static::query()->where('is_default', true)->update(['is_default' => false]);
        $preferred->forceFill(['is_default' => true])->save();

        return $preferred->refresh();
    }
}
