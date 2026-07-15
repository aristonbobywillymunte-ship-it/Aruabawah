<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapingSetting extends Model
{
    protected $fillable = [
        'google_news_interval',
        'portal_crawling_interval',
        'limit_per_run',
        'date_range',
        'timeout_seconds',
        'retry_limit',
        'retry_delay_minutes',
        'is_active',
        'enable_realtime',
    ];

    protected $casts = [
        'google_news_interval' => 'integer',
        'portal_crawling_interval' => 'integer',
        'limit_per_run' => 'integer',
        'timeout_seconds' => 'integer',
        'retry_limit' => 'integer',
        'retry_delay_minutes' => 'integer',
        'is_active' => 'boolean',
        'enable_realtime' => 'boolean',
    ];
}
