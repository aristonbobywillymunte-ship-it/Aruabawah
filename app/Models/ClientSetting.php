<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientSetting extends Model
{
    protected $fillable = [
        'user_id',
        'can_create_projects',
        'can_edit_projects',
        'can_delete_projects',
        'max_projects',
        'max_keywords_per_project',
    ];

    protected $casts = [
        'can_create_projects' => 'boolean',
        'can_edit_projects' => 'boolean',
        'can_delete_projects' => 'boolean',
        'max_projects' => 'integer',
        'max_keywords_per_project' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
