<?php

namespace App\Jobs;

use App\Models\ApifyActor;
use App\Models\ApifySetting;
use App\Models\Project;
use App\Models\SocialMediaComment;
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
    private const COMMENT_SCRAPER_STALE_MINUTES = 45;

    public array $params;

    public function __construct(array $params)
    {
        $this->params = $params;
        $this->queue = 'apify';
    }

    protected function socialPostUrlVariants(string $postUrl): array
    {
        $trimmed = trim($postUrl);
        if ($trimmed === '') {
            return [];
        }

        return array_values(array_unique([
            $trimmed,
            rtrim($trimmed, '/'),
            rtrim($trimmed, '/') . '/',
        ]));
    }

    protected function normalizeSavedCommentsPayload(mixed $comments): array
    {
        if (is_array($comments)) {
            return $comments;
        }

        return [];
    }

    protected function normalizeSocialCommentItem(array $item, string $platform): array
    {
        $authorName = trim((string) (
            data_get($item, 'author.name')
            ?: data_get($item, 'profileName')
            ?: data_get($item, 'user.nickname')
            ?: data_get($item, 'user.uniqueId')
            ?: data_get($item, 'user.name')
            ?: data_get($item, 'user.username')
            ?: data_get($item, 'nickname')
            ?: data_get($item, 'userName')
            ?: data_get($item, 'authorName')
            ?: data_get($item, 'ownerUsername')
            ?: data_get($item, 'uniqueId')
            ?: data_get($item, 'username')
            ?: ''
        ));

        $content = trim((string) (
            data_get($item, 'text')
            ?: data_get($item, 'content')
            ?: data_get($item, 'commentText')
            ?: data_get($item, 'desc')
            ?: data_get($item, 'message')
            ?: data_get($item, 'replyText')
            ?: data_get($item, 'textContent')
            ?: ''
        ));

        $avatarUrl = trim((string) (
            data_get($item, 'author.avatarThumb')
            ?: data_get($item, 'author.avatar')
            ?: data_get($item, 'user.avatarThumb')
            ?: data_get($item, 'user.avatar')
            ?: data_get($item, 'avatarThumbnail')
            ?: data_get($item, 'avatar')
            ?: data_get($item, 'avatar_url')
            ?: data_get($item, 'profilePic')
            ?: data_get($item, 'ownerProfilePicUrl')
            ?: data_get($item, 'owner.profilePicUrl')
            ?: ''
        ));

        $postedAtRaw = data_get($item, 'createTime')
            ?: data_get($item, 'createTimeISO')
            ?: data_get($item, 'timestamp')
            ?: data_get($item, 'date')
            ?: data_get($item, 'createdAt')
            ?: data_get($item, 'postedAt')
            ?: data_get($item, 'time');

        $postedAt = null;
        if ($postedAtRaw !== null && $postedAtRaw !== '') {
            try {
                if (is_numeric($postedAtRaw)) {
                    $timestamp = (int) $postedAtRaw;
                    $postedAt = strlen((string) $timestamp) >= 13
                        ? \Carbon\Carbon::createFromTimestampMs($timestamp)
                        : \Carbon\Carbon::createFromTimestamp($timestamp);
                } else {
                    $postedAt = \Carbon\Carbon::parse($postedAtRaw);
                }
            } catch (\Throwable) {
                $postedAt = null;
            }
        }

        if ($content === '') {
            $attachmentLabel = data_get($item, 'attachments.0.label');
            if ($attachmentLabel) {
                $content = '[Stiker: ' . trim((string) $attachmentLabel) . ']';
            } elseif (data_get($item, 'media.images') || data_get($item, 'media.first_party_cdn_proxied_images')) {
                $content = '[Mengirim Animasi GIF/Stiker]';
            } elseif (isset($item['attachments']) && is_array($item['attachments']) && count($item['attachments']) > 0) {
                $content = '[Mengirim Lampiran/Stiker]';
            } else {
                $content = 'Tidak ada teks komentar.';
            }
        }

        return [
            'platform' => $platform,
            'comment_id' => (string) ($item['cid'] ?? $item['id'] ?? $item['commentId'] ?? md5(json_encode($item))),
            'parent_comment_id' => (string) ($item['parentCommentId'] ?? $item['parentId'] ?? '') ?: null,
            'author_name' => $authorName !== '' ? $authorName : null,
            'author_url' => data_get($item, 'author.url')
                ?? data_get($item, 'profileUrl')
                ?? data_get($item, 'ownerProfileUrl')
                ?? null,
            'avatar_url' => $avatarUrl !== '' ? $avatarUrl : null,
            'content' => $content,
            'like_count' => (int) (
                data_get($item, 'diggCount')
                ?: data_get($item, 'likeCount')
                ?: data_get($item, 'likesCount')
                ?: data_get($item, 'likes')
                ?: data_get($item, 'like_count')
                ?: 0
            ),
            'posted_at' => $postedAt,
            'raw_json' => json_encode($item),
        ];
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
            
            $isCommentScraper = $actor && (strtolower((string) $actor->function_type) === 'comment scraper');
            if ($isCommentScraper) {
                $platformLower = strtolower($platform);
                $preCheckQuery = \App\Models\SocialMediaItem::where('project_id', $projectId)
                    ->where('platform', $platform)
                    ->whereNotNull('post_url');

                if ($platformLower === 'tiktok') {
                    $preCheckQuery = $preCheckQuery
                        ->where('post_url', 'like', '%tiktok.com/@%')
                        ->where('post_url', 'like', '%/video/%');
                } elseif ($platformLower === 'instagram') {
                    $preCheckQuery = $preCheckQuery
                        ->where('post_url', 'like', '%instagram.com/%');
                } elseif ($platformLower === 'facebook') {
                    $preCheckQuery = $preCheckQuery
                        ->where('post_url', 'like', '%facebook.com/%');
                }

                $candidateCount = $preCheckQuery
                    ->get(['post_url'])
                    ->filter(function ($item) {
                        $urlHash = md5((string) $item->post_url);
                        return !\Illuminate\Support\Facades\Cache::has('comments_scraped_for_post:' . $urlHash)
                            && !\Illuminate\Support\Facades\Cache::has('comments_scraping_in_progress:' . $urlHash);
                    })->count();
                if ($candidateCount > 0) {
                    $forceDispatch = true;
                }
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
        // separate queue/state entries for the same project and interval block,
        // EXCEPT for Facebook which has a strict 100 character query limit and needs chunking.
        if (! $isSocialPlatform || $platform === 'Facebook') {
            $dispatchKeyParts[] = $normalizedKeyword;
        }

        if ($forceDispatch) {
            $dispatchKeyParts[] = 'force_' . time() . '_' . rand(1000, 9999);
        }

        $dispatchKey = hash('sha256', implode('|', $dispatchKeyParts));

        try {
        if ($isSocialPlatform && $actorId && ! $forceDispatch) {
                $activeThreshold = $now->copy()->subMinutes($staleAfterMinutes);
                $activeState = \App\Models\ApifyDispatchState::query()
                    ->where('project_id', $projectId)
                    ->where('actor_id', $actorId)
                    ->when($platform === 'Facebook', function ($q) use ($normalizedKeyword) {
                        $q->where('normalized_keyword', $normalizedKeyword);
                    })
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
                    'started_at' => null,
                    'completed_at' => null,
                    'next_retry_at' => null,
                    'attempts' => $state->attempts + 1,
                    'last_error_code' => null,
                    'last_error_message' => null,
                    'run_id' => null,
                ]);
            } elseif (! $state->wasRecentlyCreated && $forceDispatch) {
                // Force dispatch reuses the same state key for social actors, so
                // stale timing/error fields must be cleared before the next run.
                $state->update([
                    'status' => 'queued',
                    'queued_at' => $now,
                    'started_at' => null,
                    'completed_at' => null,
                    'next_retry_at' => null,
                    'last_error_code' => null,
                    'last_error_message' => null,
                    'run_id' => null,
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

    /**
     * Dipanggil otomatis oleh Laravel ketika job gagal karena exception / timeout.
     * Hapus tanda "dalam proses" agar URL bisa masuk antrean lagi.
     */
    public function failed(\Throwable $exception): void
    {
        $keywords = array_values(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            (array) ($this->params['keywords'] ?? [])
        )));
        $keyword = trim((string) ($this->params['keyword'] ?? ''));
        if ($keywords === [] && $keyword !== '') {
            $keywords = [$keyword];
        }

        $actorId  = $this->params['actor_id'] ?? null;
        $actor    = $actorId ? ApifyActor::find($actorId) : null;

        if ($actor && strtolower((string) $actor->function_type) === 'comment scraper') {
            foreach ($keywords as $url) {
                if (filled($url)) {
                    Cache::forget('comments_scraping_in_progress:' . md5((string) $url));
                }
            }
            Log::warning('[Apify] Comment Scraper job failed. In-progress marks cleared for retry.', [
                'actor_id' => $actorId,
                'urls'     => $keywords,
                'error'    => $exception->getMessage(),
            ]);
        }
    }

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
                // Jika state sudah di-cancel atau failed secara manual, batalkan eksekusi job ini
                // (mencegah job lama dari queue me-reset state yang sudah direset manual)
                if (in_array($state->status, ['cancelled', 'failed'], true)) {
                    Log::info("[Apify] Job dibatalkan: dispatch state #{$dispatchStateId} sudah berstatus {$state->status}.", [
                        'platform' => $platform,
                        'dispatch_state_id' => $dispatchStateId,
                    ]);
                    return;
                }

                $state->update([
                    'status' => 'processing',
                    'started_at' => now(),
                    'completed_at' => null,
                    'last_error_code' => null,
                    'last_error_message' => null,
                ]);
            }
        }


        // Validasi keaktifan proyek — lewati jika proyek nonaktif atau tidak ditemukan
        if ($projectId) {
            $project = Project::find($projectId);
            if (!$project) {
                Log::warning("[Apify] Scraping dibatalkan: Proyek ID {$projectId} tidak ditemukan.");
                if ($state) {
                    $state->update(['status' => 'failed', 'last_error_message' => "Project ID {$projectId} not found."]);
                }
                return;
            }
            if (!$project->is_active) {
                Log::warning("[Apify] Scraping dibatalkan: Proyek ID {$projectId} ({$project->name}) berstatus NONAKTIF.");
                if ($state) {
                    $state->update(['status' => 'failed', 'last_error_message' => "Project ID {$projectId} is inactive."]);
                }
                return;
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
        $token = $setting->getActiveToken();

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

        $packageLimit = null;
        if ($project && $project->package) {
            $packageLimit = $project->package->getEffectiveLimitForActor($actor);
        }

        $jobLimit = isset($this->params['limit']) ? (int) $this->params['limit'] : null;
        $limit = $jobLimit ?: $packageLimit;
        if ($limit === null || $limit < 1) {
            $message = 'Actor skipped: package limit is missing or invalid.';
            Log::warning("[Apify] {$message}", [
                'project_id' => $projectId,
                'project_name' => $projectName,
                'actor_id' => $actor->id,
                'actor_name' => $actor->actor_name,
                'platform' => $platform,
                'package_id' => $project?->package_id,
                'job_limit' => $jobLimit,
                'package_limit' => $packageLimit,
            ]);

            if ($state) {
                $state->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'last_error_message' => 'Package limit is missing or invalid for this actor.',
                ]);
            }

            return;
        }

        $limit = max(1, (int) $limit);
        $isMainSocialActor = in_array($platform, ['TikTok', 'Facebook', 'Instagram'], true)
            && strtolower((string) ($actor->function_type ?? '')) !== 'comment scraper';

        if ($isMainSocialActor) {
            // Batas hasil langsung mengikuti nilai dari konfigurasi paket
            $limit = $limit;
        }

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

        // Resolving effective memory limit from package pivot override
        $resolvedMemoryLimit = null;
        if ($project && $project->package) {
            $resolvedMemoryLimit = $project->package->getEffectiveMemoryLimitForActor($actor);
        }

        if ($resolvedMemoryLimit === null || $resolvedMemoryLimit < 128) {
            $message = 'Actor skipped: package memory limit is missing or invalid.';
            Log::warning("[Apify] {$message}", [
                'project_id' => $projectId,
                'project_name' => $projectName,
                'actor_id' => $actor->id,
                'actor_name' => $actor->actor_name,
                'platform' => $platform,
                'package_id' => $project?->package_id,
                'package_memory_limit' => $resolvedMemoryLimit,
            ]);

            if ($state) {
                $state->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'last_error_message' => 'Package memory limit is missing or invalid for this actor.',
                ]);
            }

            return;
        }

        // Run the actor — send input directly in the POST body (Apify v2 API format)
        $runUrl = "https://api.apify.com/v2/acts/{$slugForUrl}/runs";
        $apifyTimeout = max(1, (int) ($actor->timeout_seconds ?: (300 + ($limit * 6))));
        $runQuery = [
            'memory' => max(128, $resolvedMemoryLimit),
            'build' => $actor->build ?: 'latest',
            'timeout' => $apifyTimeout,
        ];

        if ((bool) ($actor->no_timeout ?? false)) {
            unset($runQuery['timeout']);
        }

        $maximumCostPerRun = 0.0;
        if ($project && $project->package) {
            $effectiveCost = $project->package->getEffectiveCostForActor($actor);
            if ($effectiveCost !== null) {
                $maximumCostPerRun = (float) $effectiveCost;
            }
        }
        if ($maximumCostPerRun > 0) {
            $runQuery['maxTotalChargeUsd'] = round($maximumCostPerRun, 4);
        }

        $runResponse = Http::withToken($token)
            ->timeout(60)
            ->post($runUrl . '?' . http_build_query($runQuery), $input);

        if (!$runResponse->successful()) {
            $msg = "Apify run failed: HTTP {$runResponse->status()}: {$runResponse->body()}";
            Log::error("[Apify] {$msg} | {$contextStr}");

            $msgLower = strtolower($msg);
            $isCredentialOrLimitError = str_contains($msgLower, 'monthly usage hard limit exceeded')
                || str_contains($msgLower, 'platform-feature-disabled')
                || str_contains($msgLower, 'user-or-token-not-found')
                || str_contains($msgLower, 'insufficient-permissions')
                || str_contains($msgLower, 'invalid');

            if ($isCredentialOrLimitError) {
                // ROTASI TOKEN OTOMATIS:
                // Token limit atau bermasalah terdeteksi, kita rotasi ke backup berikutnya.
                $oldTokenLabel = $setting->getActiveTokenLabel();
                $newTokenLabel = $setting->rotateToNextToken();
                
                Log::warning("[Apify] Token limit terdeteksi ({$oldTokenLabel}). Berhasil merotasi otomatis ke {$newTokenLabel}.");

                // Re-dispatch job scraping yang gagal ini agar langsung dicoba ulang menggunakan token baru secara instan
                $retryParams = $this->params;
                $retryParams['force_dispatch'] = true; // bypass cooldown cache
                
                // Kurangi sisa try job secara manual agar tidak infinite loop jika semua backup limit
                if ($this->attempts() < $this->tries) {
                    Log::info("[Apify] Mengirim ulang job scraping dengan token cadangan baru ({$newTokenLabel}).");
                    self::dispatch($retryParams);
                }

                $actor->update([
                    'last_run_at' => now(), 
                    'last_run_status' => 'retry_wait', 
                    'last_run_message' => substr("Limit/Token error pada {$oldTokenLabel}. Sistem memindahkan token otomatis ke {$newTokenLabel}.", 0, 500)
                ]);
                
                if ($state) {
                    $state->update([
                        'status' => 'failed', 
                        'last_error_message' => substr("Limit/Token error pada {$oldTokenLabel}. Sistem memindahkan token otomatis ke {$newTokenLabel}. Msg: " . $msg, 0, 500)
                    ]);
                }
            } else {
                $cooldownMinutes = $this->apifyCooldownMinutes($msg, (int) ($actor->interval_minutes ?? 20));
                $retryAt = now()->addMinutes($cooldownMinutes);
                
                $actor->update([
                    'last_run_at' => now(), 
                    'last_run_status' => 'failed', 
                    'last_run_message' => substr($msg, 0, 500)
                ]);
                
                Cache::put("apify_actor_retry_at:{$actor->id}", $retryAt->toDateTimeString(), $retryAt);
                if ($state) {
                    $state->update([
                        'status' => 'failed', 
                        'last_error_message' => substr($msg, 0, 500)
                    ]);
                }
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

            if (! $limitReached && $shouldAbortOnHardLimit && $limit > 0 && $this->datasetItemCountAtLeast($token, $datasetId, $limit)) {
                $limitReached = true;
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

        if ($this->isCostLimitAbort($status, $statusMessage, $maximumCostPerRun, $runData) && $this->datasetItemCountAtLeast($token, $datasetId, 1)) {
            $costLimitReached = true;
            $costLimitNote = $this->costLimitNote($statusMessage, $maximumCostPerRun);
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

        // [Cost Tracking] Catat biaya aktual dari Apify API ke database (murni additive, tidak mengubah alur)
        if ($state && !empty($runData)) {
            $items = data_get($runData, 'stats.itemCount')
                ?? data_get($runData, 'chargedEventCounts.result-item')
                ?? data_get($runData, 'chargedEventCounts.result')
                ?? data_get($runData, 'chargedEventCounts.comment')
                ?? 0;

            $state->update([
                'actual_cost_usd'   => data_get($runData, 'usageTotalCostUsd') ?? data_get($runData, 'usageTotalUsd'),
                'items_collected'   => (int) $items,
                'run_duration_secs' => (int) data_get($runData, 'stats.runTimeSecs'),
            ]);
        }

        // Fetch dataset items
        $datasetResp = Http::withToken($token)
            ->get("https://api.apify.com/v2/datasets/{$datasetId}/items", [
                'format' => 'json',
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
        $saved = 0;

        foreach ($items as $item) {
            // Cegah penyimpanan item jika Apify mengembalikan error (misal: "no_items" / "Empty or private data")
            if (isset($item['error']) || isset($item['errorDescription'])) {
                \Log::warning("[Apify] Skipped dataset item: returned error from Apify.", [
                    'url' => $item['url'] ?? $item['inputUrl'] ?? null,
                    'error' => $item['error'] ?? null,
                ]);
                continue;
            }

            // Normalise fields across platforms
            $rawPostUrl = $item['videoWebUrl'] ?? $item['submittedVideoUrl'] ?? $item['webVideoUrl'] ?? $item['url'] ?? $item['facebookUrl'] ?? $item['topLevelUrl'] ?? $item['post_url'] ?? $item['postUrl'] ?? $item['link'] ?? null;
            $postUrl    = $this->normalizeSocialPostUrl($rawPostUrl);
            
            // Fallback: If it's a comment scraper and postUrl is missing, use the original keyword (which is the post URL)
            $isCommentScraper = (strtolower((string) $actor->function_type) === 'comment scraper');
            if (empty($postUrl) && $isCommentScraper && !empty($this->keyword)) {
                $postUrl = $this->normalizeSocialPostUrl($this->keyword);
            }
            $content    = $item['postText'] ?? $item['postTitle'] ?? $item['message'] ?? $item['text'] ?? $item['caption'] ?? $item['description'] ?? $item['title'] ?? '';
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
            $likes      = $this->normalizeSocialMetric($item['diggCount'] ?? $item['reactions_count'] ?? $item['like_count'] ?? $item['likesCount'] ?? $item['likeCount'] ?? $item['likes'] ?? 0);
            $comments   = $this->normalizeSocialMetric($item['commentCount'] ?? $item['comment_count'] ?? $item['comments_count'] ?? $item['commentsCount'] ?? $item['comments'] ?? 0);
            $shares     = $this->normalizeSocialMetric($item['shareCount'] ?? $item['share_count'] ?? $item['reshare_count'] ?? $item['sharesCount'] ?? $item['shares'] ?? 0);
            $views      = $this->normalizeSocialMetric($item['playCount'] ?? $item['view_count'] ?? $item['viewsCount'] ?? $item['viewCount'] ?? $item['views'] ?? 0);
            $followers  = $this->normalizeSocialMetric($item['authorMeta']['fans'] ?? $item['follower_count'] ?? $item['followersCount'] ?? $item['followerCount'] ?? 0);

            $isCommentScraper = (strtolower((string) $actor->function_type) === 'comment scraper');

            if ($platform === 'Instagram') {
                if (empty($postUrl)) {
                    Log::warning("[Apify] Skipped IG item: missing url/post_url");
                    continue;
                }

                if ($isCommentScraper && trim((string) $content) === '') {
                    $content = '[Komentar Instagram tanpa teks]';
                }

                if (! $isCommentScraper && empty($content)) {
                    Log::warning("[Apify] Skipped IG item: missing caption/content");
                    continue;
                }

                if (! $isCommentScraper) {
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

                $actorFunctionType = strtolower((string) $actor->function_type);
                $isInstagramPostSearch = $platform === 'Instagram'
                    && $actorFunctionType === 'search post'
                    && (($input['resultsType'] ?? 'posts') === 'posts');

                // Filter batas waktu 7 hari dihapus sesuai instruksi user agar semua postingan ditarik.

                $item['_metadata'] = [
                    'source_mode' => $isInstagramPostSearch ? 'instagram_post_search' : 'posts',
                    'recency_policy' => $isInstagramPostSearch ? 'ignored' : 'enforced',
                    'is_recent_7d' => $postedAtCarbon ? $postedAtCarbon->greaterThanOrEqualTo(now()->subDays(7)->startOfDay()) : false,
                    'keyword' => $keyword,
                ];
            }

            $keywordHaystack = in_array($platform, ['Instagram', 'TikTok'], true)
                ? $this->socialHashtagMatchHaystack($item)
                : $this->keywordMatchHaystack(
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
                Log::info('[Apify] Social item stored without project link: keyword proyek tidak cocok.', [
                    'platform' => $platform,
                    'project_id' => $projectId,
                    'keywords' => $keywords,
                    'post_url' => $postUrl,
                    'author' => $author,
                    'content_excerpt' => Str::limit((string) $content, 120),
                ]);
            }
            if (
                in_array($platform, ['Facebook', 'Instagram', 'TikTok'], true)
                && $this->isInvalidSocialContent($content, $platform)
            ) {
                Log::info('[Apify] Social item stored even though content is short/noisy.', [
                    'platform' => $platform,
                    'project_id' => $projectId,
                    'post_url' => $postUrl,
                    'author' => $author,
                    'content_excerpt' => Str::limit((string) $content, 120),
                ]);
            }
            if ($this->isPlaceholderOrNoiseContent($content)) {
                Log::info('[Apify] Social item stored even though content looks like placeholder/noise.', [
                    'platform' => $platform,
                    'project_id' => $projectId,
                    'post_url' => $postUrl,
                    'author' => $author,
                    'content_excerpt' => Str::limit((string) $content, 120),
                ]);
            }

            // Logika Khusus Penyimpanan Data Komentar (Comment Scraper)
            if (strtolower((string) $actor->function_type) === 'comment scraper') {
                if (empty($postUrl)) {
                    Log::warning("[Apify] Skipped comment item: missing postUrl/post_url");
                    continue;
                }

                $postUrlVariants = $this->socialPostUrlVariants($postUrl);
                $mainPost = SocialMediaItem::whereIn('post_url', $postUrlVariants)
                    ->orderByDesc('comment_count')
                    ->orderByDesc('id')
                    ->first();
                if ($mainPost) {
                    $rawJsonDecoded = json_decode($mainPost->raw_json, true) ?: [];

                    if (!isset($rawJsonDecoded['comments']) || !is_array($rawJsonDecoded['comments'])) {
                        $rawJsonDecoded['comments'] = [];
                    }

                    $commentId = $item['cid'] ?? $item['id'] ?? $item['commentId'] ?? md5(json_encode($item));
                    $exists = false;
                    foreach ($rawJsonDecoded['comments'] as $c) {
                        $existingId = $c['cid'] ?? $c['id'] ?? $c['commentId'] ?? null;
                        if ($existingId === $commentId) {
                            $exists = true;
                            break;
                        }
                    }

                    if (!$exists) {
                        $rawJsonDecoded['comments'][] = $item;
                    }

                    $updatedPayload = json_encode($rawJsonDecoded);
                    $updatedCommentCount = count($rawJsonDecoded['comments']);

                    $normalizedComment = $this->normalizeSocialCommentItem($item, $platform);
                    SocialMediaComment::updateOrCreate(
                        [
                            'social_media_item_id' => $mainPost->id,
                            'comment_id' => $normalizedComment['comment_id'],
                        ],
                        $normalizedComment
                    );

                    $mainPost->update([
                        'raw_json' => $updatedPayload,
                        'comment_count' => $updatedCommentCount
                    ]);

                    SocialMediaItem::whereIn('post_url', $postUrlVariants)
                        ->whereKeyNot($mainPost->id)
                        ->update([
                            'raw_json' => $updatedPayload,
                            'comment_count' => $updatedCommentCount,
                        ]);

                    $mainPost->refresh();

                    Log::info("[Apify] Komentar berhasil ditambahkan ke postingan utama.", [
                        'post_url' => $postUrl,
                        'comment_id' => $commentId,
                        'total_comments' => $updatedCommentCount
                    ]);

                    $saved++;
                } else {
                    $rawJsonDecoded = [
                        'post_url' => $postUrl,
                        'platform' => $platform,
                        'comments' => [$item]
                    ];

                    $mainPost = SocialMediaItem::create([
                        'project_id'    => $projectId ?: null,
                        'platform'       => $platform,
                        'post_url'       => $postUrl,
                        'author_name'    => $author,
                        'author_url'     => $authorUrl,
                        'content'        => '[Menunggu postingan utama] ' . $content,
                        'posted_at'      => $postedAtCarbon,
                        'like_count'     => 0,
                        'comment_count'  => 1,
                        'share_count'    => 0,
                        'view_count'     => 0,
                        'follower_count' => 0,
                        'raw_json'       => json_encode($rawJsonDecoded),
                    ]);

                    SocialMediaItem::whereIn('post_url', $postUrlVariants)
                        ->whereKeyNot($mainPost->id)
                        ->update([
                            'raw_json' => json_encode($rawJsonDecoded),
                            'comment_count' => 1,
                        ]);

                    $normalizedComment = $this->normalizeSocialCommentItem($item, $platform);
                    SocialMediaComment::updateOrCreate(
                        [
                            'social_media_item_id' => $mainPost->id,
                            'comment_id' => $normalizedComment['comment_id'],
                        ],
                        $normalizedComment
                    );

                    $saved++;
                }

                continue;
            }

            // --- Tentukan apakah ada comment scraper aktif untuk platform ini ---
            // Jika ada: tunda dispatch ke AI, simpan dengan comments_checked = false
            // Jika tidak ada: langsung dispatch ke AI dan set comments_checked = true
            $platformNeedsCommentCheck = in_array($platform, ['Instagram', 'TikTok', 'Facebook'], true)
                && $this->hasActiveCommentScraperForPlatform($platform, $projectId);

            $targetPostUrl = $postUrl ?? ('apify-' . md5($content . $platform));
            $existingRecord = SocialMediaItem::where('post_url', $targetPostUrl)->first();
            $commentsCheckedValue = $existingRecord ? ($existingRecord->comments_checked || ! $platformNeedsCommentCheck) : (! $platformNeedsCommentCheck);

            $record = SocialMediaItem::updateOrCreate(
                ['post_url' => $targetPostUrl],
                [
                    'project_id'       => $projectId ?: null,
                    'platform'          => $platform,
                    'author_name'       => $author,
                    'author_url'        => $authorUrl,
                    'content'           => $content,
                    'posted_at'         => $postedAtCarbon,
                    'like_count'        => (int) $likes,
                    'comment_count'     => (int) $comments,
                    'share_count'       => (int) $shares,
                    'view_count'        => (int) $views,
                    'follower_count'    => (int) $followers,
                    'raw_json'          => json_encode($item),
                    'comments_checked'  => $commentsCheckedValue,
                ]
            );

            $socialSourceName = $platform === 'TikTok' ? 'TikTok' : $platform;
            // Cross-link to ALL active projects that match the keywords (Bank Berita Concept)
            $matchingService = app(\App\Services\ContentMatchingService::class);
            if (isset($record) && $record) {
                $matchingService->crossLinkToActiveProjects($record, $projectId);
            }

            $saved++;

            // Jika platform IG/TikTok/Facebook dengan comment scraper aktif: TUNDA AI dispatch.
            // AI akan dipanggil nanti setelah comment scraper selesai (dengan komentar sebagai konteks).
            if ($platformNeedsCommentCheck) {
                Log::info('[Apify] AI dispatch ditunda: menunggu comment scraper untuk ' . $platform . '.', [
                    'post_url'   => $postUrl,
                    'social_media_item_id' => $record->id ?? null,
                ]);
                continue;
            }

            if (empty($record->id) || empty($projectId)) {
                Log::warning('[Apify] Skipped AI dispatch: missing social_media_item_id or project_id.', [
                    'social_media_item_id' => $record->id ?? null,
                    'project_id' => $projectId,
                ]);
                continue;
            }

            $dispatchStateService = app(AiAnalysisDispatchStateService::class);
            $promptTemplateId = $dispatchStateService->resolvePromptTemplateId('social');
            $providerContextHash = $dispatchStateService->resolveProviderContextHash();
            $decision = $dispatchStateService->reserveQueuedStateAndDispatch([
                'type' => 'social',
                'id' => null, // No article ID mirror
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

        // Tandai link postingan yang sudah di-scrape komentarnya agar tidak di-scrape ulang.
        // Sekaligus hapus tanda "dalam proses" yang dipasang saat dispatch.
        // Juga update DB comments_checked=true dan dispatch AI dengan komentar sebagai konteks.
        if (strtolower((string) $actor->function_type) === 'comment scraper') {
            foreach ($keywords as $url) {
                if (filled($url)) {
                    $urlHash = md5((string) $url);
                    $mainPost = \App\Models\SocialMediaItem::where(function($q) use ($url) {
                        $q->where('post_url', $url)
                          ->orWhere('post_url', rtrim($url, '/'))
                          ->orWhere('post_url', rtrim($url, '/') . '/');
                    })->first();
                    $actualCommentsCount = 0;
                    if ($mainPost) {
                        $rawJsonDecoded = json_decode($mainPost->raw_json, true) ?: [];
                        $savedComments = $this->normalizeSavedCommentsPayload($rawJsonDecoded['comments'] ?? []);
                        $actualCommentsCount = count($savedComments);
                    }

                    // Selalu tandai selesai agar tidak terjadi pengulangan scraping komentar tanpa henti
                    // pada URL post yang sama jika hasil scraping berikutnya tetap 0.
                    $shouldMarkDone = true;

                    if ($shouldMarkDone) {
                        // Tandai di cache (cepat, untuk filter loop berikutnya)
                        Cache::forever('comments_scraped_for_post:' . $urlHash, true);

                        // Tandai di DB (persisten, sumber kebenaran untuk UI dan AI dispatch)
                        if ($mainPost) {
                            SocialMediaItem::where(function($q) use ($url) {
                                $q->where('post_url', $url)
                                  ->orWhere('post_url', rtrim($url, '/'))
                                  ->orWhere('post_url', rtrim($url, '/') . '/');
                            })->update(['comments_checked' => true]);

                            // Dispatch AI untuk postingan ini sekarang (dengan komentar sebagai konteks)
                            if ($projectId) {
                                $this->dispatchAiForPostAfterCommentCheck($mainPost->fresh(), $projectId, $suppressTelegram);
                            }
                        }
                    }
                    Cache::forget('comments_scraping_in_progress:' . $urlHash);
                }
            }
        }

        if ($state) {
            $finalItemsCollected = $state->items_collected;
            if (strtolower((string) $actor->function_type) === 'comment scraper') {
                $finalItemsCollected = \App\Models\SocialMediaItem::where(function($q) {
                    $q->where('post_url', $this->keyword)
                      ->orWhere('post_url', 'ilike', '%' . $this->keyword . '%');
                })->sum('comment_count');
            }

            $state->update([
                'status' => 'success',
                'completed_at' => now(),
                'items_collected' => $finalItemsCollected,
                'last_error_message' => $costLimitReached ? $costLimitNote : ($pollTimeoutReached ? $pollTimeoutNote : null),
            ]);
        }

        // Logic Self-Chaining: Jika ini comment scraper dan masih ada URL tersisa dalam antrean proyek aktif,
        // langsung picu run berikutnya secara instan agar proses scraping terus berjalan tanpa henti.
        if (strtolower((string) $actor->function_type) === 'comment scraper') {
            $hasMoreQueue = false;
            if ($projectId) {
                $platformLower = strtolower($platform);

                // Ambil kandidat langsung dari social_media_items tanpa bergantung tabel articles
                $candidateQuery = \App\Models\SocialMediaItem::whereHas('projects', function($q) use ($projectId) {
                    $q->where('projects.id', $projectId);
                })
                    ->where('platform', $platform)
                    ->whereNotNull('post_url')
                    ->where('comments_checked', false);

                if ($platformLower === 'tiktok') {
                    $candidateQuery = $candidateQuery
                        ->where('post_url', 'like', '%tiktok.com/@%')
                        ->where('post_url', 'like', '%/video/%');
                } elseif ($platformLower === 'instagram') {
                    $candidateQuery = $candidateQuery
                        ->where('post_url', 'like', '%instagram.com/%');
                } elseif ($platformLower === 'facebook') {
                    $candidateQuery = $candidateQuery
                        ->where('post_url', 'like', '%facebook.com/%');
                }

                $candidateItems = $candidateQuery
                    ->orderBy('posted_at', 'desc')
                    ->orderBy('id', 'desc')
                    ->get(['post_url']);

                foreach ($candidateItems as $candidateItem) {
                    $urlHash = md5((string) $candidateItem->post_url);
                    $doneKey       = 'comments_scraped_for_post:' . $urlHash;
                    $inProgressKey = 'comments_scraping_in_progress:' . $urlHash;

                    if (!Cache::has($doneKey) && !Cache::has($inProgressKey)) {
                        $hasMoreQueue = true;
                        break;
                    }
                }
            }

            if ($hasMoreQueue) {
                // PROTEKSI: Cek apakah sudah ada job comment scraper aktif pada platform yang sama
                $activeCommentScrapersCount = \App\Models\ApifyDispatchState::whereIn('status', ['queued', 'processing'])
                    ->whereIn('actor_id', \App\Models\ApifyActor::where('function_type', 'Comment Scraper')
                        ->where('platform', $platform)
                        ->pluck('id'))
                    ->where(function ($query) {
                        $staleThreshold = now()->subMinutes(self::COMMENT_SCRAPER_STALE_MINUTES);
                        $query->where(function ($queued) use ($staleThreshold) {
                            $queued->where('status', 'queued')
                                ->where('queued_at', '>=', $staleThreshold);
                        })->orWhere(function ($processing) use ($staleThreshold) {
                            $processing->where('status', 'processing')
                                ->where(function ($active) use ($staleThreshold) {
                                    $active->where('started_at', '>=', $staleThreshold)
                                        ->orWhere('updated_at', '>=', $staleThreshold);
                                });
                        });
                    })
                    ->count();

                if ($activeCommentScrapersCount > 0) {
                    Log::info("[Apify] Self-chain dilewati: masih ada comment scraper aktif.");
                } else {
                    // Dispatch langsung ApifyScrapingJob ke Comment Scraper saja
                    // (TIDAK memanggil scraping:run-apify agar Posts Search tidak ikut terpicu).
                    $commentScraperActor = \App\Models\ApifyActor::where('function_type', 'Comment Scraper')
                        ->where('platform', $platform)
                        ->where('status', 'active')
                        ->first();

                    if ($commentScraperActor && $projectId) {
                        $nextUrls = $this->resolveNextCommentUrls($projectId, $platform);

                        if (!empty($nextUrls)) {
                            Log::info("[Apify] Self-chain: dispatch Comment Scraper langsung untuk " . count($nextUrls) . " URL.", [
                                'project_id'       => $projectId,
                                'platform'         => $platform,
                                'comment_actor_id' => $commentScraperActor->id,
                                'urls'             => $nextUrls,
                            ]);

                            // Tandai URL sebagai in-progress agar tidak didispatch ganda
                            foreach ($nextUrls as $urlToMark) {
                                Cache::put(
                                    'comments_scraping_in_progress:' . md5((string) $urlToMark),
                                    true,
                                    now()->addMinutes(30)
                                );
                            }

                            self::dispatchSafely([
                                'platform'       => $platform,
                                'keyword'        => $nextUrls[0],
                                'keywords'       => $nextUrls,
                                'project_id'     => $projectId,
                                'actor_id'       => $commentScraperActor->id,
                                'force_dispatch' => true,
                                'no_telegram'    => $suppressTelegram,
                            ]);
                        } else {
                            Log::info("[Apify] Self-chain selesai: tidak ada URL komentar tersisa.", [
                                'project_id' => $projectId,
                                'platform'   => $platform,
                            ]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Resolve next batch of unprocessed post URLs for comment scraping.
     * Returns up to 3 URLs that haven't been scraped or marked in-progress.
     */
    protected function resolveNextCommentUrls(int $projectId, string $platform): array
    {
        $platformLower = strtolower($platform);

        // Ambil kandidat langsung dari social_media_items, tanpa bergantung tabel articles.
        // Hanya ambil postingan yang belum dicek komentarnya (comments_checked = false).
        $candidateQuery = \App\Models\SocialMediaItem::whereHas('projects', function($q) use ($projectId) {
            $q->where('projects.id', $projectId);
        })
            ->where('platform', $platform)
            ->whereNotNull('post_url')
            ->where('comments_checked', false)
            ->orderBy('posted_at', 'desc')
            ->orderBy('id', 'desc');

        if ($platformLower === 'tiktok') {
            $candidateQuery->where('post_url', 'like', '%tiktok.com/@%')
                ->where('post_url', 'like', '%/video/%');
        } elseif ($platformLower === 'instagram') {
            $candidateQuery->where('post_url', 'like', '%instagram.com/%');
        } elseif ($platformLower === 'facebook') {
            $candidateQuery->where('post_url', 'like', '%facebook.com/%');
        }

        $results = [];
        foreach ($candidateQuery->get(['post_url']) as $item) {
            $urlHash = md5((string) $item->post_url);
            if (!Cache::has('comments_scraped_for_post:' . $urlHash)
                && !Cache::has('comments_scraping_in_progress:' . $urlHash)) {
                $results[] = $item->post_url;
                if (count($results) >= 3) {
                    break;
                }
            }
        }

        return $results;
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

        $project = $projectId ? \App\Models\Project::find($projectId) : null;
        $effectiveMemory = 0;
        if ($project && $project->package) {
            $effectiveMemory = (int) ($project->package->getEffectiveMemoryLimitForActor($actor) ?? 0);
        }

        $effectiveCost = 0.0;
        if ($project && $project->package) {
            $effectiveCost = (float) ($project->package->getEffectiveCostForActor($actor) ?? 0);
        }

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
            'memory_limit_mb' => $effectiveMemory,
            'maximum_cost_per_run_usd' => $effectiveCost,
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
            'Facebook' => isset($input['maxCommentsPerPost'])
                ? ['maxCommentsPerPost', (int) $input['maxCommentsPerPost']]
                : ['maxPosts', isset($input['maxPosts']) ? (int) $input['maxPosts'] : null],
            'Instagram' => ['resultsLimit', isset($input['resultsLimit']) ? (int) $input['resultsLimit'] : null],
            'TikTok' => isset($input['commentsPerPost'])
                ? ['commentsPerPost', (int) $input['commentsPerPost']]
                : ['maxItems', isset($input['maxItems']) ? (int) $input['maxItems'] : null],
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

    protected function isCostLimitAbort(?string $status, ?string $statusMessage, float $maximumCost = 0, array $runData = []): bool
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

        $usageTotal = (float) data_get($runData, 'usageTotalUsd', 0);

        return $maximumCost > 0
            && $usageTotal > 0
            && $usageTotal >= ($maximumCost * 0.95);
    }

    protected function costLimitNote(?string $statusMessage, float $maximumCost = 0): string
    {
        $message = (string) $statusMessage;
        $amount = null;
        if (preg_match('/\\$\\s*([0-9]+(?:\\.[0-9]+)?)/', $message, $matches)) {
            $amount = '$' . $matches[1];
        } elseif ($maximumCost > 0) {
            $amount = '$' . rtrim(rtrim(number_format($maximumCost, 4, '.', ''), '0'), '.');
        }

        $amountText = $amount ? " {$amount}" : '';

        return "Batas biaya Apify{$amountText} tercapai. Run dihentikan aman, data yang sudah terkumpul tetap disimpan dan diproses.";
    }

    protected function apifyCooldownMinutes(?string $message, int $baseMinutes = 20): int
    {
        $message = strtolower((string) $message);

        // Jika limit kuota habis atau token mati, sistem disetel untuk cooldown selama 10 menit saja
        if (str_contains($message, 'monthly usage hard limit exceeded') || str_contains($message, 'platform-feature-disabled')) {
            return 10;
        }

        if (str_contains($message, 'timeout') || str_contains($message, 'connection') || str_contains($message, 'could not')) {
            return 10;
        }

        return 10;
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

    protected function normalizeSocialMetric(mixed $value): int
    {
        $metric = (int) $value;

        return max(0, $metric);
    }

    protected function normalizeSocialPostUrl(mixed $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $path = $parts['path'] ?? '';
        $path = preg_replace('#/+$#', '', $path) ?? $path;
        $path = $path === '' ? '/' : $path;

        return $scheme . '://' . $host . $path;
    }

    protected function shouldTrustApifySearchResult(string $platform): bool
    {
        return in_array($platform, ['Facebook', 'Instagram', 'TikTok'], true);
    }

    protected function isInvalidSocialContent(?string $content, ?string $platform = null): bool
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $content)) ?? '');

        if ($normalized === '') {
            return true;
        }

        $minimumLength = $platform === 'TikTok' ? 8 : 30;

        if (mb_strlen($normalized) < $minimumLength) {
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
            $content,
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
        foreach (['hashtags', 'tags'] as $key) {
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

    protected function socialHashtagMatchHaystack(array $item): string
    {
        $parts = [];

        foreach (['hashtags', 'tags'] as $key) {
            $value = $item[$key] ?? null;
            if (is_array($value)) {
                foreach ($value as $entry) {
                    if (is_array($entry)) {
                        $entry = $entry['name'] ?? $entry['tag'] ?? $entry['text'] ?? $entry['value'] ?? null;
                    }

                    if (is_scalar($entry) || $entry === null) {
                        $trimmed = trim((string) $entry);
                        if ($trimmed !== '') {
                            $parts[] = $trimmed;
                        }
                    }
                }
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $trimmed = trim((string) $value);
                if ($trimmed !== '') {
                    $parts[] = $trimmed;
                }
            }
        }

        $content = $item['content'] ?? $item['description'] ?? null;
        if (is_string($content) && preg_match_all('/(?<!\w)#([^\s#]+)/u', $content, $matches)) {
            foreach ($matches[1] as $tag) {
                $parts[] = $tag;
            }
        }

        return implode("\n", array_values(array_unique($parts)));
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

            if (! $matched) {
                $tokens = array_values(array_filter(preg_split('/\s+/u', $keywordLower) ?: []));
                $tokens = array_values(array_filter($tokens, static fn ($token) => mb_strlen($token) >= 3));

                if ($tokens !== []) {
                    $matched = true;
                    foreach ($tokens as $token) {
                        if (! str_contains($contentLower, $token)) {
                            $matched = false;
                            break;
                        }
                    }
                }
            }
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

    /**
     * Cek apakah ada actor Comment Scraper yang aktif untuk platform tertentu.
     *
     * Jika proyek menggunakan paket: hanya cek actor yang termasuk dalam paket.
     * Jika tidak menggunakan paket: cek semua actor aktif secara global.
     *
     * Jika tidak ada comment scraper aktif → postingan langsung dianggap sudah dicek
     * (tidak perlu menunggu komentar sebelum tampil ke user dan dikirim ke AI).
     */
    protected function hasActiveCommentScraperForPlatform(string $platform, ?int $projectId): bool
    {
        static $cache = [];
        $cacheKey = "{$platform}|{$projectId}";
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $query = ApifyActor::where('function_type', 'Comment Scraper')
            ->where('platform', $platform)
            ->where('status', 'active');

        // Jika proyek pakai paket: cek apakah comment scraper ada di dalam paket
        if ($projectId) {
            $project = \App\Models\Project::find($projectId);
            if ($project && $project->package_id && $project->package) {
                $packageActorIds = $project->package->enabledActors()->pluck('apify_actors.id')->toArray();
                if (! empty($packageActorIds)) {
                    $query->whereIn('id', $packageActorIds);
                } else {
                    // Paket ada tapi tidak punya actor sama sekali → tidak ada comment scraper
                    $cache[$cacheKey] = false;
                    return false;
                }
            }
        }

        $result = $query->exists();
        $cache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Dispatch AI analysis untuk sebuah postingan setelah comment scraper selesai.
     *
     * Komentar dari tabel social_media_comments digabungkan ke konten postingan
     * sebagai konteks tambahan agar AI mendapat gambaran lengkap tentang reaksi publik.
     */
    protected function dispatchAiForPostAfterCommentCheck(
        SocialMediaItem $mainPost,
        int $projectId,
        bool $suppressTelegram = false
    ): void {
        // Bangun konten gabungan: konten postingan + daftar komentar
        $baseContent = (string) $mainPost->content;

        // Ambil komentar dari DB (lebih akurat dan terstruktur dari raw_json)
        $dbComments = SocialMediaComment::where('social_media_item_id', $mainPost->id)
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $commentLines = [];
        $commentPayload = [];
        if ($dbComments->isNotEmpty()) {
            $commentLines[] = "\n\n--- Komentar Publik ({$dbComments->count()} komentar) ---";
            foreach ($dbComments as $idx => $c) {
                $num = $idx + 1;
                $commentAuthor = $c->author_name ?: 'Pengguna';
                $commentText   = trim((string) $c->content) ?: '[tanpa teks]';
                $commentLines[] = "{$num}. {$commentAuthor}: {$commentText}";
                $commentPayload[] = [
                    'author_name' => $commentAuthor,
                    'content' => $commentText,
                    'posted_at' => $c->posted_at?->toIso8601String(),
                ];
            }
        } else {
            // Fallback: coba dari raw_json jika belum ada di tabel
            $rawJsonDecoded = json_decode((string) $mainPost->raw_json, true) ?: [];
            $rawComments = $rawJsonDecoded['comments'] ?? [];
            if (is_array($rawComments) && ! empty($rawComments)) {
                $commentLines[] = "\n\n--- Komentar Publik (" . count($rawComments) . " komentar) ---";
                foreach (array_slice($rawComments, 0, 50) as $idx => $c) {
                    $num = $idx + 1;
                    $commentAuthor = $c['ownerUsername'] ?? $c['authorName'] ?? $c['userName'] ?? 'Pengguna';
                    $commentText   = trim((string) ($c['text'] ?? $c['content'] ?? '')) ?: '[tanpa teks]';
                    $commentLines[] = "{$num}. {$commentAuthor}: {$commentText}";
                    $commentPayload[] = [
                        'author_name' => $commentAuthor,
                        'content' => $commentText,
                        'posted_at' => null,
                    ];
                }
            }
        }

        $enrichedContent = $baseContent . implode("\n", $commentLines);

        // Dispatch ke AI — menggunakan reserveQueuedStateAndDispatch dengan force reset
        // agar analisis sebelumnya (jika ada) digantikan dengan yang baru + komentar
        try {
            $dispatchStateService = app(AiAnalysisDispatchStateService::class);
            $promptTemplateId     = $dispatchStateService->resolvePromptTemplateId('social');
            $providerContextHash  = $dispatchStateService->resolveProviderContextHash();

            $platform   = $mainPost->platform;
            $authorName = $mainPost->author_name ?? $platform;

            $decision = $dispatchStateService->reserveQueuedStateAndDispatch([
                'type'          => 'social',
                'id'            => null, // No article ID mirror
                'item_id'       => $mainPost->id,
                'project_id'    => $projectId,
                'title'         => "Post dari {$platform} oleh {$authorName}",
                'content'       => $enrichedContent,
                'url'           => $mainPost->post_url ?? '',
                'source_name'   => $platform,
                'author_name'   => $authorName,
                'author_url'    => $mainPost->author_url,
                'like_count'    => (int) $mainPost->like_count,
                'comment_count' => (int) $mainPost->comment_count,
                'share_count'   => (int) $mainPost->share_count,
                'view_count'    => (int) $mainPost->view_count,
                'follower_count'=> (int) $mainPost->follower_count,
                'published_at'  => $mainPost->posted_at?->toIso8601String(),
                'comments'      => $commentPayload,
                'no_telegram'   => $suppressTelegram,
            ], $promptTemplateId, $providerContextHash, forceReset: true);

            Log::info('[Apify] AI dispatch setelah comment check.', [
                'post_url'        => $mainPost->post_url ?? '',
                'social_media_item_id' => $mainPost->id,
                'comment_count'   => $dbComments->count(),
                'should_dispatch' => $decision['should_dispatch'] ?? false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Apify] Gagal dispatch AI setelah comment check.', [
                'post_url' => $mainPost->post_url ?? '',
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
