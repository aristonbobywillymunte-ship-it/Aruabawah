<?php

namespace Tests\Feature;

use App\Jobs\AiAnalysisJob;
use App\Jobs\TelegramNotificationJob;
use App\Models\TelegramSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PortalNotificationGateTest extends TestCase
{
    public function test_low_and_medium_results_do_not_create_notification_noise(): void
    {
        DB::beginTransaction();
        Queue::fake();

        $probe = new class extends AiAnalysisJob {
            public function __construct()
            {
                parent::__construct([]);
            }

            public function simulate(int $analysisId, array $normalized, bool $suppressTelegram = false): array
            {
                $shouldNotify = ($normalized['analysis_status'] ?? 'success') === 'success'
                    && (
                        ($normalized['risk_level'] === 'high' || $normalized['risk_level'] === 'critical')
                        || ($normalized['risk_level'] === 'medium' && $normalized['reach_level'] === 'high')
                    );

                $telegramSetting = \App\Models\TelegramSetting::first();
                $dispatched = false;

                if ($shouldNotify && ! $suppressTelegram && $telegramSetting && $telegramSetting->isReadyForNotifications()) {
                    $shouldDispatchNotification = $this->upsertRiskNotification($analysisId);
                    if ($shouldDispatchNotification) {
                        TelegramNotificationJob::dispatch([
                            'ai_analysis_result_id' => $analysisId,
                            'title' => 'Simulated Alert',
                            'url' => 'https://example.com/test',
                            'risk_level' => $normalized['risk_level'],
                            'reach_level' => $normalized['reach_level'],
                            'sentiment' => $normalized['sentiment'] ?? 'negative',
                            'summary' => 'Simulated summary',
                            'reason' => 'Simulated reason',
                        ])->onQueue('notification');
                        $dispatched = true;
                    }
                }

                return [
                    'should_notify' => $shouldNotify,
                    'dispatched' => $dispatched,
                    'risk_rows' => DB::table('risk_notifications')->where('ai_analysis_result_id', $analysisId)->count(),
                ];
            }
        };

        $low = $probe->simulate(69, [
            'analysis_status' => 'success',
            'risk_level' => 'low',
            'reach_level' => 'low',
        ]);

        $medium = $probe->simulate(71, [
            'analysis_status' => 'success',
            'risk_level' => 'medium',
            'reach_level' => 'medium',
        ]);

        $this->assertFalse($low['should_notify']);
        $this->assertFalse($low['dispatched']);
        $this->assertSame(0, $low['risk_rows']);

        $this->assertFalse($medium['should_notify']);
        $this->assertFalse($medium['dispatched']);
        $this->assertSame(0, $medium['risk_rows']);

        Queue::assertNothingPushed();

        DB::rollBack();
    }

    public function test_placeholder_telegram_credentials_do_not_dispatch_notifications(): void
    {
        DB::beginTransaction();
        Queue::fake();

        $setting = TelegramSetting::first();
        if (!$setting) {
            $setting = TelegramSetting::create([
                'bot_token' => '1234567890:ABCdefGhIJKlmNoPQRsTUVwxyZ',
                'default_chat_id' => '-100123456789',
                'is_active' => true,
            ]);
        }
        $this->assertNotNull($setting);
        $this->assertFalse($setting->isReadyForNotifications());

        $probe = new class extends AiAnalysisJob {
            public function __construct()
            {
                parent::__construct([]);
            }

            public function simulate(int $analysisId, array $normalized, bool $suppressTelegram = false): array
            {
                $shouldNotify = ($normalized['analysis_status'] ?? 'success') === 'success'
                    && (
                        ($normalized['risk_level'] === 'high' || $normalized['risk_level'] === 'critical')
                        || ($normalized['risk_level'] === 'medium' && $normalized['reach_level'] === 'high')
                    );

                $telegramSetting = \App\Models\TelegramSetting::first();
                $dispatched = false;

                if ($shouldNotify && ! $suppressTelegram && $telegramSetting && $telegramSetting->isReadyForNotifications()) {
                    $shouldDispatchNotification = $this->upsertRiskNotification($analysisId);
                    if ($shouldDispatchNotification) {
                        TelegramNotificationJob::dispatch([
                            'ai_analysis_result_id' => $analysisId,
                            'title' => 'Simulated Alert',
                            'url' => 'https://example.com/test',
                            'risk_level' => $normalized['risk_level'],
                            'reach_level' => $normalized['reach_level'],
                            'sentiment' => $normalized['sentiment'] ?? 'negative',
                            'summary' => 'Simulated summary',
                            'reason' => 'Simulated reason',
                        ])->onQueue('notification');
                        $dispatched = true;
                    }
                }

                return [
                    'should_notify' => $shouldNotify,
                    'dispatched' => $dispatched,
                    'risk_rows' => DB::table('risk_notifications')->where('ai_analysis_result_id', $analysisId)->count(),
                ];
            }
        };

        $high = $probe->simulate(199, [
            'analysis_status' => 'success',
            'risk_level' => 'high',
            'reach_level' => 'high',
        ]);

        $this->assertTrue($high['should_notify']);
        $this->assertFalse($high['dispatched']);
        $this->assertSame(0, $high['risk_rows']);
        Queue::assertNothingPushed();

        DB::rollBack();
    }

    public function test_high_result_creates_one_notification_and_does_not_duplicate_on_second_run(): void
    {
        DB::beginTransaction();
        Queue::fake();

        TelegramSetting::updateOrCreate(
            ['id' => 1],
            [
                'bot_token' => '123456:AAValidTelegramTokenForTests000000000000',
                'default_chat_id' => '-100987654321',
                'is_active' => true,
            ]
        );

        $probe = new class extends AiAnalysisJob {
            public function __construct()
            {
                parent::__construct([]);
            }

            public function simulate(int $analysisId, array $normalized): array
            {
                $telegramSetting = \App\Models\TelegramSetting::first();
                $dispatched = false;

                if ($telegramSetting && $telegramSetting->isReadyForNotifications()) {
                    $shouldDispatchNotification = $this->upsertRiskNotification($analysisId);
                    if ($shouldDispatchNotification) {
                        TelegramNotificationJob::dispatch([
                            'ai_analysis_result_id' => $analysisId,
                            'title' => 'Simulated High Alert',
                            'url' => 'https://example.com/high-risk',
                            'risk_level' => $normalized['risk_level'],
                            'reach_level' => $normalized['reach_level'],
                            'sentiment' => $normalized['sentiment'] ?? 'negative',
                            'summary' => 'Simulated summary',
                            'reason' => 'Simulated reason',
                        ])->onQueue('notification');
                        $dispatched = true;
                    }
                }

                return [
                    'dispatched' => $dispatched,
                    'risk_rows' => DB::table('risk_notifications')->where('ai_analysis_result_id', $analysisId)->count(),
                ];
            }
        };

        $tempArticleId = DB::table('articles')->insertGetId([
            'title' => 'Test Article',
            'content' => 'Test Content',
            'url' => 'https://example.com',
            'source_name' => 'Test',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tempAnalysisId = DB::table('ai_analysis_results')->insertGetId([
            'article_id' => $tempArticleId,
            'social_media_item_id' => null,
            'summary' => 'Temporary high-risk simulation',
            'sentiment' => 'negative',
            'sentiment_score' => 0.2,
            'main_issue' => 'Simulated issue',
            'entities' => json_encode(['Simulated']),
            'risk_level' => 'high',
            'risk_reason' => 'Simulated high risk',
            'reach_estimate' => 1000,
            'reach_score_10' => 8,
            'reach_score_max' => 10,
            'reach_level' => 'Tinggi',
            'local_relevance_score' => 90,
            'estimated_reach_band' => 'Perkiraan 1000 pembaca',
            'confidence_score' => 65,
            'confidence_level' => 'Medium',
            'reach_trend' => 'stable',
            'reach_source' => 'test',
            'reach_confidence' => 'medium',
            'reach_reason' => 'test',
            'signals_used' => json_encode(['test']),
            'reasoning_summary' => 'test',
            'limitations' => 'test',
            'is_exact_reach' => false,
            'reach_method' => 'ai_reader_estimate_v1',
            'potential_estimated_readers' => 1000,
            'potential_reach_score' => 8,
            'potential_reach_level' => 'Tinggi',
            'potential_reach_band' => 'Perkiraan 1000 pembaca',
            'project_estimated_readers' => 1000,
            'project_reach_score' => 8,
            'project_reach_level' => 'Tinggi',
            'project_reach_band' => 'Perkiraan 1000 pembaca relevan',
            'analysis_status' => 'success',
            'validation_errors' => null,
            'recommendation' => 'test',
            'raw_response' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $first = $probe->simulate($tempAnalysisId, [
            'risk_level' => 'high',
            'reach_level' => 'high',
        ]);

        $second = $probe->simulate($tempAnalysisId, [
            'risk_level' => 'high',
            'reach_level' => 'high',
        ]);

        $this->assertTrue($first['dispatched']);
        $this->assertSame(1, $first['risk_rows']);

        $this->assertFalse($second['dispatched']);
        $this->assertSame(1, $second['risk_rows']);

        Queue::assertPushed(TelegramNotificationJob::class, 1);
        Queue::assertPushed(TelegramNotificationJob::class, function ($job) {
            return $job->queue === 'notification';
        });

        DB::rollBack();
    }
}
