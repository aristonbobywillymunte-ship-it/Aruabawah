<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReachAssessment extends Model
{
    protected $fillable = [
        'project_id',
        'assessable_type',
        'assessable_id',
        'method',
        'score_version',
        'audience_capacity_score',
        'observed_consumption_score',
        'interaction_score',
        'diffusion_score',
        'media_context_score',
        'potential_hybrid_score',
        'potential_reach_score',
        'potential_reach_level',
        'local_relevance_score',
        'relevance_status',
        'adjusted_local_hybrid_score',
        'adjusted_local_reach_score',
        'adjusted_local_reach_level',
        'confidence_score',
        'confidence_level',
        'is_exact_reach',
        'signals_json',
        'explanation',
        'calculated_at',
    ];

    protected $casts = [
        'audience_capacity_score' => 'float',
        'observed_consumption_score' => 'float',
        'interaction_score' => 'float',
        'diffusion_score' => 'float',
        'media_context_score' => 'float',
        'potential_hybrid_score' => 'float',
        'potential_reach_score' => 'integer',
        'local_relevance_score' => 'float',
        'adjusted_local_hybrid_score' => 'float',
        'adjusted_local_reach_score' => 'integer',
        'confidence_score' => 'integer',
        'is_exact_reach' => 'boolean',
        'signals_json' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assessable(): MorphTo
    {
        return $this->morphTo();
    }
}
