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
            && in_array($this->getActiveConnectionStatus(), ['connected', 'ready'], true);
    }

    /**
     * Dapatkan connection_status sesuai dengan index yang diberikan.
     */
    public function getConnectionStatusByIndex(int $index): ?string
    {
        return match ($index) {
            1 => $this->connection_status_backup_1,
            2 => $this->connection_status_backup_2,
            3 => $this->connection_status_backup_3,
            default => $this->connection_status, // 0 = Utama
        };
    }

    /**
     * Dapatkan connection_status sesuai dengan token yang sedang aktif.
     */
    public function getActiveConnectionStatus(): ?string
    {
        return $this->getConnectionStatusByIndex((int) $this->active_token_index);
    }

    /**
     * Dapatkan token sesuai dengan index yang diberikan.
     */
    public function getTokenByIndex(int $index): ?string
    {
        return match ($index) {
            1 => $this->api_token_backup_1,
            2 => $this->api_token_backup_2,
            3 => $this->api_token_backup_3,
            default => $this->api_token, // 0 = Utama
        };
    }

    /**
     * Dapatkan token yang sedang aktif berdasarkan active_token_index.
     */
    public function getActiveToken(): ?string
    {
        return $this->getTokenByIndex((int) $this->active_token_index);
    }

    /**
     * Dapatkan label nama token berdasarkan index.
     */
    public function getTokenLabelByIndex(int $index): string
    {
        return match ($index) {
            1 => 'Token Backup 1 (Index 1)',
            2 => 'Token Backup 2 (Index 2)',
            3 => 'Token Backup 3 (Index 3)',
            default => 'Token Utama (Index 0)',
        };
    }

    /**
     * Dapatkan label nama token saat ini.
     */
    public function getActiveTokenLabel(): string
    {
        return $this->getTokenLabelByIndex((int) $this->active_token_index);
    }

    /**
     * Cari token berikutnya yang terisi dan berstatus ready/connected, 
     * mengabaikan index yang ada di dalam $excludedIndexes.
     */
    public function getNextEligibleTokenIndex(array $excludedIndexes = []): ?int
    {
        for ($i = 0; $i < 4; $i++) {
            if (in_array($i, $excludedIndexes, true)) {
                continue;
            }

            $token = $this->getTokenByIndex($i);
            $status = $this->getConnectionStatusByIndex($i);

            if (filled($token) && in_array($status, ['connected', 'ready'], true)) {
                return $i;
            }
        }

        return null;
    }
}
