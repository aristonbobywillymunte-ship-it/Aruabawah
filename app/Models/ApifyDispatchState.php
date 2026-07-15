<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApifyDispatchState extends Model
{
    protected $fillable = [
        'dispatch_key',
        'project_id',
        'actor_id',
        'platform',
        'keyword',
        'normalized_keyword',
        'window_start',
        'window_end',
        'status',
        'run_id',
        'attempts',
        'queued_at',
        'started_at',
        'completed_at',
        'next_retry_at',
        'last_error_code',
        'last_error_message',
    ];

    protected $casts = [
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
