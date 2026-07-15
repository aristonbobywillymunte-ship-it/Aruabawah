<?php

namespace Tests\Feature;

use App\Jobs\TelegramNotificationJob;
use App\Models\TelegramSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramNotificationSanitizationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_sanitizes_telegram_token_and_url_from_exception_messages(): void
    {
        $articleId = DB::table('articles')->insertGetId([
            'title' => 'Telegram Sanitization Test',
            'content' => str_repeat('Sanitization article content. ', 40),
            'url' => 'https://example.com/articles/telegram-sanitization',
            'canonical_url' => 'https://example.com/articles/telegram-sanitization',
            'source_name' => 'Example Source',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $analysisId = DB::table('ai_analysis_results')->insertGetId([
            'article_id' => $articleId,
            'social_media_item_id' => null,
            'summary' => 'Telegram sanitization test',
            'sentiment' => 'negative',
            'sentiment_score' => 0.1,
            'main_issue' => 'Security',
            'entities' => json_encode(['Telegram']),
            'risk_level' => 'high',
            'risk_reason' => 'Sanitization test',
            'reach_estimate' => 120,
            'reach_score_10' => 8,
            'reach_score_max' => 10,
            'reach_level' => 'High',
            'reach_trend' => 'stable',
            'reach_source' => 'test',
            'reach_confidence' => 'medium',
            'reach_reason' => 'Sanitization test',
            'recommendation' => 'No action',
            'raw_response' => '{}',
            'analysis_status' => 'success',
            'validation_errors' => null,
            'local_relevance_score' => 85,
            'estimated_reach_band' => 'Perkiraan 120 pembaca',
            'confidence_score' => 65,
            'confidence_level' => 'Medium',
            'signals_used' => json_encode(['test']),
            'reasoning_summary' => 'Test reason',
            'limitations' => 'Test limitations',
            'is_exact_reach' => false,
            'reach_method' => 'ai_reader_estimate_v1',
            'potential_reach_score' => 8,
            'potential_reach_level' => 'High',
            'potential_reach_band' => 'Perkiraan 120 pembaca',
            'potential_estimated_readers' => 120,
            'project_estimated_readers' => 80,
            'project_reach_score' => 7,
            'project_reach_level' => 'High',
            'project_reach_band' => 'Perkiraan 80 pembaca relevan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('risk_notifications')->insert([
            'ai_analysis_result_id' => $analysisId,
            'status' => 'pending',
            'error_message' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TelegramSetting::updateOrCreate(
            ['id' => 1],
            [
                'bot_token' => '1234567890:AAValidTelegramTokenForTesting000000000000',
                'default_chat_id' => '-100123456789',
                'is_active' => true,
            ]
        );

        Http::fake([
            'https://api.telegram.org/bot*' => function () {
                throw new \RuntimeException('cURL error 28: Operation timed out for https://api.telegram.org/bot1234567890:SECRET/sendMessage');
            },
        ]);

        (new TelegramNotificationJob([
            'ai_analysis_result_id' => $analysisId,
            'project_name' => 'Sanitization Project',
            'title' => 'Telegram Sanitization Test',
            'url' => 'https://example.com/articles/telegram-sanitization',
            'risk_level' => 'high',
            'sentiment' => 'negative',
            'reach_level' => 'high',
            'summary' => 'Sanitization test',
            'reason' => 'Sanitization test',
        ]))->handle();

        $row = DB::table('risk_notifications')->where('ai_analysis_result_id', $analysisId)->first();

        $this->assertNotNull($row);
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('Telegram request failed', (string) $row->error_message);
        $this->assertStringContainsString('cURL error 28', (string) $row->error_message);
        $this->assertStringNotContainsString('api.telegram.org/bot', (string) $row->error_message);
        $this->assertStringNotContainsString('SECRET', (string) $row->error_message);
    }
}
