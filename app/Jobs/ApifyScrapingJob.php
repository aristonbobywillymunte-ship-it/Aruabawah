<?php

namespace App\Jobs;

use App\Models\ApifyActor;
use App\Models\ApifySetting;
use App\Models\Project;
use App\Models\SocialMediaItem;
use App\Services\AiAnalysisDispatchStateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\CarbonInterface;

class ApifyScrapingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1000;

    public array $params;

    public function __construct(array $params)
    {
        $this->params = $params;
        $this->queue = 'apify';
    }

    public static function dispatchSafely(array $params, int $staleAfterMinutes = 30): bool
    {
        $platform = (string) ($params['platform'] ?? '');
        $keyword = (string) ($params['keyword'] ?? '');
        $forceDispatch = (bool) ($params['force_dispatch'] ?? false);
        $keywords = array_values(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            (array) ($params['keywords'] ?? [])
        )));
        if ($keywords === [] && $keyword !== '') {
            $keywords = [$keyword];
        }
        $projectId = (int) ($params['project_id'] ?? 0);
        $actorId = (int) ($params['actor_id'] ?? 0);

        $normalizedKeyword = strtolower(trim(implode('|', $keywords ?: [$keyword])));
        $now = now();
        
        // Dynamic window based on the actor's actual interval_minutes configuration
        $intervalMinutes = 30; // fallback default
        if ($actorId) {
            $actor = \App\Models\ApifyActor::find($actorId);
            if ($actor && $actor->interval_minutes) {
                $intervalMinutes = max(1, (int) $actor->interval_minutes);
            }
        }

        // Calculate the start of the current interval window to allow execution once per interval period
        $currentIntervalBlock = (int) floor($now->timestamp / ($intervalMinutes * 60));
        $windowStart = $currentIntervalBlock * $intervalMinutes * 60;
        $windowEnd = ($currentIntervalBlock + 1) * $intervalMinutes * 60;

        $isSocialPlatform = in_array($platform, ['Facebook', 'Instagram', 'TikTok'], true);
        $dispatchKeyParts = [
            $projectId,
            $actorId,
            $platform,
            $windowStart,
            $windowEnd,
        ];

        // Social actors should only be dispatched once per project per interval window.
        // Keywords are still sent to Apify in the payload, but they no longer create
        // separate queue/state entries for the same project and interval block.
        if (! $isSocialPlatform) {
            $dispatchKeyParts[] = $normalizedKeyword;
        }

        $dispatchKey = hash('sha256', implode('|', $dispatchKeyParts));

        try {
        if ($isSocialPlatform && $actorId && ! $forceDispatch) {
                $activeThreshold = $now->copy()->subMinutes($staleAfterMinutes);
                $activeState = \App\Models\ApifyDispatchState::query()
                    ->where('project_id', $projectId)
                    ->where('actor_id', $actorId)
                    ->whereIn('status', ['queued', 'processing', 'retry_wait'])
                    ->where(function ($query) use ($now, $activeThreshold) {
                        $query
                            ->where(function ($queued) use ($activeThreshold) {
                                $queued->where('status', 'queued')
                                    ->where('queued_at', '>=', $activeThreshold);
                            })
                            ->orWhere(function ($processing) use ($activeThreshold) {
                                $processing->where('status', 'processing')
                                    ->where('started_at', '>=', $activeThreshold);
                            })
                            ->orWhere(function ($retryWait) use ($now) {
                                $retryWait->where('status', 'retry_wait')
                                    ->where(function ($retryAt) use ($now) {
                                        $retryAt->whereNull('next_retry_at')
                                            ->orWhere('next_retry_at', '>=', $now);
                                    });
                            });
                    })
                    ->first();

                if ($activeState) {
                    Log::info('[Apify] Skip dispatch: social actor still has active state.', [
                        'actor_id' => $actorId,
                        'platform' => $platform,
                        'active_state_id' => $activeState->id,
                        'active_status' => $activeState->status,
                    ]);

                    return false;
                }
            }

            // Coba ambil state, atau buat kalau tidak ada
            $state = \App\Models\ApifyDispatchState::firstOrCreate(
                ['dispatch_key' => $dispatchKey],
                [
                    'project_id' => $projectId,
                    'actor_id' => $actorId,
                    'platform' => $platform,
                    'keyword' => $keyword,
                    'normalized_keyword' => $normalizedKeyword,
                    'window_start' => $windowStart,
                    'window_end' => $windowEnd,
                    'status' => 'queued',
                    'queued_at' => $now,
                ]
            );

            // Jika state ternyata bukan baru dibuat, cek apakah boleh di-dispatch lagi
            if (!$state->wasRecentlyCreated && ! $forceDispatch) {
                $retryWaitOverdue = $state->status === 'retry_wait'
                    && $state->next_retry_at !== null
                    && $state->next_retry_at->lte($now);

                if (in_array($state->status, ['queued', 'processing', 'success'], true)
                    || ($state->status === 'retry_wait' && ! $retryWaitOverdue)) {
                    // Kalau status belum boleh didispatch lagi, kembalikan false
                    Log::info('[Apify] Skip duplicate dispatch state', ['key' => $dispatchKey, 'status' => $state->status]);
                    return false;
                }
                
                // Jika failed atau cancelled, kita bisa mencoba lagi (tergantung kebutuhan, di sini kita set queued ulang)
                $state->update([
                    'status' => 'queued',
                    'queued_at' => $now,
                    'next_retry_at' => null,
                    'attempts' => $state->attempts + 1,
                    'last_error_message' => null
                ]);
            }

            // Tambahkan ID state ke parameter
            $params['dispatch_state_id'] = $state->id;
            if ($forceDispatch) {
                $params['force_dispatch'] = true;
            }

            self::dispatch($params);
            return true;
            
        } catch (\Exception $e) {
            Log::error('[Apify] Failed to create dispatch state: ' . $e->getMessage());
            return false;
        }
    }

    // hasPendingDuplicate and cleanupStaleJobs replaced by State tracking

    public function handle(): void
    {
        $socialLog = Log::channel('social_media');
        $platform  = $this->params['platform'] ?? 'X';
        $keyword   = $this->params['keyword']  ?? null;
        $keywords  = array_values(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            (array) ($this->params['keywords'] ?? [])
        )));
        if ($keywords === [] && $keyword !== null && trim((string) $keyword) !== '') {
            $keywords = [trim((string) $keyword)];
        }
        $projectId = $this->params['project_id'] ?? null;
        $limit     = max(1, (int) ($this->params['limit'] ?? 1));
        $suppressTelegram = (bool) ($this->params['no_telegram'] ?? false);

        $dispatchStateId = $this->params['dispatch_state_id'] ?? null;
        $state = null;
        
        if ($dispatchStateId) {
            $state = \App\Models\ApifyDispatchState::find($dispatchStateId);
            if ($state) {
                $state->update(['status' => 'processing', 'started_at' => now()]);
            }
        }

        Log::info("[Apify] Starting scrape for platform={$platform} keyword={$keyword} project={$projectId}", [
            'keywords' => $keywords,
        ]);

        // Load API token
        $setting = ApifySetting::first();
        if (!$setting || ! $setting->isReadyForScraping()) {
            Log::warning('[Apify] Scraping skipped because Apify settings are not ready.', [
                'connection_status' => $setting?->connection_status,
            ]);
            if ($state) {
                $state->update(['status' => 'failed', 'last_error_message' => 'Scraping skipped because Apify settings are not ready.']);
            }
            return;
        }
        $token = $setting->api_token;

        // Load matching actor
        $actorId = $this->params['actor_id'] ?? null;
        if ($actorId) {
            $actor = ApifyActor::find($actorId);
        } else {
            $actor = ApifyActor::where('platform', $platform)
                ->where('status', 'active')
                ->orderBy('priority')
                ->first();
        }

        if (!$actor) {
            Log::warning("[Apify] No active actor found for platform: {$platform}");
            return;
        }

        if (in_array($platform, ['TikTok', 'Facebook', 'Instagram'], true) && $projectId) {
            try {
                $project = \App\Models\Project::find($projectId);
                if ($project) {
                    $projectKeywords = array_values(array_unique($project->scrapeKeywords()));
                    if ($projectKeywords !== []) {
                        $keywords = array_values(array_unique(array_filter(array_map('trim', array_merge($projectKeywords, $keywords)))));
                        if ($keywords === [] && $keyword !== null && trim((string) $keyword) !== '') {
                            $keywords = [trim((string) $keyword)];
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[Apify] Failed to merge social project keywords.', [
                    'project_id' => $projectId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $project = $projectId ? \App\Models\Project::find($projectId) : null;
        $projectName = $project ? $project->name : 'N/A';
        $contextStr = "platform={$platform} actor={$actor->actor_slug} project_name={$projectName} (ID: {$projectId}) keyword=" . implode(',', $keywords ?: [$keyword]);

        $jobLimit = isset($this->params['limit']) ? (int) $this->params['limit'] : null;
        $configuredLimit = (int) (\App\Models\ScrapingSetting::first()?->limit_per_run ?? 3);
        $limit = $jobLimit ?: $configuredLimit ?: (int) ($actor->default_limit ?? 50);
        $limit = max(1, $limit);
        $input = $actor->buildInputPayload($keyword, $limit, null, null, $keywords);

        Log::info("[Apify] Calling actor. {$contextStr} | input: " . json_encode($input));
        $socialLog->info('[Social] Actor payload prepared.', $this->buildPayloadAuditContext(
            actor: $actor,
            projectId: $projectId,
            projectName: $projectName,
            platform: $platform,
            keywords: $keywords,
            keyword: $keyword,
            limit: $limit,
            input: $input,
        ));

        // Apify API requires actor slug with ~ instead of / in the URL
        $slugForUrl = str_replace('/', '~', $actor->actor_slug);

        // Run the actor — send input directly in the POST body (Apify v2 API format)
        $runUrl = "https://api.apify.com/v2/acts/{$slugForUrl}/runs";
        $apifyTimeout = max(1, (int) ($actor->timeout_seconds ?: (300 + ($limit * 6))));
        $runQuery = [
            'memory' => max(128, (int) ($actor->memory_limit ?? 1024)),
            'build' => $actor->build ?: 'latest',
            'timeout' => $apifyTimeout,
        ];

        if ((bool) ($actor->no_timeout ?? false)) {
            unset($runQuery['timeout']);
        }

        $maximumCostPerRun = (float) ($actor->maximum_cost_per_run_usd ?? 0);
        if ($maximumCostPerRun > 0) {
            $runQuery['maxTotalChargeUsd'] = round($maximumCostPerRun, 4);
        }

        $runResponse = Http::withToken($token)
            ->timeout(60)
            ->post($runUrl . '?' . http_build_query($runQuery), $input);

        if (!$runResponse->successful()) {
            $msg = "Apify run failed: HTTP {$runResponse->status()}: {$runResponse->body()}";
            Log::error("[Apify] {$msg} | {$contextStr}");
            $actor->update(['last_run_at' => now(), 'last_run_status' => 'failed', 'last_run_message' => substr($msg, 0, 500)]);
            $cooldownMinutes = $this->apifyCooldownMinutes($msg, (int) ($actor->interval_minutes ?? 20));
            $retryAt = now()->addMinutes($cooldownMinutes);
            Cache::put("apify_actor_retry_at:{$actor->id}", $retryAt->toDateTimeString(), $retryAt);
            if ($state) {
                $state->update(['status' => 'failed', 'last_error_message' => substr($msg, 0, 500)]);
            }
            return;
        }

        $runId     = $runResponse->json('data.id');
        $datasetId = $runResponse->json('data.defaultDatasetId');
        Log::info("[Apify] Run started: runId={$runId} datasetId={$datasetId} | {$contextStr}");
        $socialLog->info('[Social] Actor run started.', array_merge(
            $this->buildPayloadAuditContext(
                actor: $actor,
                projectId: $projectId,
                projectName: $projectName,
                platform: $platform,
                keywords: $keywords,
                keyword: $keyword,
                limit: $limit,
                input: $input,
            ),
            [
                'run_id' => $runId,
                'dataset_id' => $datasetId,
                'apify_timeout_seconds' => $apifyTimeout,
                'run_query' => $runQuery,
            ],
        ));
        
        if ($state) {
            $state->update(['run_id' => $runId]);
        }

        // Poll for run completion (max 15 minutes). If Apify is still running,
        // abort safely, then process any dataset already collected.
        $status = 'RUNNING';
        $pollTimeout = $this->apifyPollTimeoutSeconds();
        $pollSleepSeconds = $this->apifyPollSleepSeconds($platform);
        $polled = 0;
        $limitReached = false;
        $costLimitReached = false;
        $pollTimeoutReached = false;
        $pollTimeoutNote = null;
        $costLimitNote = null;
        $statusMessage = null;
        $runData = [];
        $shouldAbortOnHardLimit = ! in_array($platform, ['Facebook', 'Instagram', 'TikTok'], true);
        while (in_array($status, ['RUNNING', 'READY', 'ABORTING']) && $polled < $pollTimeout) {
            sleep($pollSleepSeconds);
            $polled += $pollSleepSeconds;
            $statusResp = Http::withToken($token)
                ->get("https://api.apify.com/v2/actor-runs/{$runId}");
            $runData = $statusResp->json('data') ?? [];
            $status = $runData['status'] ?? 'FAILED';
            $statusMessage = $runData['statusMessage'] ?? null;
            Log::info("[Apify] Run status: {$status} ({$polled}s elapsed) | {$contextStr}", [
                'status_message' => $statusMessage,
            ]);

            if (! $limitReached && $limit > 0 && $this->datasetItemCountAtLeast($token, $datasetId, $limit)) {
                $limitReached = true;
                if ($shouldAbortOnHardLimit) {
                    Log::info("[Apify] Hard limit reached; stopping run at {$limit} item(s). | {$contextStr}");

                    try {
                        Http::withToken($token)
                            ->timeout(20)
                            ->post("https://api.apify.com/v2/actor-runs/{$runId}/abort");
                    } catch (\Throwable $e) {
                        Log::warning('[Apify] Failed to request run abort after reaching hard limit.', [
                            'run_id' => $runId,
                            'dataset_id' => $datasetId,
                            'limit' => $limit,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    Log::info("[Apify] Hard limit reached; waiting actor to finish naturally at {$limit} item(s). | {$contextStr}");
                }
            }
        }

        if (in_array($status, ['RUNNING', 'READY', 'ABORTING'], true) && $polled >= $pollTimeout) {
            $pollTimeoutReached = true;
            $pollTimeoutNote = 'Run Apify tidak memberi hasil akhir dalam 15 menit. Sistem mengirim perintah abort aman, lalu memproses data yang sudah terkumpul jika ada.';
            Log::warning("[Apify] Poll timeout reached; aborting run safely. | {$contextStr}", [
                'run_id' => $runId,
                'dataset_id' => $datasetId,
                'polled_seconds' => $polled,
                'note' => $pollTimeoutNote,
            ]);

            $this->abortApifyRun($token, $runId, $datasetId, '15 minute poll timeout');
        }

        if ($status !== 'SUCCEEDED') {
            try {
                $finalStatusResp = Http::withToken($token)
                    ->timeout(20)
                    ->get("https://api.apify.com/v2/actor-runs/{$runId}");
                $finalRunData = $finalStatusResp->json('data') ?? [];
                if (is_array($finalRunData) && $finalRunData !== []) {
                    $runData = array_merge($runData, $finalRunData);
                    $status = $runData['status'] ?? $status;
                    $statusMessage = $runData['statusMessage'] ?? $statusMessage;
                }
            } catch (\Throwable $e) {
                Log::warning('[Apify] Failed to fetch final run status message.', [
                    'run_id' => $runId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($pollTimeoutReached && $this->datasetItemCountAtLeast($token, $datasetId, 1)) {
            Log::warning("[Apify] Timeout abort has partial dataset; continuing with fetched items. | {$contextStr}", [
                'run_id' => $runId,
                'dataset_id' => $datasetId,
                'note' => $pollTimeoutNote,
            ]);
        }

        if ($this->isCostLimitAbort($status, $statusMessage, $actor, $runData) && $this->datasetItemCountAtLeast($token, $datasetId, 1)) {
            $costLimitReached = true;
            $costLimitNote = $this->costLimitNote($statusMessage, $actor);
            Log::warning("[Apify] Cost limit reached; continuing with partial dataset. | {$contextStr}", [
                'run_id' => $runId,
                'dataset_id' => $datasetId,
                'status_message' => $statusMessage,
                'usage_total_usd' => data_get($runData, 'usageTotalUsd'),
                'note' => $costLimitNote,
            ]);
        }

        if ($limitReached && $status === 'SUCCEEDED') {
            $status = 'SUCCEEDED';
        }

        if ($pollTimeoutReached && ! $this->datasetItemCountAtLeast($token, $datasetId, 1)) {
            $msg = $pollTimeoutNote . ' Dataset masih kosong, sehingga run ditunda untuk dicoba ulang nanti.';
            Log::warning("[Apify] {$msg} | {$contextStr}", [
                'run_id' => $runId,
                'dataset_id' => $datasetId,
                'status' => $status,
                'status_message' => $statusMessage,
            ]);

            $cooldownMinutes = $this->apifyCooldownMinutes($msg, (int) ($actor->interval_minutes ?? 20));
            $retryAt = now()->addMinutes($cooldownMinutes);
            Cache::put("apify_actor_retry_at:{$actor->id}", $retryAt->toDateTimeString(), $retryAt);
            $actor->update([
                'last_run_at' => now(),
                'last_run_status' => 'retry_wait',
                'last_run_message' => $msg . ' Coba lagi setelah ' . $retryAt->format('H:i') . '.',
            ]);
            if ($state) {
                $state->update([
                    'status' => 'retry_wait',
                    'next_retry_at' => $retryAt,
                    'last_error_message' => $msg,
                ]);
            }

            return;
        }

        if ($status !== 'SUCCEEDED' && ! $limitReached && ! $costLimitReached && ! $pollTimeoutReached) {
            $msg = "Actor run did not succeed. Final status: {$status}";
            if (filled($statusMessage)) {
                $msg .= ". {$statusMessage}";
            }
            Log::error("[Apify] {$msg} | {$contextStr}", [
                'run_id' => $runId,
                'dataset_id' => $datasetId,
                'status_message' => $statusMessage,
            ]);
            $actor->update(['last_run_at' => now(), 'last_run_status' => 'failed', 'last_run_message' => $msg]);
            $cooldownMinutes = $this->apifyCooldownMinutes($msg, (int) ($actor->interval_minutes ?? 20));
            $retryAt = now()->addMinutes($cooldownMinutes);
            Cache::put("apify_actor_retry_at:{$actor->id}", $retryAt->toDateTimeString(), $retryAt);
            if ($state) {
                $state->update(['status' => 'failed', 'last_error_message' => $msg]);
            }
            return;
        }

        if ($limitReached) {
            $doneMessage = $shouldAbortOnHardLimit
                ? "[Apify] Run marked done after hitting hard limit; continuing with fetched items only. | {$contextStr}"
                : "[Apify] Actor finished with dataset above local limit; continuing with capped items only. | {$contextStr}";
            Log::info($doneMessage);
        }
        if ($costLimitReached) {
            Log::info("[Apify] Run marked partial after hitting cost limit; continuing with fetched items only. | {$contextStr}", [
                'note' => $costLimitNote,
            ]);
        }
        if ($pollTimeoutReached) {
            Log::info("[Apify] Run marked partial after 15 minute timeout abort; continuing with fetched items only. | {$contextStr}", [
                'note' => $pollTimeoutNote,
            ]);
        }

        // Fetch dataset items
        $datasetResp = Http::withToken($token)
            ->get("https://api.apify.com/v2/datasets/{$datasetId}/items", [
                'format' => 'json',
                'limit'  => $limit,
            ]);

        if (!$datasetResp->successful()) {
            Log::error("[Apify] Failed to fetch dataset | {$contextStr} | error: " . $datasetResp->body());
            $actor->update(['last_run_at' => now(), 'last_run_status' => 'failed', 'last_run_message' => 'Dataset fetch failed']);
            $cooldownMinutes = $this->apifyCooldownMinutes('Dataset fetch failed', (int) ($actor->interval_minutes ?? 20));
            $retryAt = now()->addMinutes($cooldownMinutes);
            Cache::put("apify_actor_retry_at:{$actor->id}", $retryAt->toDateTimeString(), $retryAt);
            if ($state) {
                $state->update(['status' => 'failed', 'last_error_message' => 'Dataset fetch failed']);
            }
            return;
        }

        $items = $datasetResp->json() ?? [];
        if (in_array($platform, ['Facebook', 'Instagram', 'TikTok'], true) && count($items) > $limit) {
            $items = array_slice($items, 0, $limit);
        }
        $saved = 0;

        foreach ($items as $item) {
            // Normalise fields across platforms
            $postUrl    = $item['webVideoUrl'] ?? $item['url'] ?? $item['facebookUrl'] ?? $item['topLevelUrl'] ?? $item['post_url'] ?? $item['postUrl'] ?? $item['link'] ?? null;
            $content    = $item['message'] ?? $item['text'] ?? $item['caption'] ?? $item['description'] ?? $item['title'] ?? '';
            $authorFallback = $platform === 'TikTok' ? 'TikTok' : 'Unknown Author';
            $author     = $item['author']['name']
                ?? $item['authorMeta']['nickName']
                ?? $item['authorMeta']['name']
                ?? $item['pageName']
                ?? $item['associated_group']['name']
                ?? $item['authorName']
                ?? $item['username']
                ?? $item['ownerUsername']
                ?? $item['channelName']
                ?? $authorFallback;
            $authorUrl  = $item['author']['url']
                ?? $item['facebookUrl']
                ?? $item['associated_group']['url']
                ?? $item['profileUrl']
                ?? $item['ownerProfileUrl']
                ?? null;
            $postedAtRaw = $item['uploadedAt'] ?? $item['createTime'] ?? $item['createTimeISO'] ?? $item['timestamp'] ?? $item['time'] ?? $item['date'] ?? $item['publishedAt'] ?? $item['create_time'] ?? null;
            $postedAtCarbon = now();
            if ($postedAtRaw) {
                try {
                    if (is_numeric($postedAtRaw)) {
                        if (strlen((string)$postedAtRaw) >= 13) {
                            $postedAtCarbon = \Carbon\Carbon::createFromTimestampMs($postedAtRaw);
                        } else {
                            $postedAtCarbon = \Carbon\Carbon::createFromTimestamp($postedAtRaw);
                        }
                    } else {
                        $postedAtCarbon = \Carbon\Carbon::parse($postedAtRaw);
                    }
                } catch (\Exception $e) {}
            }
            $likes      = $item['diggCount'] ?? $item['reactions_count'] ?? $item['like_count'] ?? $item['likesCount'] ?? $item['likeCount'] ?? $item['likes'] ?? 0;
            $comments   = $item['commentCount'] ?? $item['comment_count'] ?? $item['comments_count'] ?? $item['commentsCount'] ?? $item['comments'] ?? 0;
            $shares     = $item['shareCount'] ?? $item['share_count'] ?? $item['reshare_count'] ?? $item['sharesCount'] ?? $item['shares'] ?? 0;
            $views      = $item['playCount'] ?? $item['view_count'] ?? $item['viewsCount'] ?? $item['viewCount'] ?? $item['views'] ?? 0;
            $followers  = $item['authorMeta']['fans'] ?? $item['follower_count'] ?? $item['followersCount'] ?? $item['followerCount'] ?? 0;

            if ($platform === 'Instagram') {
                if (empty($postUrl)) {
                    Log::warning("[Apify] Skipped IG item: missing url/post_url");
                    continue;
                }

                if (empty($content)) {
                    Log::warning("[Apify] Skipped IG item: missing caption/content");
                    continue;
                }

                $itemType = strtolower($item['type'] ?? $item['productType'] ?? $item['product_type'] ?? 'post');
                $allowedTypes = ['post', 'reel', 'clips', 'image', 'video', 'sidecar', 'feed', 'carousel'];
                $isAllowedType = false;
                foreach ($allowedTypes as $allowed) {
                    if (str_contains($itemType, $allowed)) {
                        $isAllowedType = true;
                        break;
                    }
                }
                if (!$isAllowedType) {
                    Log::info("[Apify] Skipped IG item: type '{$itemType}' is not post/reel");
                    continue;
                }

                $postedAtCarbon = null;
                if (!empty($postedAtRaw)) {
                    try {
                        if (is_numeric($postedAtRaw)) {
                            if (strlen((string)$postedAtRaw) >= 13) {
                                $postedAtCarbon = \Carbon\Carbon::createFromTimestampMs($postedAtRaw);
                            } else {
                                $postedAtCarbon = \Carbon\Carbon::createFromTimestamp($postedAtRaw);
                            }
                        } else {
                            $postedAtCarbon = \Carbon\Carbon::parse($postedAtRaw);
                        }
                    } catch (\Exception $e) {}
                }

                if (!$postedAtCarbon) {
                    Log::warning("[Apify] IG item timestamp unknown; saving with null posted_at.", [
                        'post_url' => $postUrl,
                        'keyword' => $keyword,
                    ]);
                }

                $isInstagramHashtagPosts = ($actor->actor_slug === 'apify/instagram-hashtag-scraper' && (($input['resultsType'] ?? '') === 'posts'));

                if (
                    !$isInstagramHashtagPosts
                    && $postedAtCarbon
                    && $postedAtCarbon->lessThan(now()->subDays(7)->startOfDay())
                ) {
                    Log::info("[Apify] Skipped IG item: older than 7 days ({$postedAtCarbon->toIso8601String()})");
                    continue;
                }

                $item['_metadata'] = [
                    'source_mode' => $isInstagramHashtagPosts ? 'instagram_hashtag_posts' : 'posts',
                    'recency_policy' => $isInstagramHashtagPosts ? 'ignored' : 'enforced',
                    'is_recent_7d' => $postedAtCarbon ? $postedAtCarbon->greaterThanOrEqualTo(now()->subDays(7)->startOfDay()) : false,
                    'keyword' => $keyword,
                ];
            }

            $keywordHaystack = $this->keywordMatchHaystack(
                $item,
                $content,
                $author,
                $authorUrl,
                $postUrl,
                $platform,
            );

            if (
                in_array($platform, ['Facebook', 'Instagram', 'TikTok'], true)
                && ! $this->matchesAnyKeywordInContent($keywords, $keywordHaystack)
            ) {
                Log::info('[Apify] Skipped social item: keyword proyek tidak cocok.', [
                    'platform' => $platform,
                    'project_id' => $projectId,
                    'keywords' => $keywords,
                    'post_url' => $postUrl,
                    'author' => $author,
                    'content_excerpt' => Str::limit((string) $content, 120),
                ]);
                continue;
            }
            if (
                in_array($platform, ['Facebook', 'Instagram', 'TikTok'], true)
                && $this->isInvalidSocialContent($content)
            ) {
                Log::info('[Apify] Skipped social item: konten medsos tidak layak simpan.', [
                    'platform' => $platform,
                    'project_id' => $projectId,
                    'post_url' => $postUrl,
                    'author' => $author,
                    'content_excerpt' => Str::limit((string) $content, 120),
                ]);
                continue;
            }
            if ($this->isPlaceholderOrNoiseContent($content)) continue;

            $record = SocialMediaItem::updateOrCreate(
                ['post_url' => $postUrl ?? ('apify-' . md5($content . $platform))],
                [
                    'project_id'    => $projectId ?: null,
                    'platform'       => $platform,
                    'author_name'    => $author,
                    'author_url'     => $authorUrl,
                    'content'        => $content,
                    'posted_at'      => $postedAtCarbon,
                    'like_count'     => (int) $likes,
                    'comment_count'  => (int) $comments,
                    'share_count'    => (int) $shares,
                    'view_count'     => (int) $views,
                    'follower_count' => (int) $followers,
                    'raw_json'       => json_encode($item),
                ]
            );

            $socialSourceName = $platform === 'TikTok' ? 'Tiktok' : $platform;
            $articleTitle = $platform === 'TikTok' && trim((string) $author) === 'TikTok'
                ? 'Post dari TikTok'
                : "Post dari {$platform} oleh {$author}";

            // Mirror as Article for dashboard
            $articleUrl = $postUrl ?? ('apify-' . md5($content . $platform));
            $article = \App\Models\Article::where('canonical_url', $articleUrl)
                ->orWhere('url', $articleUrl)
                ->first();

            if ($article) {
                $article->update([
                    'title'        => $articleTitle,
                    'content'      => $content,
                    'source_name'  => $socialSourceName,
                    'published_at' => $postedAtCarbon,
                    'sentiment'    => 'neutral',
                    'category'     => 'social',
                ]);
            } else {
                $article = \App\Models\Article::create([
                    'url'           => $articleUrl,
                    'canonical_url' => $articleUrl,
                    'title'         => $articleTitle,
                    'content'       => $content,
                    'source_name'   => $socialSourceName,
                    'published_at'  => $postedAtCarbon,
                    'sentiment'     => 'neutral',
                    'category'      => 'social',
                ]);
            }

            // Cross-link to ALL active projects that match the keywords (Bank Berita Concept)
            $matchingService = app(\App\Services\ContentMatchingService::class);
            $matchingService->crossLinkToActiveProjects($article, $projectId);
            if (isset($record) && $record) {
                $matchingService->crossLinkToActiveProjects($record, $projectId);
            }

            $saved++;

            if (empty($article->id) || empty($projectId)) {
                Log::warning('[Apify] Skipped AI dispatch: missing article_id or project_id.', [
                    'article_id' => $article->id ?? null,
                    'project_id' => $projectId,
                ]);
                continue;
            }

            $dispatchStateService = app(AiAnalysisDispatchStateService::class);
            $promptTemplateId = $dispatchStateService->resolvePromptTemplateId('social');
            $providerContextHash = $dispatchStateService->resolveProviderContextHash();
            $decision = $dispatchStateService->reserveQueuedStateAndDispatch([
                'type' => 'social',
                'id' => $article->id,
                'item_id' => $record->id,
                'project_id' => $projectId,
                'title' => "Post dari {$platform} oleh {$author}",
                'content' => $content,
                'url' => $postUrl ?? '',
                'source_name' => $platform,
                'media_type' => $this->detectSocialMediaType($item, $platform),
                'media_url' => $this->extractSocialMediaUrl($item),
                'thumbnail_url' => $this->extractSocialThumbnailUrl($item),
                'author_name' => $author,
                'author_url' => $authorUrl,
                'like_count' => (int) $likes,
                'comment_count' => (int) $comments,
                'share_count' => (int) $shares,
                'view_count' => (int) $views,
                'follower_count' => (int) $followers,
                'raw_social_item' => $item,
                    'published_at' => $postedAtCarbon?->toIso8601String(),
                    'no_telegram' => $suppressTelegram,
                ], $promptTemplateId, $providerContextHash);

            if (! ($decision['should_dispatch'] ?? false)) {
                Log::info('[Apify] AI dispatch skipped due to persistent dispatch state.', [
                    'article_id' => $article->id,
                    'status' => $decision['status'] ?? 'unknown',
                    'reason' => $decision['reason'] ?? 'unknown',
                ]);
                continue;
            }

            Cache::put("ai_analysis_lock_social_{$record->id}", true, now()->addMinutes(15));
        }

        $msg = "Scraped {$saved} items from {$platform} for keyword: {$keyword}";
        Log::info("[Apify] {$msg}");

        $lastRunMessage = $msg;
        if ($costLimitReached) {
            $lastRunMessage = $msg . ' (' . $costLimitNote . ')';
        } elseif ($pollTimeoutReached) {
            $lastRunMessage = $msg . ' (' . $pollTimeoutNote . ')';
        } elseif ($limitReached) {
            $lastRunMessage = $msg . " (done at {$limit} items)";
        }

        $actor->update([
            'last_run_at'      => now(),
            'last_run_status'  => 'success',
            'last_run_message' => $lastRunMessage,
        ]);
        Cache::forget("apify_actor_retry_at:{$actor->id}");

        if ($state) {
            $state->update([
                'status' => 'success',
                'completed_at' => now(),
                'last_error_message' => $costLimitReached ? $costLimitNote : ($pollTimeoutReached ? $pollTimeoutNote : null),
            ]);
        }
    }

    protected function detectSocialMediaType(array $item, string $platform): string
    {
        $haystack = mb_strtolower(implode(' ', array_map(static function ($value) {
            if (is_scalar($value) || $value === null) {
                return (string) $value;
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }, [
            $item['type'] ?? '',
            $item['post_type'] ?? '',
            $item['media_type'] ?? '',
            $item['content_type'] ?? '',
            $item['video_url'] ?? '',
            $item['videoUrl'] ?? '',
            $item['image_url'] ?? '',
            $item['imageUrl'] ?? '',
            $item['thumbnail_url'] ?? '',
            $item['thumbnailUrl'] ?? '',
            $item['attachments'] ?? '',
            $item['media'] ?? '',
            $item['images'] ?? '',
            $item['videos'] ?? '',
            $item['carousel'] ?? '',
        ])));

        if (preg_match('/\b(video|reel|clip|short|live)\b/', $haystack)) {
            return 'video';
        }

        if (preg_match('/\b(photo|image|img|picture|gambar|foto)\b/', $haystack)) {
            return 'image';
        }

        if (preg_match('/\b(carousel|sidecar|gallery|album)\b/', $haystack)) {
            return 'carousel';
        }

        if ($platform === 'TikTok') {
            return 'video';
        }

        return 'text';
    }

    protected function extractSocialMediaUrl(array $item): string
    {
        foreach (['video_url', 'videoUrl', 'media_url', 'mediaUrl', 'image_url', 'imageUrl', 'url'] as $key) {
            $value = trim((string) ($item[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function extractSocialThumbnailUrl(array $item): string
    {
        foreach (['thumbnail_url', 'thumbnailUrl', 'preview_url', 'previewUrl', 'image_url', 'imageUrl'] as $key) {
            $value = trim((string) ($item[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function abortApifyRun(string $token, string $runId, ?string $datasetId = null, string $reason = 'safe abort'): void
    {
        try {
            Http::withToken($token)
                ->timeout(20)
                ->post("https://api.apify.com/v2/actor-runs/{$runId}/abort");
        } catch (\Throwable $e) {
            Log::warning('[Apify] Failed to request run abort.', [
                'run_id' => $runId,
                'dataset_id' => $datasetId,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function buildPayloadAuditContext(
        ApifyActor $actor,
        mixed $projectId,
        string $projectName,
        string $platform,
        array $keywords,
        mixed $keyword,
        int $limit,
        array $input,
    ): array {
        [$payloadLimitField, $payloadLimitValue] = $this->resolvePayloadLimitInfo($platform, $input);

        return [
            'platform' => $platform,
            'project_id' => $projectId ? (int) $projectId : null,
            'project_name' => $projectName !== 'N/A' ? $projectName : null,
            'actor_id' => (int) $actor->id,
            'actor_name' => $actor->actor_name,
            'actor_slug' => $actor->actor_slug,
            'keyword' => is_string($keyword) ? $keyword : null,
            'keywords' => array_values($keywords),
            'keyword_count' => count($keywords),
            'limit_total_requested' => $limit,
            'payload_limit_field' => $payloadLimitField,
            'payload_limit_value' => $payloadLimitValue,
            'interval_minutes' => (int) ($actor->interval_minutes ?? 0),
            'memory_limit_mb' => (int) ($actor->memory_limit ?? 0),
            'maximum_cost_per_run_usd' => (float) ($actor->maximum_cost_per_run_usd ?? 0),
            'range_mode' => $actor->range_mode,
            'priority' => (int) ($actor->priority ?? 0),
            'last_run_at' => $actor->last_run_at instanceof CarbonInterface
                ? $actor->last_run_at->toDateTimeString()
                : $actor->last_run_at,
            'payload' => $input,
        ];
    }

    protected function resolvePayloadLimitInfo(string $platform, array $input): array
    {
        return match ($platform) {
            'Facebook' => ['maxPosts', isset($input['maxPosts']) ? (int) $input['maxPosts'] : null],
            'Instagram' => ['resultsLimit', isset($input['resultsLimit']) ? (int) $input['resultsLimit'] : null],
            'TikTok' => ['maxItems', isset($input['maxItems']) ? (int) $input['maxItems'] : null],
            default => ['limit', null],
        };
    }

    protected function apifyPollTimeoutSeconds(): int
    {
        if (app()->environment('testing') && isset($this->params['poll_timeout_seconds'])) {
            return max(1, (int) $this->params['poll_timeout_seconds']);
        }

        return 900;
    }

    protected function apifyPollSleepSeconds(string $platform): int
    {
        if (app()->environment('testing') && isset($this->params['poll_sleep_seconds'])) {
            return max(1, (int) $this->params['poll_sleep_seconds']);
        }

        return in_array($platform, ['Facebook', 'Instagram', 'TikTok'], true) ? 5 : 10;
    }

    protected function datasetItemCountAtLeast(string $token, string $datasetId, int $limit): bool
    {
        if ($limit < 1) {
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(20)
                ->get("https://api.apify.com/v2/datasets/{$datasetId}/items", [
                    'format' => 'json',
                    'limit' => 1,
                    'offset' => max(0, $limit - 1),
                ]);

            if (! $response->successful()) {
                return false;
            }

            $items = $response->json();
            return is_array($items) && ! empty($items);
        } catch (\Throwable $e) {
            Log::warning('[Apify] Failed to inspect dataset item count.', [
                'dataset_id' => $datasetId,
                'limit' => $limit,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function isCostLimitAbort(?string $status, ?string $statusMessage, ?ApifyActor $actor = null, array $runData = []): bool
    {
        if (! in_array($status, ['ABORTED', 'ABORTING'], true)) {
            return false;
        }

        $message = Str::lower((string) $statusMessage);

        if (str_contains($message, 'maximum cost')
            || str_contains($message, 'max total charge')
            || str_contains($message, 'maxtotalchargeusd')) {
            return true;
        }

        $maximumCost = (float) ($actor?->maximum_cost_per_run_usd ?? 0);
        $usageTotal = (float) data_get($runData, 'usageTotalUsd', 0);

        return $maximumCost > 0
            && $usageTotal > 0
            && $usageTotal >= ($maximumCost * 0.95);
    }

    protected function costLimitNote(?string $statusMessage, ?ApifyActor $actor = null): string
    {
        $message = (string) $statusMessage;
        $amount = null;
        if (preg_match('/\\$\\s*([0-9]+(?:\\.[0-9]+)?)/', $message, $matches)) {
            $amount = '$' . $matches[1];
        } elseif ($actor && (float) ($actor->maximum_cost_per_run_usd ?? 0) > 0) {
            $amount = '$' . rtrim(rtrim(number_format((float) $actor->maximum_cost_per_run_usd, 4, '.', ''), '0'), '.');
        }

        $amountText = $amount ? " {$amount}" : '';

        return "Batas biaya Apify{$amountText} tercapai. Run dihentikan aman, data yang sudah terkumpul tetap disimpan dan diproses.";
    }

    protected function apifyCooldownMinutes(?string $message, int $baseMinutes = 20): int
    {
        $message = strtolower((string) $message);

        if (str_contains($message, 'monthly usage hard limit exceeded') || str_contains($message, 'platform-feature-disabled')) {
            return max(60, $baseMinutes * 6);
        }

        if (str_contains($message, 'timeout') || str_contains($message, 'connection') || str_contains($message, 'could not')) {
            return max(15, $baseMinutes);
        }

        return max(10, $baseMinutes);
    }

    protected function isPlaceholderOrNoiseContent(?string $content): bool
    {
        if (empty($content)) {
            return true;
        }

        $contentLower = strtolower(trim($content));

        // Patterns of login wall / redirection page content (both raw UTF-8 and unicode-escaped)
        $patterns = [
            '/查看.*的更多信息/u',
            '/\\\\u67e5\\\\u770b.*\\\\u7684\\\\u66f4\\\\u591a\\\\u4fe1\\\\u606f/i', // literal unicode escape
            '/\\\\u67e5\\\\u770b/i', // literal unicode escape for 查看
            '/see more of.*on facebook/i',
            '/lihat lebih banyak dari.*di facebook/i',
            '/lihat selengkapnya dari.*di facebook/i',
            '/untuk melihat selengkapnya dari.*di facebook/i',
            '/login atau buat akun/i',
            '/log in or create/i',
            '/buat akun baru/i',
            '/create new account/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $contentLower)) {
                return true;
            }
        }

        return false;
    }

    protected function shouldTrustApifySearchResult(string $platform): bool
    {
        return in_array($platform, ['Facebook', 'Instagram', 'TikTok'], true);
    }

    protected function isInvalidSocialContent(?string $content): bool
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $content)) ?? '');

        if ($normalized === '') {
            return true;
        }

        if (mb_strlen($normalized) < 30) {
            return true;
        }

        $normalizedLower = Str::lower($normalized);

        foreach ($this->socialNoisePhrases() as $phrase) {
            if (Str::contains($normalizedLower, $phrase)) {
                return true;
            }
        }

        return false;
    }

    protected function keywordMatchHaystack(
        array $item,
        ?string $content,
        ?string $author,
        ?string $authorUrl,
        ?string $postUrl,
        string $platform,
    ): string {
        $haystackParts = [
            $author,
            $authorUrl,
            $postUrl,
            $item['pageName'] ?? null,
            $item['authorName'] ?? null,
            $item['username'] ?? null,
            $item['title'] ?? null,
            $item['description'] ?? null,
            $item['topLevelUrl'] ?? null,
            $item['facebookUrl'] ?? null,
        ];

        if (! in_array($platform, ['Facebook', 'Instagram', 'TikTok'], true)) {
            $haystackParts[] = $content;
        }

        $explicitSocialTerms = [];
        foreach (['hashtags', 'tags', 'searchQuery', 'searchTerm', 'keyword', 'query'] as $key) {
            $value = $item[$key] ?? null;
            if (is_array($value)) {
                foreach ($value as $entry) {
                    if (is_scalar($entry) || $entry === null) {
                        $explicitSocialTerms[] = trim((string) $entry);
                    }
                }
            } elseif (is_scalar($value) || $value === null) {
                $trimmed = trim((string) $value);
                if ($trimmed !== '') {
                    $explicitSocialTerms[] = $trimmed;
                }
            }
        }

        if (is_string($content) && preg_match_all('/(?<!\w)#([^\s#]+)/u', $content, $matches)) {
            foreach ($matches[1] as $tag) {
                $explicitSocialTerms[] = $tag;
            }
        }

        if ($explicitSocialTerms !== []) {
            $haystackParts = array_merge($haystackParts, $explicitSocialTerms);
        }

        return implode("\n", array_filter($haystackParts, static fn ($value) => filled($value)));
    }

    protected function matchesAnyKeywordInContent(array $keywords, ?string $content): bool
    {
        $keywords = array_values(array_filter(array_map('trim', $keywords)));
        if ($keywords === []) {
            return $this->matchesKeywordInContent(null, $content);
        }

        foreach ($keywords as $keyword) {
            if ($this->matchesKeywordInContent($keyword, $content)) {
                return true;
            }
        }

        return false;
    }

    protected function matchesKeywordInContent(?string $keyword, ?string $content): bool
    {
        $keyword = trim((string) $keyword);
        $content = trim((string) $content);

        if ($keyword === '') {
            return true;
        }

        if ($content === '') {
            return false;
        }

        $contentLower = Str::lower($content);
        $keywordLower = Str::lower($keyword);

        $escapedKeyword = preg_quote($keywordLower, '/');

        $pattern = '/(?<![a-zA-Z0-9_])' . $escapedKeyword . '(?![a-zA-Z0-9_])/u';
        $matched = preg_match($pattern, $contentLower);

        if (!$matched) {
            if (preg_match('/\s/u', $keywordLower) === 0) {
                return false;
            }

            // Try space-normalized match (e.g., "wali kota" matching "walikota")
            $keywordAlt = str_replace(' ', '', $keywordLower);
            $contentAlt = str_replace(' ', '', $contentLower);
            $matched = str_contains($contentAlt, $keywordAlt);
        }

        if (!$matched) {
            return false;
        }

        foreach ($this->noisePhrases() as $phrase) {
            if (Str::contains($contentLower, $phrase)) {
                return false;
            }
        }

        return true;
    }

    protected function noisePhrases(): array
    {
        return [
            'see more about',
            'lihat selengkapnya tentang',
            'follow for more',
            'suggested for you',
            'disarankan untuk anda',
            'people you may know',
            'orang yang mungkin anda kenal',
        ];
    }

    protected function socialNoisePhrases(): array
    {
        return [
            'ray-ban meta',
            'server error field_exception',
            'field_exception occured',
            'see more about',
            'lihat lebih banyak',
            'lihat selengkapnya',
        ];
    }
}
