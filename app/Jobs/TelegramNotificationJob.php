<?php

namespace App\Jobs;

use App\Models\TelegramSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TelegramNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;
    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        Log::channel('telegram')->info('[Pipeline] TelegramNotificationJob started');
        $lock = Cache::lock('telegram-notification-send-lock', 180);

        try {
            $lock->block(5);

            $setting = TelegramSetting::first();
            $status = $setting?->notificationCredentialStatus() ?? [
                'ready' => false,
                'issues' => ['missing_setting'],
            ];

            if (! $setting || ! $status['ready']) {
                Log::channel('telegram')->warning('[Pipeline] Telegram notification skipped: Bot credentials are invalid or incomplete.', [
                    'issues' => $status['issues'] ?? [],
                ]);

                if (!empty($this->data['ai_analysis_result_id'])) {
                    DB::table('risk_notifications')
                        ->where('ai_analysis_result_id', $this->data['ai_analysis_result_id'])
                        ->update([
                            'status' => 'skipped_invalid_credentials',
                            'error_message' => 'Telegram credentials invalid or incomplete.',
                            'updated_at' => now(),
                        ]);
                }

                return;
            }

            $analysisId = $this->data['ai_analysis_result_id'] ?? null;
            $projectId = $this->data['project_id'] ?? null;

            // Fetch recipients
            $recipients = [];
            if ($projectId) {
                $recipients = DB::table('project_telegram_recipients')
                    ->where('project_id', $projectId)
                    ->where('is_active', true)
                    ->pluck('chat_id')
                    ->toArray();
            }

            if (empty($recipients)) {
                $recipients = [$setting->default_chat_id];
            }

            $recipients = array_filter(array_unique($recipients));

            if (empty($recipients)) {
                Log::channel('telegram')->warning('[Pipeline] Telegram notification skipped: No recipients found.');
                return;
            }

            $sentRecipientsKey = "telegram_sent_recipients_{$analysisId}";
            $sentRecipients = Cache::get($sentRecipientsKey, []);

            $hasAnySuccess = false;
            $hasAnyFailure = false;
            $lastErrorMessage = null;
            $isAnyAuthError = false;

            $esc = fn($str) => htmlspecialchars((string) $str, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

            $message = "⚠️ <b>ANALISIS RISIKO KRISIS TERDETEKSI</b> ⚠️\n\n";
            $message .= "📁 <b>Proyek:</b> " . strtoupper($esc($this->data['project_name'] ?? 'UMUM')) . "\n";
            $message .= "📌 <b>Judul:</b> " . $esc($this->data['title'] ?? 'N/A') . "\n";
            $message .= "📰 <b>Sumber:</b> " . $esc($this->data['source_name'] ?? 'N/A') . "\n";
            $message .= "🔗 <b>Tautan:</b> <a href=\"" . $esc($this->data['url'] ?? '#') . "\">Baca Artikel Lengkap</a>\n";
            $message .= "🚨 <b>Risk Level:</b> " . strtoupper($esc($this->data['risk_level'] ?? 'MEDIUM')) . "\n";
            $message .= "📊 <b>Sentiment:</b> " . strtoupper($esc($this->data['sentiment'] ?? 'NEGATIVE')) . "\n";
            $message .= "📈 <b>Reach Level:</b> " . strtoupper($esc($this->data['reach_level'] ?? 'N/A')) . "\n\n";
            $message .= "📝 <b>Ringkasan:</b> " . $esc($this->data['summary'] ?? '') . "\n";
            $message .= "🎯 <b>Alasan:</b> " . $esc($this->data['reason'] ?? '') . "\n";

            Log::info('[Pipeline] Message body: ' . $message);

            $totalRecipientsCount = count($recipients);
            $currentIndex = 0;

            foreach ($recipients as $targetChatId) {
                $currentIndex++;

                // Skip if already sent to this recipient
                if (in_array($targetChatId, $sentRecipients, true)) {
                    Log::channel('telegram')->info("[Pipeline] Skip recipient {$targetChatId}: Already sent previously.");
                    $hasAnySuccess = true;
                    continue;
                }

                // Check cooldown for temporary failure (3 minutes)
                $cooldownKey = "telegram_cooldown_{$targetChatId}";
                if (Cache::has($cooldownKey)) {
                    Log::channel('telegram')->warning("[Pipeline] Skip recipient {$targetChatId}: In 3-minute failure cooldown.");
                    $hasAnyFailure = true;
                    $lastErrorMessage = "Recipient {$targetChatId} is in 3-minute failure cooldown.";
                    continue;
                }

                // Wait 1 minute (60 seconds) delay before next send (if we have sent something in this loop)
                if ($hasAnySuccess && $currentIndex > 1) {
                    Log::channel('telegram')->info('[Pipeline] Waiting 1 minute (60 seconds) delay before next send...');
                    sleep(60);
                }

                Log::channel('telegram')->info('[Pipeline] Sending alert message to Telegram Chat ID: ' . $targetChatId);

                $response = null;
                $sendError = null;

                try {
                    // Timeout set to 30 seconds as requested by the user
                    $response = Http::timeout(30)->post("https://api.telegram.org/bot{$setting->bot_token}/sendMessage", [
                        'chat_id' => $targetChatId,
                        'text' => $message,
                        'parse_mode' => 'HTML',
                        'disable_web_page_preview' => true,
                    ]);
                } catch (\Throwable $e) {
                    $sendError = $e;
                }

                if ($response && $response->successful()) {
                    Log::channel('telegram')->info("[Pipeline] Telegram notification delivered successfully to {$targetChatId}.");
                    $sentRecipients[] = $targetChatId;
                    Cache::put($sentRecipientsKey, $sentRecipients, now()->addDays(7));
                    $hasAnySuccess = true;
                } else {
                    $statusCode = $response ? (int) $response->status() : 500;
                    $responseBody = $response ? (string) $response->body() : ($sendError ? $sendError->getMessage() : 'No response');
                    $messageError = $this->sanitizeTelegramError($statusCode, $responseBody, $sendError);

                    Log::channel('telegram')->error("[Pipeline] Telegram notification failed for recipient {$targetChatId}", [
                        'chat_id' => $targetChatId,
                        'status_code' => $statusCode,
                        'message' => $messageError,
                    ]);

                    $lastErrorMessage = $messageError;

                    if ($this->isPermanentTelegramError($statusCode, $responseBody)) {
                        // Permanent failure: deactivate the recipient in the database
                        if ($projectId) {
                            DB::table('project_telegram_recipients')
                                ->where('project_id', $projectId)
                                ->where('chat_id', $targetChatId)
                                ->update([
                                    'is_active' => false,
                                    'updated_at' => now()
                                ]);
                            Log::channel('telegram')->warning("[Pipeline] Recipient {$targetChatId} deactivated permanently due to error: {$messageError}");
                        }
                    } else {
                        // Temporary failure: set 3-minute cooldown
                        Cache::put($cooldownKey, true, now()->addMinutes(3));
                        Log::channel('telegram')->info("[Pipeline] Recipient {$targetChatId} placed on a 3-minute cooldown due to temporary error.");
                    }

                    if ($statusCode === 401) {
                        $isAnyAuthError = true;
                    }
                    $hasAnyFailure = true;
                }
            }

            if ($analysisId) {
                if (!$hasAnyFailure) {
                    DB::table('risk_notifications')
                        ->where('ai_analysis_result_id', $analysisId)
                        ->update([
                            'status' => 'sent',
                            'error_message' => null,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('risk_notifications')
                        ->where('ai_analysis_result_id', $analysisId)
                        ->update([
                            'status' => $isAnyAuthError ? 'auth_error' : 'failed',
                            'error_message' => $lastErrorMessage ?? 'Some recipients failed to receive the notification.',
                            'updated_at' => now(),
                        ]);
                }
            }
        } catch (\Throwable $e) {
            $messageError = $this->sanitizeTelegramError(exception: $e);

            Log::channel('telegram')->error('[Pipeline] Telegram notification failed', [
                'provider' => 'telegram',
                'error_code' => class_basename($e),
                'message' => $messageError,
            ]);

            if (!empty($this->data['ai_analysis_result_id'])) {
                DB::table('risk_notifications')
                    ->where('ai_analysis_result_id', $this->data['ai_analysis_result_id'])
                    ->update([
                        'status' => 'failed',
                        'error_message' => $messageError,
                        'updated_at' => now(),
                    ]);
            }

            return;
        } finally {
            optional($lock)->release();
        }
    }

    private function isPermanentTelegramError(int $statusCode, string $responseBody): bool
    {
        if (in_array($statusCode, [401, 403], true)) {
            return true;
        }

        if ($statusCode === 400) {
            $body = strtolower($responseBody);
            return str_contains($body, 'chat not found')
                || str_contains($body, 'deactivated')
                || str_contains($body, 'kicked')
                || str_contains($body, 'blocked')
                || str_contains($body, 'invalid')
                || str_contains($body, 'not found');
        }

        return false;
    }

    private function sanitizeTelegramError(?int $statusCode = null, ?string $responseBody = null, ?\Throwable $exception = null): string
    {
        $messageParts = ['Telegram request failed'];

        if ($exception !== null) {
            $messageParts[] = class_basename($exception);
            if ($exception->getCode() !== 0) {
                $messageParts[] = 'code ' . $exception->getCode();
            }
            $rawMessage = $exception->getMessage();
            if ($rawMessage !== '') {
                $messageParts[] = $this->maskTelegramSecrets($rawMessage);
            }
        } elseif ($statusCode !== null) {
            $messageParts[] = 'HTTP ' . $statusCode;
            if ($statusCode === 401) {
                $messageParts[] = 'Unauthorized';
            } elseif ($statusCode === 403) {
                $messageParts[] = 'Forbidden';
            }
            $sanitizedBody = $this->maskTelegramSecrets((string) $responseBody);
            $sanitizedBody = trim((string) preg_replace('/\s+/u', ' ', $sanitizedBody));
            if ($sanitizedBody !== '') {
                $messageParts[] = mb_substr($sanitizedBody, 0, 160);
            }
        }

        $message = trim(implode(': ', array_filter($messageParts, fn ($part) => $part !== '')));
        $message = trim((string) preg_replace('/\s+/u', ' ', $message));

        return mb_substr($message, 0, 500);
    }

    private function maskTelegramSecrets(string $message): string
    {
        $patterns = [
            '~https?://api\.telegram\.org/bot[^/\s]+~i' => 'Telegram API [masked]',
            '~api\.telegram\.org/bot[^/\s]+~i' => 'Telegram API [masked]',
            '~\bbot\d+:[A-Za-z0-9_-]{20,}\b~' => '[masked-telegram-token]',
            '~\b\d{5,}:[A-Za-z0-9_-]{20,}\b~' => '[masked-telegram-token]',
            '~(Authorization\s*:\s*Bearer\s+)[A-Za-z0-9._-]+~i' => '$1[masked]',
        ];

        return trim(str_replace(['  ', "\t", "\n", "\r"], ' ', preg_replace(array_keys($patterns), array_values($patterns), $message)));
    }

    private function isRetryableTelegramError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'curl error 28')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection refused');
    }
}
