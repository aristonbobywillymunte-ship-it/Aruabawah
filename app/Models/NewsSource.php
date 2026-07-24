<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsSource extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name',
        'domain',
        'icon_url',
        'source_type',
        'media_scope',
        'dewan_pers_status',
        'base_url',
        'feed_url',
        'search_url',
        'sitemap_url',
        'search_result_selector',
        'article_link_selector',
        'article_content_selector',
        'article_author_selector',
        'article_date_selector',
        'article_noise_selector',
        'path_blocklist',
        'selector_blocklist',
        'local_reach_weight',
        'scrape_priority',
        'reach_notes',
        'is_search_enabled',
        'is_feed_enabled',
        'is_sitemap_enabled',
        'is_active',
        'crawling_type',
        'selector',
        'timeout_seconds',
        'notes',
    ];

    protected $casts = [
        'is_search_enabled' => 'boolean',
        'is_feed_enabled' => 'boolean',
        'is_sitemap_enabled' => 'boolean',
        'is_active' => 'boolean',
        'timeout_seconds' => 'integer',
    ];
}
