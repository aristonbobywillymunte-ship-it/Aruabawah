<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapingItem extends Model
{
    protected $fillable = [
        'candidate_link_id',
        'url',
        'status',
        'retry_count',
        'last_attempt_at',
        'error_message',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'retry_count' => 'integer',
    ];
}
