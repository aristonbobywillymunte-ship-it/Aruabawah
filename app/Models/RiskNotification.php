<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskNotification extends Model
{
    protected $fillable = [
        'ai_analysis_result_id',
        'status',
        'error_message',
    ];

    public function aiAnalysisResult(): BelongsTo
    {
        return $this->belongsTo(AiAnalysisResult::class);
    }
}
