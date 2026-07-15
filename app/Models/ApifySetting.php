<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApifySetting extends Model
{
    protected $fillable = [
        'api_token',
        'connection_status',
        'last_test_status',
        'last_test_dataset_id',
        'last_test_message',
        'last_test_at',
    ];

    protected $casts = [
        'api_token' => 'encrypted',
        'last_test_at' => 'datetime',
    ];

    public function isReadyForScraping(): bool
    {
        return filled($this->api_token)
            && in_array($this->connection_status, ['connected', 'ready'], true);
    }
}
