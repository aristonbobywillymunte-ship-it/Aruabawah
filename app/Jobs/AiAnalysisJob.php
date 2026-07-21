<?php

namespace App\Jobs;

use App\Models\AiPromptTemplate;
use App\Models\AiAnalysisResult;
use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Project;
use App\Models\NewsSource;
use App\Models\SocialMediaItem;
use App\Models\TelegramSetting;
use App\Services\AiAnalysisDispatchStateService;
use App\Services\AiProviderRouter;
use App\Queue\Middleware\AiAnalysisRateThrottle;
use App\Services\AllProvidersCoolingDownException;
use App\Services\AllProvidersFailedException;
use App\Services\RateLimitRetryException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MIN_ANALYSIS_LENGTH = 500;

    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
        $this->onConnection('redis-ai');
        $this->onQueue('ai-analysis');
    }

    public function middleware(): array
    {
        return [
            new AiAnalysisRateThrottle(5),
        ];
    }

    public function handle(): void
    {
        $dispatchStateService = app(AiAnalysisDispatchStateService::class);
        $type = $this->payload['type'] ?? null;
        Log::info('[Pipeline] AiAnalysisJob analyzing item type: ' . $type);
        $sourceType = $type === 'social' ? 'social' : 'article';
        $promptTemplateId = $this->payload['prompt_template_id'] ?? $dispatchStateService->resolvePromptTemplateId($sourceType);
        $providerContextHash = $this->payload['provider_context_hash'] ?? $dispatchStateService->resolveProviderContextHash();

        // Validasi kelayakan data (Exit Quietly jika data tidak memenuhi syarat)
        $content = trim($this->payload['content'] ?? '');
        if ($content === '') {
            Log::warning('[Pipeline] Lewati analisis: Konten berita/sosmed kosong (tidak layak dianalisis).');
            $dispatchStateService->markFailed(
                $this->payload,
                'empty_content',
                'Content is empty.',
                $promptTemplateId,
                $providerContextHash
            );
            return; // Keluar dengan aman, tidak dimasukkan ke failed_jobs
        }

        $minLen = ($type === 'social') ? 5 : self::MIN_ANALYSIS_LENGTH;
        if (mb_strlen($content) < $minLen) {
            Log::warning('[Pipeline] Lewati analisis: Konten terlalu pendek untuk AI analysis.', [
                'content_length' => mb_strlen($content),
                'min_required' => $minLen,
                'title' => $this->payload['title'] ?? null,
                'url' => $this->payload['url'] ?? null,
            ]);
            $dispatchStateService->markFailed(
                $this->payload,
                'content_too_short',
                'Content is too short for AI analysis.',
                $promptTemplateId,
                $providerContextHash
            );
            return;
        }

        $projectId = $this->payload['project_id'] ?? null;
        if ($projectId && !\App\Models\Project::where('id', $projectId)->exists()) {
            Log::warning("[Pipeline] Lewati analisis: Proyek ID {$projectId} tidak ditemukan di database.");
            $dispatchStateService->markFailed(
                $this->payload,
                'project_not_found',
                "Project ID {$projectId} not found.",
                $promptTemplateId,
                $providerContextHash
            );
            return; // Keluar dengan aman, tidak dimasukkan ke failed_jobs
        }

        $template = AiPromptTemplate::where('source_type', $type)->where('is_default', true)->where('is_active', true)->first();

        if (!$template) {
            Log::warning('[Pipeline] Missing active AI Prompt Template.');
            $dispatchStateService->markFailed(
                $this->payload,
                'missing_configuration',
                'AI prompt template is not ready.',
                $promptTemplateId,
                $providerContextHash
            );
            return;
        }

        $dispatchState = $dispatchStateService->claimProcessing($this->payload, $promptTemplateId, $providerContextHash);
        if (! $dispatchState) {
            Log::info('[Pipeline] Skip AI job because dispatch state is already settled or not due.', [
                'analyzable_type' => $type,
                'analyzable_id' => $this->payload['id'] ?? $this->payload['item_id'] ?? null,
                'project_id' => $this->payload['project_id'] ?? null,
                'dispatch_key' => app(AiAnalysisDispatchStateService::class)->buildDispatchKey(
                    (string) ($this->payload['type'] ?? 'article'),
                    (int) ($this->payload['id'] ?? $this->payload['item_id'] ?? 0),
                    isset($this->payload['project_id']) ? (int) $this->payload['project_id'] : null,
                    $promptTemplateId,
                    $providerContextHash
                ),
            ]);
            return;
        }

        $prompt = $this->buildPrompt($template, $this->payload);
        $rawText = null;
        $usedProvider = null;
        $fallbackCount = 0;
        
        $router = app(AiProviderRouter::class);

        try {
            $options = ['response_format' => 'json_object'];
            $result = $router->execute(
                $template->system_prompt,
                $prompt,
                $options,
                $this->payload['id'] ?? null,
                $type === 'social' ? 'project_insight' : 'article_analysis'
            );
            $usedProvider = $result['provider'];
            $rawText = $result['text'];
            $fallbackCount = $result['fallback_count'];
            $fallbackReason = $result['fallback_reason'] ?? null;
            $lastErrorCategory = $result['last_error_category'] ?? null;
            
        } catch (RateLimitRetryException $e) {
            // RateLimit for current provider - backoff and retry without exhausting all providers
            Log::warning("[Pipeline] Transient rate limit hit. Releasing job in {$e->delaySeconds}s.");
            $state = $dispatchStateService->markRetryWait(
                $this->payload,
                'rate_limit_wait',
                $e->getMessage(),
                $promptTemplateId,
                $providerContextHash,
                $e
            );
            $this->release($e->delaySeconds);
            return;
        } catch (AllProvidersFailedException $e) {
            $msg = '[Pipeline] All active AI providers failed.';
            Log::error($msg);
            
            $dispatchStateService->markFailed(
                $this->payload,
                'all_providers_failed',
                $msg,
                $promptTemplateId,
                $providerContextHash,
                $e
            );
            return;
        } catch (AllProvidersCoolingDownException $e) {
            $dispatchStateService->markRetryWait(
                $this->payload,
                'rate_limit',
                $e->getMessage(),
                $promptTemplateId,
                $providerContextHash,
                $e
            );
            $this->release(max(30, $e->delaySeconds));
            return;
        } catch (\Throwable $e) {
            // Unknown unexpected error
            $dispatchStateService->markFailed(
                $this->payload,
                'analysis_failed',
                $e->getMessage(),
                $promptTemplateId,
                $providerContextHash,
                $e
            );
            return;
        }

        try {

            $decoded = $this->decodeAiJson($rawText);
            if (! $decoded) {
                Log::error('[Pipeline] Failed to decode AI JSON response.', [
                    'provider' => $usedProvider?->provider_type,
                    'model' => $usedProvider?->model_name,
                    'raw_length' => mb_strlen(trim($rawText)),
                ]);
                $dispatchStateService->markFailed(
                    $this->payload,
                    'json_decode_failed',
                    'Failed to decode JSON response from AI.',
                    $promptTemplateId,
                    $providerContextHash
                );
                return;
            }

            [$normalized, $validation] = $this->normalizeAndValidateAnalysisResult($decoded, $usedProvider, $type);
            if (! $validation['valid']) {
                $retryPrompt = $this->buildValidationRetryPrompt($template, $this->payload, $validation['errors']);
                if ($retryPrompt !== null) {
                    try {
                        $retryResult = $router->execute(
                            $template->system_prompt,
                            $retryPrompt,
                            ['response_format' => 'json_object'],
                            $this->payload['id'] ?? null,
                            $type === 'social' ? 'project_insight' : 'article_analysis'
                        );
                        $retryRawText = $retryResult['text'];
                        $usedProvider = $retryResult['provider'];
                        $retryDecoded = $this->decodeAiJson($retryRawText);
                        if ($retryDecoded) {
                            [$retryNormalized, $retryValidation] = $this->normalizeAndValidateAnalysisResult($retryDecoded, $usedProvider, $type);
                            if ($retryValidation['valid']) {
                                $normalized = $retryNormalized;
                                $validation = $retryValidation;
                            } else {
                                $normalized['analysis_status'] = 'invalid_ai_reach';
                                $normalized['validation_errors'] = json_encode($retryValidation['errors']);
                            }
                        } else {
                            $normalized['analysis_status'] = 'invalid_ai_reach';
                            $normalized['validation_errors'] = json_encode(['retry_decode_failed']);
                        }
                    } catch (\Throwable $retryException) {
                        Log::warning('[Pipeline] AI reach validation retry failed', [
                            'message' => $retryException->getMessage(),
                        ]);
                        $normalized['analysis_status'] = 'invalid_ai_reach';
                        $normalized['validation_errors'] = json_encode($validation['errors']);
                    }
                } else {
                    $normalized['analysis_status'] = 'invalid_ai_reach';
                    $normalized['validation_errors'] = json_encode($validation['errors']);
                }
            }

            Log::info('[AiAudit] Metadata', [
                'provider_id_used' => $usedProvider?->id,
                'provider_name' => $usedProvider?->name,
                'model_used' => $usedProvider?->model_name,
                'fallback_count' => $fallbackCount,
                'fallback_reason' => $fallbackReason ?? null,
                'last_error_category' => $lastErrorCategory ?? null,
                'provider_context_hash' => $providerContextHash,
            ]);

            $analysisId = $this->persistAnalysis($normalized);

            if (($normalized['analysis_status'] ?? 'success') !== 'success') {
                $dispatchStateService->markFailed(
                    $this->payload,
                    'invalid_ai_reach',
                    'AI reach output failed validation and was not marked as official.',
                    $promptTemplateId,
                    $providerContextHash
                );

                Log::warning('[Pipeline] AI analysis completed but reach output is invalid.', [
                    'analysis_id' => $analysisId,
                    'article_id' => $normalized['article_id'],
                ]);
                return;
            }

            $dispatchStateService->markSuccess(
                $this->payload,
                $analysisId,
                $promptTemplateId,
                $providerContextHash
            );

            $this->ensureOfficialReachFields($analysisId, $normalized);
            $this->syncSourceRecord($normalized);

            Log::info('[Pipeline] AI analysis result completed', [
                'article_id' => $normalized['article_id'],
                'social_media_item_id' => $normalized['social_media_item_id'],
                'sentiment' => $normalized['sentiment'],
                'risk_level' => $normalized['risk_level'],
                'reach_estimate' => $normalized['reach_estimate'],
            ]);
        } catch (\Throwable $e) {
            $failure = $dispatchStateService->classifyFailure($e);
            if (($failure['status'] ?? 'failed') === 'retry_wait') {
                $state = $dispatchStateService->markRetryWait(
                    $this->payload,
                    $failure['code'] ?? 'transient_error',
                    $failure['message'] ?? 'AI analysis failed.',
                    $promptTemplateId,
                    $providerContextHash,
                    $e
                );
                $delay = $state && $state->next_retry_at ? now()->diffInSeconds($state->next_retry_at) : 60;
                $this->release($delay);
            } else {
                $dispatchStateService->markFailed(
                    $this->payload,
                    $failure['code'] ?? 'analysis_failed',
                    $failure['message'] ?? 'AI analysis failed.',
                    $promptTemplateId,
                    $providerContextHash,
                    $e
                );
            }

            Log::warning('[Pipeline] AI analysis failed safely without queue failure.', [
                'category' => $failure['category'] ?? 'unknown_error',
                'error_code' => $failure['code'] ?? class_basename($e),
                'message' => $failure['message'] ?? 'AI analysis failed safely.',
                'status' => $failure['status'] ?? 'failed',
            ]);

            return;
        }

        // Gunakan field canonical (potential_reach_level) untuk keputusan notifikasi,
        // BUKAN reach_level legacy yang selalu bernilai 'Unknown' pada hasil AI v2.
        $canonicalReachLevel = strtolower((string) ($normalized['potential_reach_level'] ?? $normalized['reach_level'] ?? ''));
        $shouldNotify = ($normalized['analysis_status'] ?? 'success') === 'success'
            && (
                ($normalized['risk_level'] === 'high' || $normalized['risk_level'] === 'critical')
                || ($normalized['risk_level'] === 'medium' && in_array($canonicalReachLevel, ['tinggi', 'sangat tinggi', 'high'], true))
            );

        $suppressTelegram = (bool) ($this->payload['no_telegram'] ?? false);
        $telegramSetting = TelegramSetting::first();
        $telegramStatus = $telegramSetting?->notificationCredentialStatus() ?? [
            'ready' => false,
            'issues' => ['missing_setting'],
        ];

        if ($shouldNotify && ! $suppressTelegram && $telegramSetting && $telegramStatus['ready']) {
            $shouldDispatchNotification = $this->upsertRiskNotification($analysisId);

            if ($shouldDispatchNotification) {
                $projectName = Project::find($this->payload['project_id'])?->name ?? 'N/A';
                TelegramNotificationJob::dispatch([
                    'ai_analysis_result_id' => $analysisId,
                    'project_id' => $this->payload['project_id'],
                    'project_name' => $projectName,
                    'title' => $this->payload['title'] ?? 'Postingan Media Sosial',
                    'url' => $this->payload['url'] ?? '',
                    'source_name' => $this->payload['source_name'] ?? 'Google News',
                    'risk_level' => $normalized['risk_level'],
                    'reach_level' => $normalized['potential_reach_level'] ?? $normalized['reach_level'],
                    'sentiment' => $normalized['sentiment'],
                    'summary' => $normalized['summary'],
                    'reason' => $normalized['risk_reason'],
                ])->delay(now()->addMinute())->onQueue('notification');
            } else {
                Log::info('[Pipeline] Telegram notification dispatch skipped: notification already pending or sent.', [
                    'ai_analysis_result_id' => $analysisId,
                ]);
            }
        } elseif ($shouldNotify && ! $suppressTelegram) {
            Log::warning('[Pipeline] Telegram notification skipped: setting is inactive or incomplete.', [
                'issues' => $telegramStatus['issues'] ?? [],
            ]);
        }

        // Broadcast event real-time bahwa analisis artikel baru telah selesai
        event(new \App\Events\RealtimeNotificationEvent('article_analyzed', 'Analisis Selesai', 'Analisis berita/sosmed terbaru selesai diproses oleh AI.', [
            'analysis_id' => $analysisId,
            'article_id' => $normalized['article_id'],
            'social_media_item_id' => $normalized['social_media_item_id']
        ]));
    }

    protected function buildPrompt(AiPromptTemplate $template, array $payload): string
    {
        $replacements = [
            '{title}' => (string) ($payload['title'] ?? ''),
            '{content}' => (string) ($payload['content'] ?? ''),
            '{platform}' => (string) ($payload['platform'] ?? ''),
            '{url}' => (string) ($payload['url'] ?? ''),
            '{media_type}' => (string) ($payload['media_type'] ?? 'text'),
            '{media_url}' => (string) ($payload['media_url'] ?? ''),
            '{thumbnail_url}' => (string) ($payload['thumbnail_url'] ?? ''),
            '{source_name}' => (string) ($payload['source_name'] ?? ''),
            '{author_name}' => (string) ($payload['author_name'] ?? ''),
            '{author_url}' => (string) ($payload['author_url'] ?? ''),
            '{published_at}' => (string) ($payload['published_at'] ?? ''),
            '{engagement_context}' => $this->buildEngagementContext($payload),
            '{media_context}' => $this->buildMediaContext($payload),
            '{project_context}' => $this->buildProjectContext($payload),
            '{reach_context}' => $this->buildReachContext($payload),
        ];

        $schema = $this->effectiveOutputSchema($template);
        $instruction = $template->system_prompt . "\n\n";
        $instruction .= strtr($template->user_prompt_template, $replacements) . "\n\n";
        $instruction .= "AI wajib menghasilkan estimasi pembaca dengan field berikut: project_estimated_readers, potential_estimated_readers, potential_reach_score, potential_reach_level, potential_reach_band, local_relevance_score, confidence_score, confidence_level, signals_used, reasoning_summary, limitations, is_exact_reach (false), reach_method (ai_reader_estimate_v1).\n";
        if (($template->source_type ?? 'article') === 'social') {
            $instruction .= "Untuk sosial media, prioritaskan penilaian berdasarkan link konten, jenis media, caption, konteks visual, dan engagement bila tersedia. Jika link atau thumbnail mengarah ke video/foto/carousel, gunakan itu sebagai sinyal utama untuk menentukan apakah kontennya video, gambar, carousel, atau teks.\n";
            $instruction .= "Jangan menebak isi visual secara berlebihan; jika media tidak bisa diakses, sebutkan keterbatasan secara eksplisit di limitations.\n";
        }
        $instruction .= "Estimasi pembaca harus berupa integer natural yang spesifik dan tidak boleh dipaksa ke angka bulat generik seperti 100, 500, atau 1000 tanpa alasan kuat. Gunakan angka yang terasa realistis dari sinyal konten, misalnya 187, 326, 847, atau 1.173 bila konteksnya mendukung.\n";
        $instruction .= "project_estimated_readers adalah estimasi jumlah pembaca artikel secara umum. Jangan gunakan angka random atau string rentang (misal '10-20'). Nilai ini harus dihitung berdasarkan kekuatan dan skala media, posisi artikel, karakter isu, dan distribusi.\n";
        $instruction .= "Jangan mengurangi atau mengubah nilai project_estimated_readers berdasarkan relevansi artikel terhadap project. Nilai nol tidak diperbolehkan.\n";
        $instruction .= "potential_estimated_readers: Estimasi potensi pembaca artikel SECARA UMUM. Artikel di portal besar bisa memiliki potential_estimated_readers besar. (Catatan: Nilai ini biasanya hampir sama dengan project_estimated_readers karena keduanya kini menghitung konteks artikel umum).\n";
        $instruction .= "Jangkauan umum (potential) adalah estimasi seluruh pembaca artikel.\n";
        $instruction .= "Score dan level WAJIB mengikuti tabel berikut berdasarkan estimasi pembaca (potential_estimated_readers):\n";
        $instruction .= "1-20 pembaca -> Skor 1 (Sangat rendah)\n";
        $instruction .= "21-40 pembaca -> Skor 2 (Sangat rendah)\n";
        $instruction .= "41-70 pembaca -> Skor 3 (Rendah)\n";
        $instruction .= "71-100 pembaca -> Skor 4 (Rendah)\n";
        $instruction .= "101-150 pembaca -> Skor 5 (Sedang)\n";
        $instruction .= "151-200 pembaca -> Skor 6 (Sedang)\n";
        $instruction .= "201-350 pembaca -> Skor 7 (Cukup tinggi)\n";
        $instruction .= "351-600 pembaca -> Skor 8 (Tinggi)\n";
        $instruction .= "601-999 pembaca -> Skor 9 (Sangat tinggi)\n";
        $instruction .= ">=1000 pembaca -> Skor 10 (Luar biasa/nasional)\n";
        $instruction .= "Band (string) harus mendeskripsikan rentang tersebut (contoh: '1-20 pembaca', '71-100 pembaca', '>=1.000 pembaca').\n";
        $instruction .= "Jika analytics nyata tidak ada, estimasi harus konservatif (confidence maksimal 69 dan level Medium).\n";
        $instruction .= "Balas hanya dengan JSON valid tanpa markdown atau teks tambahan.\n";

        if ($schema !== '') {
            $instruction .= "Gunakan schema berikut sebagai format output:\n" . $schema;
        }

        return $instruction;
    }

    protected function effectiveOutputSchema(AiPromptTemplate $template): string
    {
        if ($template->source_type !== 'article') {
            $schema = trim((string) ($template->output_schema ?? ''));
            if ($schema === '' && $template->source_type === 'social') {
                return json_encode([
                    'type' => 'object',
                    'properties' => [
                        'summary' => ['type' => 'string'],
                        'sentiment' => ['type' => 'string'],
                        'sentiment_score' => ['type' => 'number'],
                        'main_issue' => ['type' => 'string'],
                        'entities' => ['type' => 'array'],
                        'risk_level' => ['type' => 'string'],
                        'risk_reason' => ['type' => 'string'],
                        'reach_estimate' => ['type' => 'integer'],
                        'reach_score_10' => ['type' => 'integer'],
                        'reach_level' => ['type' => 'string'],
                        'reach_trend' => ['type' => 'string'],
                        'reach_source' => ['type' => 'string'],
                        'reach_confidence' => ['type' => 'string'],
                        'reach_reason' => ['type' => 'string'],
                        'content_type' => ['type' => 'string'],
                        'media_type' => ['type' => 'string'],
                        'media_link_used' => ['type' => 'string'],
                        'media_signal' => ['type' => 'string'],
                        'local_relevance_score' => ['type' => 'integer'],
                        'confidence_score' => ['type' => 'integer'],
                        'confidence_level' => ['type' => 'string'],
                        'signals_used' => ['type' => 'array'],
                        'reasoning_summary' => ['type' => 'string'],
                        'limitations' => ['type' => 'string'],
                        'recommendation' => ['type' => 'string'],
                    ],
                    'required' => [
                        'summary',
                        'sentiment',
                        'sentiment_score',
                        'main_issue',
                        'entities',
                        'risk_level',
                        'risk_reason',
                        'reach_estimate',
                        'reach_score_10',
                        'reach_level',
                        'reach_trend',
                        'reach_source',
                        'reach_confidence',
                        'reach_reason',
                        'content_type',
                        'media_type',
                        'media_link_used',
                        'media_signal',
                        'local_relevance_score',
                        'confidence_score',
                        'confidence_level',
                        'signals_used',
                        'reasoning_summary',
                        'limitations',
                        'recommendation',
                    ],
                ], JSON_UNESCAPED_UNICODE);
            }

            return $schema;
        }

        return json_encode([
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'sentiment' => ['type' => 'string'],
                'sentiment_score' => ['type' => 'number'],
                'main_issue' => ['type' => 'string'],
                'entities' => ['type' => 'array'],
                'risk_level' => ['type' => 'string'],
                'risk_reason' => ['type' => 'string'],
                'potential_estimated_readers' => ['type' => 'integer', 'minimum' => 1],
                'project_estimated_readers' => ['type' => 'integer', 'minimum' => 1],
                'potential_reach_score' => ['type' => 'integer'],
                'potential_reach_level' => ['type' => 'string'],
                'potential_reach_band' => ['type' => 'string'],
                'local_relevance_score' => ['type' => 'integer'],
                'confidence_score' => ['type' => 'integer'],
                'confidence_level' => ['type' => 'string'],
                'signals_used' => ['type' => 'array'],
                'reasoning_summary' => ['type' => 'string'],
                'limitations' => ['type' => 'string'],
                'is_exact_reach' => ['type' => 'boolean'],
                'reach_method' => ['type' => 'string'],
                'recommendation' => ['type' => 'string'],
            ],
            'required' => [
                'potential_estimated_readers',
                'project_estimated_readers',
                'potential_reach_score',
                'potential_reach_level',
                'potential_reach_band',
                'local_relevance_score',
                'confidence_score',
                'confidence_level',
                'signals_used',
                'reasoning_summary',
                'limitations',
                'is_exact_reach',
                'reach_method'
            ]
        ], JSON_UNESCAPED_UNICODE);
    }




    protected function decodeAiJson(string $rawText): ?array
    {
        $trimmed = trim($rawText);
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    protected function normalizeAndValidateAnalysisResult(array $decoded, AiProvider $usedProvider, ?string $type): array
    {
        $normalized = $this->normalizeAnalysisResult($decoded, $usedProvider, $type ?? 'article');
        $errors = $this->validateAiReachConsistency($normalized);
        $validation = [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
        
        if ($validation['valid']) {
            $normalized['analysis_status'] = 'success';
            $normalized['validation_errors'] = null;
        }
        
        return [$normalized, $validation];
    }

    protected function normalizeAnalysisResult(array $result, AiProvider $provider, string $type): array
    {
        $sentiment = strtolower((string) ($result['sentiment'] ?? 'neutral'));
        if (!in_array($sentiment, ['positive', 'neutral', 'negative'], true)) {
            $sentiment = 'neutral';
        }

        $riskLevel = strtolower((string) ($result['risk_level'] ?? 'low'));
        if (!in_array($riskLevel, ['low', 'medium', 'high', 'critical'], true)) {
            $riskLevel = 'low';
        }

        $reachTrend = strtolower((string) ($result['reach_trend'] ?? 'stable'));
        if (!in_array($reachTrend, ['up', 'down', 'stable'], true)) {
            $reachTrend = 'stable';
        }

        $reachConfidence = strtolower((string) ($result['reach_confidence'] ?? 'medium'));
        if (!in_array($reachConfidence, ['low', 'medium', 'high'], true)) {
            $reachConfidence = 'medium';
        }

        $rawReaders = $result['potential_estimated_readers'] ?? 0;
        if (is_string($rawReaders)) {
            $rawReaders = str_replace(['.', ','], '', $rawReaders);
        } elseif (is_float($rawReaders)) {
            $str = (string) $rawReaders;
            if (strpos($str, '.') !== false && strlen(substr($str, strpos($str, '.') + 1)) === 3) {
                $rawReaders = str_replace('.', '', $str);
            } else {
                $rawReaders = round($rawReaders);
            }
        }
        $potentialEstimatedReaders = (int) $rawReaders;

        $projectReaders = isset($result['project_estimated_readers']) 
            && is_numeric($result['project_estimated_readers'])
            ? (int) $result['project_estimated_readers']
            : null;
        $potentialReachScore = (int) ($result['potential_reach_score'] ?? 0);
        $potentialReachLevel = (string) ($result['potential_reach_level'] ?? '');
        $potentialReachBand = (string) ($result['potential_reach_band'] ?? '');
        $localRelevanceScore = (int) ($result['local_relevance_score'] ?? 0);
        $confidenceScore = (int) ($result['confidence_score'] ?? 0);
        $confidenceLevel = (string) ($result['confidence_level'] ?? '');
        
        $signalsUsed = $result['signals_used'] ?? [];
        if (!is_array($signalsUsed)) {
            $signalsUsed = [$signalsUsed];
        }
        $reasoningSummary = (string) ($result['reasoning_summary'] ?? '');
        $limitations = (string) ($result['limitations'] ?? '');
        $isExactReach = (bool) ($result['is_exact_reach'] ?? false);
        $reachMethod = trim((string) ($result['reach_method'] ?? 'ai_reader_estimate_v1'));
        if ($reachMethod !== 'ai_reader_estimate_v1') {
            $reachMethod = 'ai_reader_estimate_v1';
        }

        return [
            'article_id' => $this->payload['id'] ?? null,
            'social_media_item_id' => $this->payload['item_id'] ?? null,
            'summary' => (string) ($result['summary'] ?? ''),
            'sentiment' => $sentiment,
            'sentiment_score' => (float) ($result['sentiment_score'] ?? 0),
            'main_issue' => (string) ($result['main_issue'] ?? 'Umum'),
            'entities' => json_encode($result['entities'] ?? []),
            'risk_level' => $riskLevel,
            'risk_reason' => (string) ($result['risk_reason'] ?? ''),
            'potential_estimated_readers' => $potentialEstimatedReaders,
            'project_estimated_readers' => $projectReaders,
            'potential_reach_score' => $potentialReachScore,
            'potential_reach_level' => $potentialReachLevel,
            'potential_reach_band' => $potentialReachBand,
            'local_relevance_score' => $localRelevanceScore,
            'confidence_score' => $confidenceScore,
            'confidence_level' => $confidenceLevel,
            'signals_used' => json_encode(array_values($signalsUsed)),
            'reasoning_summary' => $reasoningSummary,
            'limitations' => $limitations,
            'is_exact_reach' => $isExactReach,
            'reach_method' => $reachMethod,
            'recommendation' => (string) ($result['recommendation'] ?? ''),
            'raw_response' => json_encode($result),
            'updated_at' => now(),
            // ─── LEGACY PLACEHOLDER ─── Diisi HANYA untuk memenuhi constraint NOT NULL
            // pada kolom skema v1. Nilai-nilai ini BUKAN data bisnis dan TIDAK BOLEH
            // digunakan untuk keputusan (notifikasi, sorting, validasi, UI).
            // Field canonical yang benar: potential_reach_score,
            // potential_estimated_readers.
            'reach_estimate' => 0,
            'reach_score_10' => 0,      // DEPRECATED – gunakan potential_reach_score / project_reach_score
            'reach_score_max' => 10,
            'reach_level' => 'Unknown', // DEPRECATED – gunakan potential_reach_level / project_reach_level
            'estimated_reach_band' => 'Unknown',
            'reach_trend' => 'stable',
            'reach_source' => 'unknown',
            'reach_confidence' => 'low',
            'reach_reason' => 'Legacy field – not used in business logic',
        ];
    }

    protected function normalizeReachLevel(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'local' => 'Local',
            'medium', 'regional' => 'Medium',
            'high', 'national' => 'High',
            'viral' => 'Viral',
            default => 'Low',
        };
    }

    protected function normalizeConfidenceLevel(string $value): string
    {
        return match (strtolower(trim($value))) {
            'high' => 'High',
            'medium' => 'Medium',
            default => 'Low',
        };
    }

    protected function normalizeReachScore(mixed $value): int
    {
        return max(1, min(10, (int) round((float) $value)));
    }

    protected function scoreToLevel(int $score): string
    {
        return match (true) {
            $score <= 2 => 'Sangat rendah',
            $score <= 4 => 'Rendah',
            $score <= 6 => 'Sedang',
            $score <= 8 => 'Tinggi',
            default => 'Sangat tinggi',
        };
    }

    protected function expectedScoreForReaders(int $readers): int
    {
        return match (true) {
            $readers <= 20 => 1,
            $readers <= 40 => 2,
            $readers <= 70 => 3,
            $readers <= 100 => 4,
            $readers <= 150 => 5,
            $readers <= 200 => 6,
            $readers <= 350 => 7,
            $readers <= 600 => 8,
            $readers <= 999 => 9,
            default => 10,
        };
    }

    protected function validateAiReachConsistency(array $normalized): array
    {
        $errors = [];

        $potReaders = (int) ($normalized['potential_estimated_readers'] ?? 0);

        if ($potReaders < 1) $errors[] = 'potential_estimated_readers must be >= 1';

        $projectReaders = $normalized['project_estimated_readers'] ?? null;
        if ($projectReaders === null) {
            $errors[] = 'article_readers_missing';
        } elseif (!is_int($projectReaders)) {
            $errors[] = 'article_readers_invalid_type';
        } elseif ($projectReaders < 1) {
            $errors[] = 'article_readers_too_low';
        }

        $this->validateScoreLevelBand(
            $potReaders,
            $normalized['potential_reach_score'] ?? null,
            $normalized['potential_reach_level'] ?? null,
            $normalized['potential_reach_band'] ?? null,
            'potential',
            $errors
        );



        $confidenceScore = (int) ($normalized['confidence_score'] ?? 0);
        $confidenceLevel = (string) ($normalized['confidence_level'] ?? '');
        if ((! $this->hasConsumptionSignals($normalized)) && ($confidenceScore > 69 || $confidenceLevel === 'High')) {
            $errors[] = 'confidence too high without consumption signals';
        }

        if (!empty($normalized['is_exact_reach'])) {
            $errors[] = 'is_exact_reach must be false';
        }

        return $errors;
    }

    protected function validateScoreLevelBand(int $readers, mixed $score, mixed $level, mixed $band, string $prefix, array &$errors): void
    {
        $expectedScore = $this->expectedScoreForReaders($readers);
        $score = (int) round((float) $score);

        if ($score < 1 || $score > 10) {
            $errors[] = "{$prefix} score must be 1-10";
        } elseif ($score !== $expectedScore) {
            $errors[] = "{$prefix} score ({$score}) inconsistent with readers ({$readers}), expected {$expectedScore}";
        }

        $expectedLevel = match ($score) {
            1, 2 => 'Sangat rendah',
            3, 4 => 'Rendah',
            5, 6 => 'Sedang',
            7 => 'Cukup tinggi',
            8 => 'Tinggi',
            9 => 'Sangat tinggi',
            default => 'Luar biasa/nasional',
        };

        if (!is_string($level) || trim($level) === '') {
            $errors[] = "{$prefix} level missing";
        } elseif (strtolower($expectedLevel) !== strtolower($level)) {
            $errors[] = "{$prefix} level ({$level}) inconsistent with score ({$score}), expected {$expectedLevel}";
        }
    }

    protected function normalizeBandText(string $band): string
    {
        return preg_replace('/\s+/', ' ', strtolower(trim($band))) ?? strtolower(trim($band));
    }

    protected function buildValidationRetryPrompt(AiPromptTemplate $template, array $payload, array $errors): ?string
    {
        if (empty($errors)) {
            return null;
        }

        $base = $this->buildPrompt($template, $payload);
        return $base . "\n\nPerbaiki konsistensi output JSON berdasarkan validation_errors berikut: " . implode('; ', $errors) . ". Pastikan score serta level sesuai dengan tabel mapping pembaca, dan estimasi pembaca tetap natural tanpa pembulatan generik yang tidak didukung sinyal. Kembalikan JSON valid saja tanpa tambahan markdown.\n";
    }

    protected function hasConsumptionSignals(array $result): bool
    {
        $candidates = [
            $this->payload['views'] ?? null,
            $this->payload['view_count'] ?? null,
            $this->payload['pageviews'] ?? null,
            $this->payload['unique_reach'] ?? null,
            $this->payload['engagement'] ?? null,
            $this->payload['engagement_score'] ?? null,
            $result['views'] ?? null,
            $result['view_count'] ?? null,
            $result['pageviews'] ?? null,
            $result['unique_reach'] ?? null,
            $result['engagement'] ?? null,
            $result['engagement_score'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (float) $candidate > 0) {
                return true;
            }
            if (is_string($candidate) && trim($candidate) !== '' && trim($candidate) !== '0') {
                return true;
            }
        }

        return false;
    }

    protected function calibrateReachForLocalContext(
        int $reachEstimate,
        int $reachScore10,
        string $reachLevel,
        string $reachSource,
        string $reachReason
    ): array {
        if (($this->payload['type'] ?? null) !== 'article') {
            return [$reachEstimate, $reachScore10, $reachLevel, $reachSource, $reachReason];
        }

        $sourceName = (string) ($this->payload['source_name'] ?? '');
        $title = mb_strtolower((string) ($this->payload['title'] ?? ''));
        $content = mb_strtolower((string) ($this->payload['content'] ?? ''));
        $sourceKey = mb_strtolower($sourceName);

        $isKaltimLocal = $this->isKaltimLocalSource($sourceKey);
        $isPolitical = $this->containsAny($title . ' ' . $content, [
            'rudy mas',
            'rudy mas’ud',
            "rudy mas'ud",
            'gubernur',
            'kaltim',
            'samarinda',
            'asn',
            'pemprov',
            'politik',
            'pilkada',
            'dprd',
        ]);

        if (!$isKaltimLocal || !$isPolitical) {
            return [$reachEstimate, $reachScore10, $reachLevel, $reachSource, $reachReason];
        }

        $sourceBand = $this->localSourceBand($sourceKey);
        $intensity = 0;

        if ($this->containsAny($title, ['arogan', 'dilaporkan', 'kpk', 'kejagung', 'mundur', 'lengser', 'kecewa', 'skandal'])) {
            $intensity += 1;
        }
        if ($this->containsAny($title, ['lantik', 'asn', 'pendidikan', 'gratispol', 'janji', 'program', 'peluang'])) {
            $intensity += 1;
        }
        if ($this->containsAny($title, ['kompas', 'antara', 'bisnis.com', 'idn times'])) {
            $intensity += 1;
        }

        $baseMin = $sourceBand['min'];
        $baseMax = $sourceBand['max'];
        $spread = max(1, $baseMax - $baseMin);
        $seed = abs(crc32($sourceName . '|' . $title));
        $offset = $seed % $spread;
        $candidate = $baseMin + $offset + ($intensity * 15);
        $candidate = min($baseMax, max($baseMin, $candidate));

        if ($candidate % 10 === 0) {
            $candidate += 3;
        } elseif ($candidate % 5 === 0) {
            $candidate += 2;
        } elseif ($candidate % 2 === 0) {
            $candidate += 1;
        }

        $reachEstimate = $candidate;
        if ($this->containsAny($sourceKey, ['niaga.asia', 'prokal', 'katakaltim', 'nomorsatukaltim'])) {
            $reachScore10 = 10;
        } else {
            $reachScore10 = match (true) {
                $reachEstimate >= 500 => 5,
                $reachEstimate >= 150 => 3,
                $reachEstimate >= 25 => 2,
                default => 1,
            };
        }

        $reachLevel = $reachEstimate >= 350 ? 'regional' : 'local';
        $reachSource = $sourceName !== '' ? $sourceName : $reachSource;
        $reachReason = 'Dikalibrasi untuk konteks portal lokal Kaltim/Samarinda berdasarkan skala media, jenis isu politik, dan pola pembaca regional.';

        return [$reachEstimate, $reachScore10, $reachLevel, $reachSource, $reachReason];
    }

    protected function isKaltimLocalSource(string $sourceKey): bool
    {
        return !$this->containsAny($sourceKey, [
            'detik',
            'kompas',
            'antara',
            'tribun',
            'bisnis.com',
            'idn times',
            'liputan6',
            'merdeka',
            'viva.co',
            'cnn',
            'tempo',
            'kumparan',
            'jawapos',
            'sindonews',
            'okezone',
            'jpnn',
        ]);
    }

    protected function localSourceBand(string $sourceKey): array
    {
        return match (true) {
            // National / Large Media
            str_contains($sourceKey, 'detik'),
            str_contains($sourceKey, 'kompas'),
            str_contains($sourceKey, 'antara'),
            str_contains($sourceKey, 'bisnis.com'),
            str_contains($sourceKey, 'idn times'),
            str_contains($sourceKey, 'liputan6'),
            str_contains($sourceKey, 'merdeka'),
            str_contains($sourceKey, 'viva'),
            str_contains($sourceKey, 'cnn'),
            str_contains($sourceKey, 'tempo'),
            str_contains($sourceKey, 'kumparan'),
            str_contains($sourceKey, 'jawapos'),
            str_contains($sourceKey, 'sindonews'),
            str_contains($sourceKey, 'okezone'),
            str_contains($sourceKey, 'jpnn') => ['min' => 150, 'max' => 850],

            // High-Impact Regional
            str_contains($sourceKey, 'niaga.asia'),
            str_contains($sourceKey, 'prokal'),
            str_contains($sourceKey, 'katakaltim'),
            str_contains($sourceKey, 'nomorsatukaltim') => ['min' => 25, 'max' => 45],

            // Standard Regional
            str_contains($sourceKey, 'pusaranmedia'),
            str_contains($sourceKey, 'kpfm'),
            str_contains($sourceKey, 'ibukotakini'),
            str_contains($sourceKey, 'adakah'),
            str_contains($sourceKey, 'faktual'),
            str_contains($sourceKey, 'kaltim') => ['min' => 5, 'max' => 25],

            // Low-tier / Unknown
            default => ['min' => 1, 'max' => 4],
        };
    }

    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    protected function persistAnalysis(array $normalized): int
    {
        $analysisKey = [
            'article_id' => $normalized['article_id'],
            'social_media_item_id' => $normalized['social_media_item_id'],
        ];

        $analysisPayload = $normalized;
        unset($analysisPayload['article_id'], $analysisPayload['social_media_item_id']);

        $existingAnalysisId = DB::table('ai_analysis_results')
            ->where($analysisKey)
            ->value('id');

        if ($existingAnalysisId) {
            DB::table('ai_analysis_results')
                ->where('id', $existingAnalysisId)
                ->update($analysisPayload);

            return (int) $existingAnalysisId;
        }

        return (int) DB::table('ai_analysis_results')->insertGetId(array_merge(
            $analysisKey,
            $analysisPayload,
            ['created_at' => now()]
        ));
    }

    protected function syncSourceRecord(array $normalized): void
    {
        if ($normalized['article_id']) {
            Article::where('id', $normalized['article_id'])->update([
                'sentiment' => $normalized['sentiment'],
                'sentiment_score' => $normalized['sentiment_score'],
                'category' => $normalized['main_issue'],
            ]);
        }

        if ($normalized['social_media_item_id']) {
            $currentRawJson = SocialMediaItem::where('id', $normalized['social_media_item_id'])->value('raw_json');
            $existing = json_decode((string) ($currentRawJson ?? '{}'), true);
            if (!is_array($existing)) {
                $existing = [];
            }

            $existing['ai_analysis'] = [
                'summary' => $normalized['summary'],
                'sentiment' => $normalized['sentiment'],
                'sentiment_score' => $normalized['sentiment_score'],
                'main_issue' => $normalized['main_issue'],
                'risk_level' => $normalized['risk_level'],
                'risk_reason' => $normalized['risk_reason'],
            'local_relevance_score' => $normalized['local_relevance_score'],
            'potential_estimated_readers' => $normalized['potential_estimated_readers'],
            'potential_reach_score' => $normalized['potential_reach_score'],
            'potential_reach_level' => $normalized['potential_reach_level'],
            'potential_reach_band' => $normalized['potential_reach_band'],
            'confidence_score' => $normalized['confidence_score'],
            'confidence_level' => $normalized['confidence_level'],
            'signals_used' => $normalized['signals_used'],
            'reasoning_summary' => $normalized['reasoning_summary'],
            'limitations' => $normalized['limitations'],
            'is_exact_reach' => $normalized['is_exact_reach'],
            'reach_method' => $normalized['reach_method'],
            'recommendation' => $normalized['recommendation'],
        ];

            SocialMediaItem::where('id', $normalized['social_media_item_id'])->update([
                'raw_json' => json_encode($existing),
            ]);
        }
    }

    protected function ensureOfficialReachFields(int $analysisId, array $normalized): void
    {
        $projectReaders = $normalized['project_estimated_readers'] ?? null;
        if (! is_numeric($projectReaders) || (int) $projectReaders < 1) {
            if (! empty($normalized['article_id'])) {
                BackfillArticleReadersJob::dispatch([
                    'type' => 'article',
                    'id' => $normalized['article_id'],
                    'project_id' => $this->payload['project_id'] ?? null,
                    'title' => $this->payload['title'] ?? '',
                    'content' => $this->payload['content'] ?? '',
                    'url' => $this->payload['url'] ?? '',
                    'source_name' => $this->payload['source_name'] ?? '',
                    'published_at' => $this->payload['published_at'] ?? null,
                ])->onConnection('redis-ai')->onQueue('ai-backfill');
            }

            return;
        }

        $readers = (int) $projectReaders;
        $score = AiAnalysisResult::officialProjectReachScoreForReaders($readers);
        $level = AiAnalysisResult::officialProjectReachLevelForScore($score);
        $band = AiAnalysisResult::officialProjectReachBandForReaders($readers);

        DB::table('ai_analysis_results')
            ->where('id', $analysisId)
            ->update([
                'project_estimated_readers' => $readers,
                'project_reach_score' => $score,
                'project_reach_level' => $level,
                'project_reach_band' => $band,
                'reach_method' => 'ai_reader_estimate_v1',
                'updated_at' => now(),
            ]);
    }

    protected function upsertRiskNotification(int $analysisId): bool
    {
        $existingNotification = DB::table('risk_notifications')
            ->where('ai_analysis_result_id', $analysisId)
            ->first(['id', 'status']);

        if ($existingNotification) {
            if (in_array($existingNotification->status, ['pending', 'sent'], true)) {
                return false;
            }

            DB::table('risk_notifications')
                ->where('id', $existingNotification->id)
                ->update([
                    'status' => 'pending',
                    'error_message' => null,
                    'updated_at' => now(),
                ]);
            return true;
        }

        DB::table('risk_notifications')->insert([
            'ai_analysis_result_id' => $analysisId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    protected function generateRealisticMockResponse(array $payload): string
    {
        $title = $payload['title'] ?? 'Artikel Baru';
        $content = $payload['content'] ?? $title;
        $sourceName = $payload['source_name'] ?? 'Google News';

        $haystack = mb_strtolower($title . ' ' . $content);

        $sentiment = 'neutral';
        $sentimentScore = 0.0;
        if ($this->containsAny($haystack, ['pemadaman', 'kecewa', 'arogan', 'korupsi', 'salah', 'demo', 'angket', 'rugi', 'turun', 'kritik', 'perang dingin', 'perang'])) {
            $sentiment = 'negative';
            $sentimentScore = -0.5 - (abs(crc32($title)) % 5) / 10;
        } elseif ($this->containsAny($haystack, ['sukses', 'dukung', 'aman', 'prestasi', 'apresiasi', 'menang', 'lancar', 'bantu', 'harmonis', 'akrab'])) {
            $sentiment = 'positive';
            $sentimentScore = 0.5 + (abs(crc32($title)) % 5) / 10;
        }

        $riskLevel = 'low';
        $riskReason = 'Tidak terdeteksi krisis reputasi serius.';
        if ($sentiment === 'negative') {
            if ($this->containsAny($haystack, ['perang dingin', 'pecah', 'korupsi', 'demo', 'krisis', 'angket'])) {
                $riskLevel = 'high';
                $riskReason = 'Terdeteksi potensi krisis reputasi/konflik politik dari isi berita.';
            } else {
                $riskLevel = 'medium';
                $riskReason = 'Berita bernada negatif ringan/kritik publik.';
            }
        }

        $mainIssue = 'Pemerintahan & Kebijakan';
        if ($this->containsAny($haystack, ['pemadaman', 'listrik'])) {
            $mainIssue = 'Infrastruktur & Layanan Publik';
        } elseif ($this->containsAny($haystack, ['umkm', 'ekraf', 'belanja'])) {
            $mainIssue = 'Ekonomi & Bisnis';
        } elseif ($this->containsAny($haystack, ['pilkada', 'politik', 'fraksi', 'angket', 'hubungan'])) {
            $mainIssue = 'Politik & Pilkada';
        }

        $sourceKey = mb_strtolower($sourceName);
        
        $band = $this->localSourceBand($sourceKey);
        $baseMin = $band['min'];
        $baseMax = $band['max'];

        $seed = abs(crc32($title . '|' . $sourceName));
        $reachEstimate = $baseMin + ($seed % ($baseMax - $baseMin + 1));
        
        if ($reachEstimate % 10 === 0) {
            $reachEstimate += 3;
        }

        if ($this->containsAny($sourceKey, ['niaga.asia', 'prokal', 'katakaltim', 'nomorsatukaltim'])) {
            $reachScore10 = 10;
        } else {
            $reachScore10 = match (true) {
                $reachEstimate >= 500 => 5,
                $reachEstimate >= 150 => 3,
                $reachEstimate >= 25 => 2,
                default => 1,
            };
        }

        $reachLevel = $reachEstimate >= 350 ? 'regional' : 'local';

        $mockJson = [
            'summary' => "AI menyimpulkan bahwa berita berjudul '{$title}' membahas tentang " . mb_strtolower($mainIssue) . " di daerah Kaltim.",
            'sentiment' => $sentiment,
            'sentiment_score' => $sentimentScore,
            'main_issue' => $mainIssue,
            'entities' => array_values(array_filter([
                str_contains($haystack, 'seno aji') ? 'Seno Aji' : null,
                str_contains($haystack, 'rudy mas') ? "Rudy Mas'ud" : null,
                $sourceName
            ])),
            'risk_level' => $riskLevel,
            'risk_reason' => $riskReason,
            'potential_estimated_readers' => $reachEstimate,
            'potential_reach_score' => $reachScore10,
            'potential_reach_level' => $reachLevel,
            'potential_reach_band' => 'Perkiraan ' . $reachEstimate . ' pembaca',
            'local_relevance_score' => $this->containsAny($haystack, ['kaltim', 'kalimantan timur', 'samarinda']) ? 80 : 30,
            'confidence_score' => 55,
            'confidence_level' => 'Medium',
            'signals_used' => ['title', 'content', 'source_name'],
            'reasoning_summary' => 'Penilaian reach berbasis isi artikel, reputasi media, dan konteks isu lokal.',
            'limitations' => 'Tidak ada pageview, unique reach, atau engagement real-time.',
            'is_exact_reach' => false,
            'reach_method' => 'ai_reader_estimate_v1',
            'recommendation' => $sentiment === 'negative' ? 'Pantau terus tanggapan publik di media sosial terkait isu ini.' : 'Pertahankan publikasi berita bernada positif/netral ini.'
        ];

        return json_encode($mockJson);
    }

    protected function buildProjectContext(array $payload): string
    {
        $projectId = $payload['project_id'] ?? null;
        if ($projectId) {
            $project = \App\Models\Project::find($projectId);
            if ($project) {
                return "Konteks Project Aktif: {$project->name}. " .
                       "Gunakan konteks ini hanya untuk menganalisis relevansi, sentimen, dan risiko terhadap project '{$project->name}'. " .
                       "Namun, estimasi pembaca (project_estimated_readers) TETAP merupakan jumlah pembaca artikel secara umum, BUKAN berdasarkan relevansi project.";
            }
        }
        return 'Analisis artikel secara umum dan objektif tanpa terikat pada konteks spesifik proyek.';
    }

    protected function buildMediaContext(array $payload): string
    {
        if (($payload['type'] ?? null) !== 'social') {
            return 'Tidak ada konteks media sosial tambahan.';
        }

        $mediaType = trim((string) ($payload['media_type'] ?? 'text'));
        $mediaUrl = trim((string) ($payload['media_url'] ?? ''));
        $thumbnailUrl = trim((string) ($payload['thumbnail_url'] ?? ''));
        $author = trim((string) ($payload['author_name'] ?? ''));

        return trim(implode("\n", array_filter([
            "Jenis media terdeteksi: {$mediaType}.",
            $mediaUrl !== '' ? "Link media: {$mediaUrl}." : null,
            $thumbnailUrl !== '' ? "Thumbnail: {$thumbnailUrl}." : null,
            $author !== '' ? "Penulis/akun: {$author}." : null,
            'Jika ada link video/foto, gunakan itu untuk menilai apakah kontennya memang visual, bukan hanya teks caption.',
        ])));
    }

    protected function buildEngagementContext(array $payload): string
    {
        if (($payload['type'] ?? null) !== 'social') {
            return 'Tidak ada engagement media sosial.';
        }

        $metrics = [];
        foreach ([
            'like_count' => 'likes',
            'comment_count' => 'comments',
            'share_count' => 'shares',
            'view_count' => 'views',
            'follower_count' => 'followers',
        ] as $key => $label) {
            $value = $payload[$key] ?? null;
            if (is_numeric($value) && (int) $value > 0) {
                $metrics[] = "{$label}: " . (int) $value;
            }
        }

        return $metrics ? 'Engagement tersedia: ' . implode(', ', $metrics) . '.' : 'Engagement tidak tersedia.';
    }

    protected function buildReachContext(array $payload): string
    {
        $sourceName = (string) ($payload['source_name'] ?? '');
        $domain = parse_url((string) ($payload['url'] ?? ''), PHP_URL_HOST) ?: '';
        $domain = preg_replace('/^www\./', '', strtolower($domain));
        $source = null;

        if ($domain !== '') {
            $source = NewsSource::query()
                ->where('domain', $domain)
                ->orWhere('domain', 'like', '%' . $domain . '%')
                ->first();
        }

        $articleId = $payload['id'] ?? null;
        $outletCount = 0;
        if ($articleId) {
            $article = Article::find($articleId);
            if ($article) {
                $title = mb_strtolower((string) $article->title);
                $terms = array_values(array_filter(explode(' ', preg_replace('/[^\pL\pN\s]+/u', ' ', $title)), fn ($term) => mb_strlen($term) >= 4));
                $query = DB::table('articles')
                    ->where('articles.id', '!=', $articleId);

                foreach (array_slice($terms, 0, 3) as $term) {
                    $query->where('articles.title', 'ilike', '%' . $term . '%');
                }

                $outletCount = (int) $query->distinct('articles.source_name')->count('articles.source_name');
            }
        }

        return json_encode([
            'source_name' => $sourceName,
            'media_scope' => $source?->media_scope,
            'local_reach_weight' => $source?->local_reach_weight,
            'dewan_pers_status' => $source?->dewan_pers_status,
            'other_outlet_count' => $outletCount,
            'views_available' => null,
            'engagement_available' => null,
        ], JSON_UNESCAPED_UNICODE);
    }
}
