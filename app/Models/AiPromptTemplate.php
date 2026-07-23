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

    public static function resolveActiveDefaultForSourceType(string $name, string $sourceType): ?self
    {
        $name = trim($name);
        $sourceType = trim($sourceType);

        return static::query()
            ->where('name', $name)
            ->where('source_type', $sourceType)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }
}
