<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsSourceSuggestion extends Model
{
    protected $fillable = [
        'news_source_id',
        'suggested_by',
        'source_name',
        'domain',
        'base_url',
        'crawling_type',
        'search_url',
        'feed_url',
        'sitemap_url',
        'search_result_selector',
        'article_link_selector',
        'article_content_selector',
        'article_author_selector',
        'article_date_selector',
        'article_noise_selector',
        'confidence',
        'ai_reason',
        'status',
        'test_result_json',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'confidence' => 'float',
        'test_result_json' => 'array',
        'approved_at' => 'datetime',
    ];

    public function newsSource(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
