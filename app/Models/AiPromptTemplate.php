<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiPromptTemplate extends Model
{
    protected $fillable = [
        'name',
        'source_type',
        'system_prompt',
        'user_prompt_template',
        'output_schema',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];
}
