<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApifySetting extends Model
{
    protected $fillable = [
        'api_token',
        'api_token_backup_1',
        'api_token_backup_2',
        'api_token_backup_3',
        'active_token_index',
        'connection_status',
        'connection_status_backup_1',
        'connection_status_backup_2',
        'connection_status_backup_3',
        'last_test_status',
        'last_test_dataset_id',
        'last_test_message',
        'last_test_at',
    ];

    protected $casts = [
        'api_token' => 'encrypted',
        'api_token_backup_1' => 'encrypted',
        'api_token_backup_2' => 'encrypted',
        'api_token_backup_3' => 'encrypted',
        'active_token_index' => 'integer',
        'last_test_at' => 'datetime',
    ];

    public function isReadyForScraping(): bool
    {
        return filled($this->getActiveToken())
            && in_array($this->connection_status, ['connected', 'ready'], true);
    }

    /**
     * Dapatkan token yang sedang aktif berdasarkan active_token_index.
     */
    public function getActiveToken(): ?string
    {
        return match ((int) $this->active_token_index) {
            1 => $this->api_token_backup_1,
            2 => $this->api_token_backup_2,
            3 => $this->api_token_backup_3,
            default => $this->api_token, // 0 = Utama
        };
    }

    /**
     * Dapatkan label nama token saat ini.
     */
    public function getActiveTokenLabel(): string
    {
        return match ((int) $this->active_token_index) {
            1 => 'Token Backup 1 (Index 1)',
            2 => 'Token Backup 2 (Index 2)',
            3 => 'Token Backup 3 (Index 3)',
            default => 'Token Utama (Index 0)',
        };
    }

    /**
     * Rotasi otomatis ke token berikutnya jika limit tercapai (0 -> 1 -> 2 -> 3 -> 0).
     */
    public function rotateToNextToken(): string
    {
        $currentIndex = (int) $this->active_token_index;
        
        // Loop token yang tersedia
        $tokens = [
            0 => $this->api_token,
            1 => $this->api_token_backup_1,
            2 => $this->api_token_backup_2,
            3 => $this->api_token_backup_3,
        ];

        // Cari token berikutnya yang terisi
        for ($i = 1; $i <= 4; $i++) {
            $nextIndex = ($currentIndex + $i) % 4;
            if (filled($tokens[$nextIndex])) {
                $this->active_token_index = $nextIndex;
                $this->save();
                
                return $this->getActiveTokenLabel();
            }
        }

        return $this->getActiveTokenLabel();
    }
}
