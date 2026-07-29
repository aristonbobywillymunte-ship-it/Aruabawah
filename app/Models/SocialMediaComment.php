<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMediaComment extends Model
{
    protected $fillable = [
        'social_media_item_id',
        'platform',
        'comment_id',
        'parent_comment_id',
        'author_name',
        'author_url',
        'avatar_url',
        'content',
        'like_count',
        'posted_at',
        'raw_json',
    ];

    protected $casts = [
        'like_count' => 'integer',
        'posted_at' => 'datetime',
    ];

    public function socialMediaItem(): BelongsTo
    {
        return $this->belongsTo(SocialMediaItem::class);
    }
}
