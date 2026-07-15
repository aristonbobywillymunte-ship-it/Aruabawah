<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramSetting extends Model
{
    private const PLACEHOLDER_BOT_TOKEN = '1234567890:ABCdefGhIJKlmNoPQRsTUVwxyZ';

    protected $fillable = [
        'bot_token',
        'default_chat_id',
        'is_active',
    ];

    protected $casts = [
        'bot_token' => 'encrypted',
        'is_active' => 'boolean',
    ];

    public function isReadyForNotifications(): bool
    {
        return $this->notificationCredentialStatus()['ready'];
    }

    public function hasValidNotificationCredentials(): bool
    {
        return $this->isReadyForNotifications();
    }

    public function notificationCredentialIssues(): array
    {
        return $this->notificationCredentialStatus()['issues'];
    }

    public function notificationCredentialStatus(): array
    {
        $issues = [];

        if (! $this->is_active) {
            $issues[] = 'inactive';
        }

        $botToken = trim((string) $this->bot_token);
        if ($botToken === '') {
            $issues[] = 'missing_bot_token';
        } elseif ($this->isPlaceholderBotToken($botToken)) {
            $issues[] = 'placeholder_bot_token';
        } elseif (! preg_match('/^\\d{5,}:[A-Za-z0-9_-]{20,}$/', $botToken)) {
            $issues[] = 'invalid_bot_token_format';
        }

        $defaultChatId = trim((string) $this->default_chat_id);
        if ($defaultChatId === '') {
            $issues[] = 'missing_default_chat_id';
        } elseif (! preg_match('/^-?\\d+$/', $defaultChatId)) {
            $issues[] = 'invalid_default_chat_id_format';
        }

        return [
            'ready' => empty($issues),
            'issues' => $issues,
        ];
    }

    protected function isPlaceholderBotToken(string $botToken): bool
    {
        return $botToken === self::PLACEHOLDER_BOT_TOKEN;
    }
}
