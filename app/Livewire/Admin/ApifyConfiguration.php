<?php

namespace App\Livewire\Admin;

use App\Models\ApifyActor;
use App\Models\ApifySetting;
use App\Services\ApifyActorRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ApifyConfiguration extends Component
{
    public string $apiToken = '';
    public string $connectionStatus = 'belum_dicek';
    public string $lastTestStatus = '';
    public string $lastTestDatasetId = '';
    public string $lastTestMessage = '';
    public ?string $lastTestAt = null;

    public string $search = '';

    // Form fields
    public bool $showActorModal = false;
    public bool $editingActor = false;
    public ?int $editingActorId = null;
    public string $platform = 'Facebook';
    public string $actorName = '';
    public string $actorSlug = '';
    public string $functionType = 'Search Post';
    public string $defaultKeyword = '';
    public string $defaultLimit = '20';
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public string $actorStatus = 'active';
    public string $facebook_post_time_range = '24h';
    public bool $facebook_use_apify_proxy = true;
    public ?int $facebook_max_posts = null;
    public ?int $instagram_search_limit = null;
    
    // New fields
    public string $keyword_field_mapping = 'search';
    public ?string $output_mapping = '';
    public int $interval_minutes = 240;
    public int $memory_limit = 1024;
    public string $range_mode = '7d';
    public bool $post_filter_enabled = false;
    public int $priority = 1;
    public float $cost_reference = 0.0000;
    public float $maximum_cost_per_run_usd = 0.0000;
    public bool $tiktok_include_search_keywords = true;
    public string $tiktok_date_range = '7days';
    public string $tiktok_location = 'ID';
    public ?int $tiktok_max_items = null;
    public bool $tiktok_mirror_videos = true;
    public bool $tiktok_use_proxy = true;
    public string $tiktok_proxy_group = 'RESIDENTIAL';
    public string $tiktok_sort_type = 'RELEVANCE';
    public bool $tiktok_strict_keyword_match = false;
    public int $tiktok_min_play_count = 0;
    public int $tiktok_mirror_video_bytes = 262144;
    public int $tiktok_min_duration_sec = 0;
    public int $tiktok_max_concurrent_keywords = 1;

    public bool $showTestModal = false;
    public ?int $testingActorId = null;
    public string $testKeyword = '';
    public string $testLimit = '20';
    public ?string $testDateFrom = null;
    public ?string $testDateTo = null;
    public string $testResultStatus = '';
    public string $testResultCount = '';
    public string $testResultDatasetId = '';
    public string $testResultError = '';

    public ?string $flashMessage = null;
    public ?string $flashType = null;
    public bool $confirmingDelete = false;
    public ?int $deleteId = null;

    protected function adminOnly(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    protected function setting(): ApifySetting
    {
        return ApifySetting::firstOrCreate(['id' => 1]);
    }

    protected function registry(): ApifyActorRegistry
    {
        return app(ApifyActorRegistry::class);
    }

    protected function actorQuery()
    {
        return ApifyActor::query()
            ->when($this->search, function ($query) {
                $search = trim($this->search);
                $query->where(function ($inner) use ($search) {
                    $inner->where('platform', 'like', "%{$search}%")
                        ->orWhere('actor_name', 'like', "%{$search}%")
                        ->orWhere('actor_slug', 'like', "%{$search}%")
                        ->orWhere('function_type', 'like', "%{$search}%");
                });
            })
            ->orderBy('priority', 'asc')
            ->orderBy('platform')
            ->orderBy('actor_name');
    }

    public function mount(): void
    {
        $this->adminOnly();

        $setting = $this->setting();
        $this->apiToken = $setting->api_token ?? '';
        $this->connectionStatus = $setting->connection_status ?? 'belum_dicek';
        $this->lastTestStatus = $setting->last_test_status ?? '';
        $this->lastTestDatasetId = $setting->last_test_dataset_id ?? '';
        $this->lastTestMessage = $setting->last_test_message ?? '';
        $this->lastTestAt = $setting->last_test_at?->toDateTimeString();
    }

    public function render()
    {
        $this->adminOnly();

        $this->registry()->syncManagedActors();
        $actors = $this->actorQuery()->get();
        $primarySlugs = collect($this->registry()->managedSlugs());
        $legacySlugs = collect($this->registry()->legacySlugs());

        return view('livewire.admin.apify-configuration', [
            'actors' => $actors,
            'primaryActors' => $actors->whereIn('actor_slug', $primarySlugs)->values(),
            'legacyActors' => $actors->whereIn('actor_slug', $legacySlugs)->values(),
            'setting' => $this->setting(),
            'primaryActorDefs' => $this->registry()->primaryActors(),
            'legacyActorDefs' => $this->registry()->legacyActors(),
        ]);
    }

    public function saveToken(): void
    {
        $this->adminOnly();

        $token = trim($this->apiToken);

        if ($token === '') {
            $this->notify('error', 'Token API Key masih kosong.');
            return;
        }

        $setting = $this->setting();
        $setting->api_token = $token;
        $setting->connection_status = 'belum_dicek';
        $setting->last_test_status = null;
        $setting->last_test_dataset_id = null;
        $setting->last_test_message = null;
        $setting->last_test_at = null;
        $setting->save();

        $this->apiToken = $token;
        $this->connectionStatus = 'belum_dicek';
        $this->lastTestStatus = '';
        $this->lastTestDatasetId = '';
        $this->lastTestMessage = '';
        $this->lastTestAt = null;

        $this->notify('success', 'Token Apify berhasil disimpan.');
    }

    public function syncManagedActors(): void
    {
        $this->adminOnly();

        Log::info('ApifyConfiguration: Sync managed actors started.');

        try {
            $syncedActors = $this->registry()->syncManagedActors();
            $count = $syncedActors->count();

            Log::info('ApifyConfiguration: Sync managed actors completed.', [
                'count' => $count,
                'actors' => $syncedActors->pluck('actor_slug')->toArray()
            ]);

            $this->notify('success', "Actor bawaan berhasil disinkronkan ({$count} item).");
        } catch (\Throwable $e) {
            Log::error('ApifyConfiguration: Sync managed actors failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            $this->notify('error', "Gagal sinkron actor bawaan: " . $e->getMessage());
        }
    }

    public function testConnection(): void
    {
        $this->adminOnly();

        $setting = $this->setting();
        $token = trim($this->apiToken ?: $setting->api_token);

        if (blank($token)) {
            $this->connectionStatus = 'error';
            $this->lastTestStatus = 'failed';
            $this->lastTestMessage = 'Token belum diisi.';
            $this->notify('error', 'Token API Key kosong.');
            return;
        }

        try {
            // Real HTTP Connection Test to Apify users API endpoint
            $response = \Illuminate\Support\Facades\Http::timeout(12)
                ->get("https://api.apify.com/v2/users/me?token={$token}");

            if ($response->successful()) {
                $setting->connection_status = 'connected';
                $setting->last_test_status = 'success';
                $setting->last_test_dataset_id = null;
                $setting->last_test_message = 'Koneksi ke akun Apify berhasil diverifikasi.';
                $setting->last_test_at = now();
                $setting->save();

                $this->notify('success', 'Koneksi ke server Apify berhasil terhubung.');
            } else {
                $status = (int) $response->status();
                if ($status === 401) {
                    throw new \RuntimeException('Token Apify tidak valid. Periksa kembali token yang disimpan.');
                }

                throw new \RuntimeException('Apify tidak merespons dengan benar. Kode: ' . $status . '.');
            }
        } catch (\Throwable $e) {
            $setting->connection_status = 'error';
            $setting->last_test_status = 'failed';
            $setting->last_test_message = $e->getMessage();
            $setting->last_test_at = now();
            $setting->save();

            $this->notify('error', 'Koneksi Apify gagal: ' . $e->getMessage());
        }

        $this->connectionStatus = $setting->connection_status;
        $this->lastTestStatus = $setting->last_test_status ?? '';
        $this->lastTestDatasetId = $setting->last_test_dataset_id ?? '';
        $this->lastTestMessage = $setting->last_test_message ?? '';
        $this->lastTestAt = $setting->last_test_at?->toDateTimeString();
    }

    public function createActor(): void
    {
        $this->adminOnly();
        $this->resetActorForm();
        $this->showActorModal = true;
        $this->editingActor = false;
    }

    public function updatedPlatform(string $value): void
    {
        if ($value === 'TikTok') {
            $this->loadTikTokPayloadDefaults();
            $this->memory_limit = max(2048, (int) $this->memory_limit);
            $this->interval_minutes = max(5, (int) $this->interval_minutes);
            $this->keyword_field_mapping = 'keyword';
        } elseif ($value === 'Facebook') {
            $this->loadFacebookPayloadDefaults();
            $this->keyword_field_mapping = 'searchQueries';
        } elseif ($value === 'Instagram') {
            $this->loadInstagramPayloadDefaults();
            $this->keyword_field_mapping = 'search';
        }

        $this->syncDefaultLimitForPlatform();
    }

    public function updatedDefaultLimit($value): void
    {
        $resolved = min(50, max(1, (int) $value));

        if ($this->platform === 'Facebook') {
            $this->facebook_max_posts = $resolved;
        } elseif ($this->platform === 'Instagram') {
            $this->instagram_search_limit = $resolved;
        } elseif ($this->platform === 'TikTok') {
            $this->tiktok_max_items = $resolved;
        }

        $this->syncDefaultLimitForPlatform();
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['facebook_max_posts', 'facebook_post_time_range', 'facebook_use_apify_proxy'], true)) {
            $this->output_mapping = $this->buildFacebookOutputMapping([]);
        } elseif (in_array($propertyName, ['instagram_search_limit', 'defaultKeyword'], true)) {
            $this->output_mapping = $this->buildInstagramOutputMapping([]);
        } elseif (in_array($propertyName, [
            'tiktok_max_items', 'tiktok_date_range', 'tiktok_location', 'tiktok_mirror_videos',
            'tiktok_use_proxy', 'tiktok_proxy_group', 'tiktok_sort_type', 'tiktok_strict_keyword_match',
            'tiktok_min_play_count', 'tiktok_mirror_video_bytes', 'tiktok_min_duration_sec', 'tiktok_max_concurrent_keywords'
        ], true)) {
            $this->output_mapping = $this->buildTikTokOutputMapping([]);
        }
    }

    public function editActor(int $id): void
    {
        $this->adminOnly();
        $actor = ApifyActor::findOrFail($id);

        $this->editingActorId = $actor->id;
        $this->platform = $actor->platform;
        $this->actorName = $actor->actor_name;
        $this->actorSlug = $actor->actor_slug;
        $this->functionType = $actor->function_type;
        $this->defaultKeyword = $actor->default_keyword ?? '';
        $this->defaultLimit = (string) min(50, (int) $actor->default_limit);
        $this->dateFrom = optional($actor->date_from)->format('Y-m-d');
        $this->dateTo = optional($actor->date_to)->format('Y-m-d');
        $this->actorStatus = $actor->status;
        
        // Load new fields
        $this->keyword_field_mapping = $actor->keyword_field_mapping;
        $this->output_mapping = $actor->output_mapping;
        $this->interval_minutes = $actor->interval_minutes;
        $this->memory_limit = $actor->memory_limit;
        $this->range_mode = $actor->range_mode;
        $this->post_filter_enabled = $actor->post_filter_enabled;
        $this->priority = $actor->priority;
        $this->cost_reference = (float) $actor->cost_reference;
        $this->maximum_cost_per_run_usd = (float) ($actor->maximum_cost_per_run_usd ?? 0);

        // Reset platform-specific state to avoid leakages
        $this->facebook_max_posts = null;
        $this->instagram_search_limit = null;
        $this->tiktok_max_items = null;

        if ($actor->platform === 'Facebook') {
            $this->loadFacebookPayloadDefaults($actor->output_mapping);
        } elseif ($actor->platform === 'TikTok') {
            $this->loadTikTokPayloadDefaults($actor->output_mapping);
        } elseif ($actor->platform === 'Instagram') {
            $this->loadInstagramPayloadDefaults($actor->output_mapping);
        }

        $this->syncDefaultLimitForPlatform();

        $this->editingActor = true;
        $this->showActorModal = true;
    }

    public function saveActor(): void
    {
        $this->adminOnly();

        try {
            $data = $this->validate([
                'platform' => ['required', 'in:Facebook,Instagram,TikTok'],
                'actorName' => ['required', 'string', 'max:255'],
                'actorSlug' => ['required', 'string', 'max:255'],
                'functionType' => ['required', 'in:Search Post,Detail Post,Comment Scraper'],
                'defaultKeyword' => ['nullable', 'string', 'max:255'],
                'defaultLimit' => ['required', 'integer', 'min:1', 'max:50'],
                'dateFrom' => ['nullable', 'date'],
                'dateTo' => ['nullable', 'date', 'after_or_equal:dateFrom'],
                'actorStatus' => ['required', 'in:active,inactive'],
                'keyword_field_mapping' => ['required', 'string', 'max:255'],
                'output_mapping' => ['nullable', 'string'],
                'interval_minutes' => ['required', 'integer', 'min:5'],
                'memory_limit' => ['required', 'integer', 'in:128,256,512,1024,2048,4096'],
                'range_mode' => ['required', 'string', 'in:24h,7d,30d,90d'],
                'post_filter_enabled' => ['boolean'],
                'priority' => ['required', 'integer', 'min:1'],
                'cost_reference' => ['required', 'numeric', 'min:0'],
                'maximum_cost_per_run_usd' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'facebook_max_posts' => ['required_if:platform,Facebook', 'nullable', 'integer', 'min:1', 'max:50'],
                'facebook_post_time_range' => ['required_if:platform,Facebook', 'nullable', 'in:24h,7d,30d,90d'],
                'facebook_use_apify_proxy' => ['required_if:platform,Facebook', 'accepted'],
                'instagram_search_limit' => ['required_if:platform,Instagram', 'nullable', 'integer', 'min:1', 'max:50'],
                'tiktok_max_items' => ['required_if:platform,TikTok', 'nullable', 'integer', 'min:1', 'max:50'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Illuminate\Support\Facades\Log::error('Apify actor validation failed', [
                'errors' => $e->errors(),
                'platform' => $this->platform,
                'actorName' => $this->actorName,
                'actorStatus' => $this->actorStatus,
                'cost_reference' => $this->cost_reference,
            ]);
            throw $e;
        }

        $data['defaultLimit'] = $this->resolvePlatformLimit();
        $this->defaultLimit = (string) $data['defaultLimit'];

        if ($data['platform'] === 'Facebook') {
            $data['range_mode'] = $this->facebook_post_time_range ?: $data['range_mode'];
            $this->range_mode = $data['range_mode'];
        }

        if (in_array($data['platform'], ['Facebook', 'Instagram', 'TikTok'], true) && $data['memory_limit'] < 1024) {
            $this->addError('memory_limit', 'Minimal RAM untuk actor sosial media adalah 1024 MB.');
            return;
        }

        // Actor whitelist validation
        $whitelist = $this->registry()->allManagedSlugs();
        if (!in_array($data['actorSlug'], $whitelist, true)) {
            $this->addError('actorSlug', 'Aktor Slug tidak terdaftar dalam whitelist resmi.');
            return;
        }

        $resolvedOutputMapping = $data['output_mapping'] ?? null;
        if ($data['platform'] === 'TikTok') {
            $resolvedOutputMapping = $this->buildTikTokOutputMapping($data);
            $this->output_mapping = $resolvedOutputMapping;
        } elseif ($data['platform'] === 'Facebook') {
            $resolvedOutputMapping = $this->buildFacebookOutputMapping($data);
            $this->output_mapping = $resolvedOutputMapping;
        } elseif ($data['platform'] === 'Instagram') {
            $resolvedOutputMapping = $this->buildInstagramOutputMapping($data);
            $this->output_mapping = $resolvedOutputMapping;
        }

        if ($resolvedOutputMapping) {
            json_decode($resolvedOutputMapping);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError('output_mapping', 'Format JSON Output Mapping tidak valid.');
                return;
            }
        }

        $payload = [
            'platform' => $data['platform'],
            'actor_name' => $data['actorName'],
            'actor_slug' => $data['actorSlug'],
            'function_type' => $data['functionType'],
            'default_keyword' => $data['defaultKeyword'] ?? null,
            'default_limit' => $data['defaultLimit'],
            'date_from' => $data['dateFrom'] ?? null,
            'date_to' => $data['dateTo'] ?? null,
            'status' => $data['actorStatus'],
            'keyword_field_mapping' => $data['keyword_field_mapping'],
            'output_mapping' => $resolvedOutputMapping ?: null,
            'interval_minutes' => $data['interval_minutes'],
            'memory_limit' => $data['memory_limit'],
            'range_mode' => $data['range_mode'],
            'post_filter_enabled' => $data['post_filter_enabled'],
            'priority' => $data['priority'],
            'cost_reference' => $data['cost_reference'],
            'maximum_cost_per_run_usd' => $data['maximum_cost_per_run_usd'] ?? 0,
        ];

        if ($this->editingActor && $this->editingActorId) {
            ApifyActor::findOrFail($this->editingActorId)->update($payload);
            $this->notify('success', 'Actor berhasil diperbarui.');
        } else {
            ApifyActor::create($payload);
            $this->notify('success', 'Actor berhasil ditambahkan.');
        }

        $this->showActorModal = false;
        $this->resetActorForm();
    }

    public function requestDeleteActor(int $id): void
    {
        $this->adminOnly();
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    public function deleteActorConfirmed(): void
    {
        $this->adminOnly();
        abort_unless($this->deleteId, 400);
        $actor = ApifyActor::findOrFail($this->deleteId);
        if ($this->registry()->isPrimarySlug($actor->actor_slug)) {
            $this->confirmingDelete = false;
            $this->deleteId = null;
            $this->notify('error', 'Actor bawaan sistem tidak dapat dihapus.');
            return;
        }
        $actor->delete();
        $this->confirmingDelete = false;
        $this->deleteId = null;
        $this->notify('success', 'Actor berhasil dihapus.');
    }

    public function toggleActorStatus(int $id): void
    {
        $this->adminOnly();
        $actor = ApifyActor::findOrFail($id);
        $actor->status = $actor->status === 'active' ? 'inactive' : 'active';
        $actor->save();
        $this->notify('success', 'Status actor berhasil diperbarui.');
    }

    public function openTestRun(int $id): void
    {
        $this->adminOnly();
        $actor = ApifyActor::findOrFail($id);

        $this->testingActorId = $actor->id;
        $this->testKeyword = $actor->default_keyword ?? '';
        $this->testLimit = (string) min(50, (int) $actor->default_limit);
        $this->testDateFrom = optional($actor->date_from)->format('Y-m-d');
        $this->testDateTo = optional($actor->date_to)->format('Y-m-d');
        $this->testResultStatus = '';
        $this->testResultCount = '';
        $this->testResultDatasetId = '';
        $this->testResultError = '';
        $this->showTestModal = true;
    }

    public function runTest(): void
    {
        $this->adminOnly();

        $this->validate([
            'testKeyword' => ['required', 'string', 'max:255'],
            'testLimit' => ['required', 'integer', 'min:1', 'max:1000'],
            'testDateFrom' => ['nullable', 'date'],
            'testDateTo' => ['nullable', 'date', 'after_or_equal:testDateFrom'],
        ]);

        $actor = ApifyActor::findOrFail($this->testingActorId);
        $setting = $this->setting();

        if (blank($setting->api_token)) {
            $this->testResultStatus = 'failed';
            $this->testResultCount = '0';
            $this->testResultError = 'Gagal menjalankan: API Token Apify belum diisi.';
            $this->notify('error', 'Token API Key kosong.');
            return;
        }

        try {
            $input = $actor->buildInputPayload(
                $this->testKeyword,
                (int) $this->testLimit,
                $this->testDateFrom ?: null,
                $this->testDateTo ?: null,
            );

            $slugForUrl = str_replace('/', '~', $actor->actor_slug);

            // Run Apify Actor remotely
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->post(
                    "https://api.apify.com/v2/acts/{$slugForUrl}/runs?token={$setting->api_token}&memory={$actor->memory_limit}",
                    $input
                );

            if ($response->successful()) {
                $data = $response->json();
                $runId = $data['data']['id'] ?? '';
                $datasetId = $data['data']['defaultDatasetId'] ?? '';

                $this->testResultStatus = 'success';
                $this->testResultCount = 'Pemicuan aktor berhasil (Run ID: ' . $runId . ')';
                $this->testResultDatasetId = $datasetId;
                $this->testResultError = '';

                $actor->last_run_at = now();
                $actor->last_run_status = 'success';
                $actor->last_run_message = 'Aktor berhasil dipicu. Dataset ID: ' . $datasetId;
                $actor->save();

                $setting->last_test_status = 'success';
                $setting->last_test_dataset_id = $datasetId;
                $setting->last_test_message = 'Aktor berhasil dijalankan.';
                $setting->last_test_at = now();
                $setting->save();

                $this->notify('success', 'Aktor Apify berhasil dijalankan.');
            } else {
                throw new \Exception('Apify Response HTTP ' . $response->status() . ': ' . $response->body());
            }
        } catch (\Throwable $e) {
            $this->testResultStatus = 'failed';
            $this->testResultCount = '0';
            $this->testResultDatasetId = '';
            $this->testResultError = $e->getMessage();

            $actor->last_run_at = now();
            $actor->last_run_status = 'failed';
            $actor->last_run_message = $e->getMessage();
            $actor->save();

            $this->notify('error', 'Gagal memicu aktor Apify: ' . $e->getMessage());
        }
    }

    public function closeActorModal(): void
    {
        $this->showActorModal = false;
        $this->resetActorForm();
    }

    public function closeTestModal(): void
    {
        $this->showTestModal = false;
        $this->testingActorId = null;
    }

    protected function resetActorForm(): void
    {
        $this->editingActor = false;
        $this->editingActorId = null;
        $this->platform = 'Facebook';
        $this->actorName = '';
        $this->actorSlug = '';
        $this->functionType = 'Search Post';
        $this->defaultKeyword = '';
        $this->defaultLimit = '20';
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->actorStatus = 'active';
        $this->keyword_field_mapping = 'searchQueries';
        $this->output_mapping = '';
        $this->interval_minutes = 240;
        $this->memory_limit = 1024;
        $this->range_mode = '7d';
        $this->post_filter_enabled = false;
        $this->priority = 1;
        $this->cost_reference = 0.0000;
        $this->maximum_cost_per_run_usd = 0.0000;
        $this->tiktok_include_search_keywords = true;
        $this->tiktok_date_range = '7days';
        $this->tiktok_location = 'ID';
        $this->tiktok_max_items = null;
        $this->tiktok_mirror_videos = true;
        $this->tiktok_use_proxy = true;
        $this->tiktok_proxy_group = 'RESIDENTIAL';
        $this->tiktok_sort_type = 'RELEVANCE';
        $this->tiktok_strict_keyword_match = false;
        $this->tiktok_min_play_count = 0;
        $this->tiktok_mirror_video_bytes = 262144;
        $this->tiktok_min_duration_sec = 0;
        $this->tiktok_max_concurrent_keywords = 1;
        $this->facebook_post_time_range = '24h';
        $this->facebook_use_apify_proxy = true;
        $this->facebook_max_posts = null;
        $this->instagram_search_limit = null;

        $this->loadFacebookPayloadDefaults();
        $this->syncDefaultLimitForPlatform();
    }

    protected function loadFacebookPayloadDefaults(?string $outputMapping = null): void
    {
        $template = $outputMapping ? json_decode($outputMapping, true) : null;
        if (!is_array($template)) {
            $template = json_decode($this->registry()->primaryActors()['facebook']['output_mapping'], true) ?: [];
        }

        $maxPosts = $template['maxPosts'] ?? null;
        if (is_numeric($maxPosts)) {
            $this->facebook_max_posts = min(50, max(1, (int) $maxPosts));
        } elseif (!$this->editingActor) {
            $actorDefaultLimit = $this->registry()->primaryActors()['facebook']['default_limit'] ?? $this->defaultLimit;
            $this->facebook_max_posts = min(50, max(1, (int) $actorDefaultLimit));
        }

        $resolvedPostTimeRange = (string) ($template['postTimeRange'] ?? '');
        if ($resolvedPostTimeRange === '' || str_contains($resolvedPostTimeRange, '{time_filter}')) {
            $resolvedPostTimeRange = match ($this->range_mode) {
                '24h', '1d' => '24h',
                '7d' => '7d',
                '30d' => '30d',
                '90d' => '90d',
                default => '7d',
            };
        }
        $this->facebook_post_time_range = $resolvedPostTimeRange;
        $this->range_mode = $resolvedPostTimeRange;

        $this->facebook_use_apify_proxy = (bool) data_get($template, 'proxyConfiguration.useApifyProxy', true);
        $this->defaultLimit = (string) $this->facebook_max_posts;
    }

    protected function loadInstagramPayloadDefaults(?string $outputMapping = null): void
    {
        $template = $outputMapping ? json_decode($outputMapping, true) : null;
        if (!is_array($template)) {
            $template = json_decode($this->registry()->primaryActors()['instagram']['output_mapping'], true) ?: [];
        }

        $search = (string) ($template['search'] ?? '');
        $this->defaultKeyword = $search !== '' ? $search : ($this->editingActor ? $this->defaultKeyword : $this->registry()->primaryActors()['instagram']['default_keyword']);
        $this->instagram_search_limit = min(50, max(1, (int) ($template['searchLimit'] ?? $this->registry()->primaryActors()['instagram']['default_limit'])));
        $this->keyword_field_mapping = 'search';
        $this->defaultLimit = (string) $this->instagram_search_limit;
    }

    protected function loadTikTokPayloadDefaults(?string $outputMapping = null): void
    {
        $template = $outputMapping ? json_decode($outputMapping, true) : null;
        if (!is_array($template)) {
            $template = json_decode($this->registry()->primaryActors()['tiktok']['output_mapping'], true) ?: [];
        }

        $this->tiktok_include_search_keywords = (bool) ($template['includeSearchKeywords'] ?? true);
        $this->tiktok_date_range = (string) ($template['dateRange'] ?? '7days');
        $this->tiktok_location = (string) ($template['location'] ?? 'ID');
        $this->tiktok_max_items = min(50, max(1, (int) ($template['maxItems'] ?? 100)));
        $this->tiktok_mirror_videos = (bool) ($template['mirrorVideos'] ?? true);
        $this->tiktok_use_proxy = (bool) ($template['useProxy'] ?? true);
        $this->tiktok_proxy_group = (string) data_get($template, 'proxyConfiguration.apifyProxyGroups.0', 'RESIDENTIAL');
        $this->tiktok_sort_type = (string) ($template['sortType'] ?? 'RELEVANCE');
        $this->tiktok_strict_keyword_match = (bool) ($template['strictKeywordMatch'] ?? false);
        $this->tiktok_min_play_count = (int) ($template['minPlayCount'] ?? 0);
        $this->tiktok_mirror_video_bytes = (int) ($template['mirrorVideoBytes'] ?? 262144);
        $this->tiktok_min_duration_sec = (int) ($template['minDurationSec'] ?? 0);
        $this->tiktok_max_concurrent_keywords = (int) ($template['maxConcurrentKeywords'] ?? 1);
        $this->defaultLimit = (string) $this->tiktok_max_items;
    }

    protected function buildTikTokOutputMapping(array $data): string
    {
        $existing = json_decode($this->output_mapping, true) ?: [];

        $dateRange = trim((string) $this->tiktok_date_range);
        if ($dateRange === '') {
            $dateRange = '7days';
        }

        $proxyGroups = array_values(array_filter(array_map(
            static fn ($v) => trim((string) $v),
            explode(',', (string) ($this->tiktok_proxy_group ?: 'RESIDENTIAL'))
        )));

        if ($proxyGroups === []) {
            $proxyGroups = ['RESIDENTIAL'];
        }

        $payload = array_merge([
            'dateRange' => '7days',
            'includeSearchKeywords' => true,
            'keywords' => ['{keyword}'],
            'location' => 'ID',
            'mirrorVideos' => true,
            'proxyConfiguration' => [
                'useApifyProxy' => true,
                'apifyProxyGroups' => ['RESIDENTIAL'],
                'apifyProxyCountry' => 'ID',
            ],
            'sortType' => 'RELEVANCE',
            'strictKeywordMatch' => false,
            'useProxy' => true,
            'minPlayCount' => 0,
            'mirrorVideoBytes' => 262144,
            'minDurationSec' => 0,
            'maxConcurrentKeywords' => 1,
        ], $existing, [
            'dateRange' => $dateRange,
            'includeSearchKeywords' => (bool) $this->tiktok_include_search_keywords,
            'location' => $this->tiktok_location ?: 'ID',
            'maxItems' => min(50, max(1, (int) $this->tiktok_max_items)),
            'mirrorVideos' => (bool) $this->tiktok_mirror_videos,
            'proxyConfiguration' => [
                'useApifyProxy' => (bool) $this->tiktok_use_proxy,
                'apifyProxyGroups' => $proxyGroups,
                'apifyProxyCountry' => data_get($existing, 'proxyConfiguration.apifyProxyCountry', 'ID'),
            ],
            'sortType' => $this->tiktok_sort_type ?: 'RELEVANCE',
            'strictKeywordMatch' => (bool) $this->tiktok_strict_keyword_match,
            'useProxy' => (bool) $this->tiktok_use_proxy,
            'minPlayCount' => (int) $this->tiktok_min_play_count,
            'mirrorVideoBytes' => (int) $this->tiktok_mirror_video_bytes,
            'minDurationSec' => (int) $this->tiktok_min_duration_sec,
            'maxConcurrentKeywords' => max(1, (int) $this->tiktok_max_concurrent_keywords),
        ]);

        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    protected function buildFacebookOutputMapping(array $data): string
    {
        $existing = json_decode($this->output_mapping, true) ?: [];

        $payload = array_merge([
            'postTimeRange' => '24h',
            'proxyConfiguration' => [
                'useApifyProxy' => true,
            ],
            'searchQueries' => ['{keyword}'],
        ], $existing, [
            'maxPosts' => min(50, max(1, (int) $this->facebook_max_posts)),
            'postTimeRange' => $this->facebook_post_time_range ?: '24h',
            'proxyConfiguration' => [
                'useApifyProxy' => (bool) $this->facebook_use_apify_proxy,
            ],
        ]);

        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    protected function buildInstagramOutputMapping(array $data): string
    {
        $existing = json_decode($this->output_mapping, true) ?: [];

        $keywords = array_values(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            preg_split('/\s*,\s*/', (string) ($data['defaultKeyword'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));

        if ($keywords === []) {
            $keywords = [trim((string) ($data['defaultKeyword'] ?? $this->registry()->primaryActors()['instagram']['default_keyword']))];
        }

        $payload = array_merge([
            'enhanceUserSearchWithFacebookPage' => false,
            'liveSearch' => true,
            'searchType' => 'popular',
        ], $existing, [
            'search' => implode(',', $keywords),
            'searchLimit' => min(50, max(1, (int) $this->instagram_search_limit)),
        ]);

        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    protected function syncDefaultLimitForPlatform(): void
    {
        $this->defaultLimit = (string) $this->resolvePlatformLimit();
    }

    protected function resolvePlatformLimit(): int
    {
        return match ($this->platform) {
            'Facebook' => min(50, max(1, (int) $this->facebook_max_posts)),
            'Instagram' => min(50, max(1, (int) $this->instagram_search_limit)),
            'TikTok' => min(50, max(1, (int) $this->tiktok_max_items)),
            default => min(50, max(1, (int) $this->defaultLimit)),
        };
    }

    protected function notify(string $type, string $message): void
    {
        $this->flashType = $type;
        $this->flashMessage = $message;
        $payload = [
            'type' => $type,
            'title' => $message,
            'message' => '',
        ];

        if (method_exists($this, 'dispatchBrowserEvent')) {
            $this->dispatchBrowserEvent('admin-toast', $payload);
        }

        $this->dispatch('admin-toast',
            type: $type,
            title: $message,
            message: '',
            payload: $payload
        );
    }

    public function dehydrate(): void
    {
        $this->flashType = null;
        $this->flashMessage = null;
    }
}
