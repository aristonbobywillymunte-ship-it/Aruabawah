<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiAnalysisDispatchState extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'analyzable_type',
        'analyzable_id',
        'project_id',
        'prompt_template_id',
        'provider_context_hash',
        'dispatch_key',
        'status',
        'attempts',
        'failure_category',
        'last_error_code',
        'error_message',
        'last_attempt_at',
        'last_failed_at',
        'next_retry_at',
        'completed_at',
        'meta_json',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'last_attempt_at' => 'datetime',
        'last_failed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'completed_at' => 'datetime',
        'meta_json' => 'array',
        'project_id' => 'integer',
        'prompt_template_id' => 'integer',
        'analyzable_id' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(AiPromptTemplate::class);
    }

    public function analyzable(): MorphTo
    {
        return $this->morphTo();
    }
}
