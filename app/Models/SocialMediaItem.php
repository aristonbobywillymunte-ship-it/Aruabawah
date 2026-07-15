<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SocialMediaItem extends Model
{
    protected $fillable = [
        'project_id',
        'platform',
        'post_url',
        'author_name',
        'author_url',
        'content',
        'posted_at',
        'like_count',
        'comment_count',
        'share_count',
        'view_count',
        'follower_count',
        'raw_json',
    ];

    protected $casts = [
        'posted_at'      => 'datetime',
        'like_count'     => 'integer',
        'comment_count'  => 'integer',
        'share_count'    => 'integer',
        'view_count'     => 'integer',
        'follower_count' => 'integer',
    ];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_social_media_items')
            ->withTimestamps();
    }

    public function aiAnalysisResult(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AiAnalysisResult::class);
    }
}
