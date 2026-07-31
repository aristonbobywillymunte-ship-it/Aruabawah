<?php

namespace App\Livewire\Admin;

use App\Models\ApifyActor;
use App\Models\ApifySetting;
use App\Services\ApifyActorRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
    public string $defaultLimit = '';
    public string $instagram_results_type = 'posts';
    public ?int $instagram_results_limit = null;
    public bool $instagram_include_nested_comments = false;
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public string $actorStatus = 'active';
    public string $facebook_post_time_range = '24h';
    public bool $facebook_use_apify_proxy = true;
    public ?int $facebook_max_posts = null;
    
    // New fields
    public string $keyword_field_mapping = 'search';
    public ?string $output_mapping = '';
    public string $build = 'latest';
    public int $timeout_seconds = 10000;
    public bool $no_timeout = false;
    public int $interval_minutes = 240;
    public int $memory_limit = 0;
    public string $range_mode = '7d';
    public int $priority = 1;

    protected $listeners = [
        'testConnection' => 'testConnection'
    ];

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

    // Toggle status confirmation
    public bool $confirmingToggle = false;
    public ?int $toggleId = null;
    public string $toggleActorName = '';
    public string $toggleCurrentStatus = '';

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
            ->orderBy('platform', 'asc')
            ->orderBy('priority', 'asc')
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

        // Sync managed actors once on initial mount so the modal render path stays ringan.
        $this->registry()->syncManagedActors();
    }

    public function render()
    {
        $this->adminOnly();

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
            'instagramActorDefs' => $this->instagramActorDefinitions(),
            'tiktokActorDefs' => $this->tikTokActorDefinitions(),
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
            $definition = $this->resolveTikTokActorDefinition($this->actorSlug ?: null)
                ?? $this->resolveTikTokActorDefinition();

            if ($definition) {
                if (! $this->editingActor) {
                    $this->applyActorDefinitionDefaults($definition);
                } else {
                    $this->loadTikTokPayloadDefaults($this->actorSlug ?: $definition['actor_slug']);
                }
            }
        } elseif ($value === 'Facebook') {
            $this->loadFacebookPayloadDefaults();
            $this->keyword_field_mapping = 'searchQueries';
            if (! $this->editingActor) {
                $facebook = $this->registry()->primaryActors()['facebook'];
                $this->actorName = $facebook['actor_name'];
                $this->actorSlug = $facebook['actor_slug'];
                $this->functionType = $facebook['function_type'];
                $this->actorStatus = (string) ($facebook['status'] ?? 'active');
                $this->memory_limit = (int) $facebook['memory_limit'];
                $this->interval_minutes = (int) $facebook['interval_minutes'];
                $this->range_mode = (string) $facebook['range_mode'];
            }
        } elseif ($value === 'Instagram') {
            $definition = $this->resolveInstagramActorDefinition($this->actorSlug ?: null)
                ?? $this->resolveInstagramActorDefinition();

            if ($definition) {
                if (! $this->editingActor) {
                    $this->applyActorDefinitionDefaults($definition);
                }

                $this->loadInstagramPayloadDefaults($definition['output_mapping'] ?? null);
            }
        }

    }

    public function updatedActorSlug(string $value): void
    {
        if ($this->platform === 'Instagram') {
            $definition = $this->resolveInstagramActorDefinition($value);

            if ($definition) {
                $this->applyActorDefinitionDefaults($definition);
                $this->loadInstagramPayloadDefaults($definition['output_mapping'] ?? null);
            } else {
                $this->loadInstagramPayloadDefaults();
            }
            return;
        }

        if ($this->platform !== 'TikTok') {
            return;
        }

        $definition = $this->resolveTikTokActorDefinition($value);
        if ($definition) {
            $this->applyActorDefinitionDefaults($definition);
        } else {
            $this->loadTikTokPayloadDefaults($value);
        }
    }

    public function updatedDefaultLimit($value): void
    {
        $resolved = (int) $value;

        if ($this->platform === 'Facebook') {
            $this->facebook_max_posts = $resolved;
        } elseif ($this->platform === 'Instagram') {
            $this->instagram_results_limit = $resolved;
        }

    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['facebook_max_posts', 'facebook_post_time_range', 'facebook_use_apify_proxy'], true)) {
            $this->output_mapping = $this->buildFacebookOutputMapping([]);
        } elseif (in_array($propertyName, ['defaultKeyword', 'instagram_results_type', 'instagram_results_limit', 'instagram_include_nested_comments'], true)) {
            $this->output_mapping = $this->buildInstagramOutputMapping([]);
        }
    }

    public function editActor(int $id): void
    {
        $this->adminOnly();
        $actor = ApifyActor::findOrFail($id);

        $this->editingActorId = $actor->id;
        $this->editingActor = true;
        $this->platform = $actor->platform;
        $this->actorName = $actor->actor_name;
        $this->actorSlug = $actor->actor_slug;
        $this->functionType = $actor->function_type;
        $this->defaultKeyword = $actor->default_keyword ?? '';
        $this->defaultLimit = (string) (int) $actor->default_limit;
        $this->dateFrom = optional($actor->date_from)->format('Y-m-d');
        $this->dateTo = optional($actor->date_to)->format('Y-m-d');
        $this->actorStatus = $actor->status;
        
        // Load new fields
        $this->keyword_field_mapping = $actor->keyword_field_mapping;
        $this->output_mapping = $actor->output_mapping;
        $this->build = (string) ($actor->build ?? 'latest');
        $this->timeout_seconds = (int) ($actor->timeout_seconds ?? 10000);
        $this->no_timeout = (bool) ($actor->no_timeout ?? false);
        $this->interval_minutes = $actor->interval_minutes;
        $this->memory_limit = $actor->memory_limit;
        $this->range_mode = $actor->range_mode;
        $this->priority = $actor->priority;

        // Reset platform-specific state to avoid leakages
        $this->facebook_max_posts = null;
        $this->loadTikTokPayloadDefaults();

        if ($actor->platform === 'Facebook') {
            $this->loadFacebookPayloadDefaults($actor->output_mapping);
        } elseif ($actor->platform === 'Instagram') {
            $this->loadInstagramPayloadDefaults($actor->output_mapping);
        } elseif ($actor->platform === 'TikTok') {
            $this->loadTikTokPayloadDefaults($actor->actor_slug);
        }

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
                'defaultLimit' => ['required', 'integer'],
                'dateFrom' => ['nullable', 'date'],
                'dateTo' => ['nullable', 'date', 'after_or_equal:dateFrom'],
                'actorStatus' => ['required', 'in:active,inactive'],
                'keyword_field_mapping' => ['required', 'string', 'max:255'],
                'output_mapping' => ['nullable', 'string'],
                'build' => ['required', 'in:latest,beta,prod'],
                'timeout_seconds' => ['required', 'integer'],
                'no_timeout' => ['boolean'],
                'interval_minutes' => ['required', 'integer'],
                'memory_limit' => ['required', 'integer'],
                'range_mode' => ['required', 'string'],
                'priority' => ['required', 'integer'],
                'facebook_max_posts' => ['required_if:platform,Facebook', 'nullable', 'integer'],
                'facebook_post_time_range' => ['required_if:platform,Facebook', 'nullable', 'string'],
                'facebook_use_apify_proxy' => ['required_if:platform,Facebook', 'accepted'],
                'instagram_results_type' => ['nullable', 'in:posts,reels'],
                'instagram_results_limit' => ['nullable', 'integer'],
                'instagram_include_nested_comments' => ['boolean'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Illuminate\Support\Facades\Log::error('Apify actor validation failed', [
                'errors' => $e->errors(),
                'platform' => $this->platform,
                'actorName' => $this->actorName,
                'actorStatus' => $this->actorStatus,
            ]);
            throw $e;
        }

        $data['defaultLimit'] = (int) $data['defaultLimit'];

        if ($data['platform'] === 'Facebook') {
            $data['range_mode'] = $this->facebook_post_time_range ?: $data['range_mode'];
            $this->range_mode = $data['range_mode'];
        } elseif ($data['platform'] === 'Instagram') {
            $this->instagram_results_limit = $data['defaultLimit'];
            if ($this->isInstagramCommentsActor()) {
                $data['keyword_field_mapping'] = 'directUrls';
            } else {
                $data['keyword_field_mapping'] = 'hashtags';
            }
        }

        $data['build'] = $this->build;
        $data['timeout_seconds'] = $this->no_timeout ? 0 : (int) $this->timeout_seconds;
        $data['no_timeout'] = (bool) $this->no_timeout;

        // Actor whitelist validation
        $whitelist = $this->registry()->allManagedSlugs();
        if (!in_array($data['actorSlug'], $whitelist, true)) {
            $this->addError('actorSlug', 'Aktor Slug tidak terdaftar dalam whitelist resmi.');
            return;
        }

        $resolvedOutputMapping = $data['output_mapping'] ?? null;
        if ($data['platform'] === 'Facebook') {
            $resolvedOutputMapping = $this->buildFacebookOutputMapping($data);
            $this->output_mapping = $resolvedOutputMapping;
        } elseif ($data['platform'] === 'Instagram') {
            $resolvedOutputMapping = $this->buildInstagramOutputMapping($data);
            $this->output_mapping = $resolvedOutputMapping;
        } elseif ($data['platform'] === 'TikTok') {
            $definition = $this->resolveTikTokActorDefinition($data['actorSlug']);
            if ($definition) {
                $data['keyword_field_mapping'] = $definition['keyword_field_mapping'];
            }

            $resolvedOutputMapping = $this->buildTikTokOutputMapping($data, $definition);
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
            'priority' => $data['priority'],
            'maximum_cost_per_run_usd' => 0,
        ];

        if (Schema::hasColumn('apify_actors', 'build')) {
            $payload['build'] = $data['build'];
        }
        if (Schema::hasColumn('apify_actors', 'timeout_seconds')) {
            $payload['timeout_seconds'] = $data['timeout_seconds'];
        }
        if (Schema::hasColumn('apify_actors', 'no_timeout')) {
            $payload['no_timeout'] = $data['no_timeout'];
        }

        try {
            if ($this->editingActor && $this->editingActorId) {
                ApifyActor::findOrFail($this->editingActorId)->update($payload);
                $this->notify('success', 'Actor berhasil diperbarui.');
            } else {
                ApifyActor::create($payload);
                $this->notify('success', 'Actor berhasil ditambahkan.');
            }
        } catch (\Throwable $e) {
            Log::error('Apify actor save failed', [
                'message' => $e->getMessage(),
                'platform' => $data['platform'],
                'editingActor' => $this->editingActor,
                'editingActorId' => $this->editingActorId,
                'payload' => $payload,
            ]);

            $this->notify('error', 'Gagal menyimpan actor: ' . $e->getMessage());
            return;
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

    public function requestToggleActorStatus(int $id): void
    {
        $this->adminOnly();
        $actor = ApifyActor::findOrFail($id);
        $this->toggleId = $id;
        $this->toggleActorName = $actor->actor_name;
        $this->toggleCurrentStatus = $actor->status;
        $this->confirmingToggle = true;
    }

    public function toggleActorStatusConfirmed(): void
    {
        $this->adminOnly();
        abort_unless($this->toggleId, 400);
        $actor = ApifyActor::findOrFail($this->toggleId);
        $actor->status = $actor->status === 'active' ? 'inactive' : 'active';
        $actor->save();

        $label = $actor->status === 'active' ? 'diaktifkan' : 'dinonaktifkan';
        $this->notify('success', "Actor \"{$actor->actor_name}\" berhasil {$label}.");

        $this->confirmingToggle = false;
        $this->toggleId = null;
        $this->toggleActorName = '';
        $this->toggleCurrentStatus = '';
    }

    public function cancelToggle(): void
    {
        $this->confirmingToggle = false;
        $this->toggleId = null;
        $this->toggleActorName = '';
        $this->toggleCurrentStatus = '';
    }

    public function openTestRun(int $id): void
    {
        $this->adminOnly();
        $actor = ApifyActor::findOrFail($id);

        $this->testingActorId = $actor->id;
        $this->testKeyword = $actor->default_keyword ?? '';
        $this->testLimit = (string) (int) $actor->default_limit;
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
            'testLimit' => ['required', 'integer'],
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
            $runQuery = [
                'memory' => $actor->memory_limit,
                'build' => $actor->build ?: 'latest',
            ];
            if (! $actor->no_timeout) {
                $runQuery['timeout'] = (int) ($actor->timeout_seconds ?: 10000);
            }

            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->post(
                    "https://api.apify.com/v2/acts/{$slugForUrl}/runs?token={$setting->api_token}&" . http_build_query($runQuery),
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
        $this->defaultLimit = '';
        $this->instagram_results_type = 'posts';
        $this->instagram_results_limit = null;
        $this->instagram_include_nested_comments = false;
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->actorStatus = 'active';
        $this->keyword_field_mapping = 'searchQueries';
        $this->output_mapping = '';
        $this->build = 'latest';
        $this->timeout_seconds = 10000;
        $this->no_timeout = false;
        $this->interval_minutes = 240;
        $this->memory_limit = 0;
        $this->range_mode = '7d';
        $this->facebook_post_time_range = '24h';
        $this->facebook_use_apify_proxy = true;
        $this->facebook_max_posts = null;
        $this->loadFacebookPayloadDefaults();
        $this->loadTikTokPayloadDefaults();
    }

    protected function loadFacebookPayloadDefaults(?string $outputMapping = null): void
    {
        $template = $outputMapping ? json_decode($outputMapping, true) : null;
        if (!is_array($template)) {
            $template = json_decode($this->registry()->primaryActors()['facebook']['output_mapping'], true) ?: [];
        }

        $maxPosts = $template['maxPosts'] ?? null;
        if (is_numeric($maxPosts)) {
            $this->facebook_max_posts = (int) $maxPosts;
        } elseif (!$this->editingActor && is_numeric($this->defaultLimit)) {
            $this->facebook_max_posts = (int) $this->defaultLimit;
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
    }

    protected function loadInstagramPayloadDefaults(?string $outputMapping = null): void
    {
        $definition = $this->resolveInstagramActorDefinition($this->actorSlug ?: null)
            ?? $this->resolveInstagramActorDefinition();

        $template = $outputMapping ? json_decode($outputMapping, true) : null;
        if (!is_array($template)) {
            $template = json_decode((string) ($definition['output_mapping'] ?? ''), true) ?: [];
        }

        if ($this->isInstagramCommentsActor()) {
            $this->instagram_results_type = 'posts';
            $this->instagram_results_limit = is_numeric($template['resultsLimit'] ?? null)
                ? (int) $template['resultsLimit']
                : (is_numeric($this->defaultLimit) ? (int) $this->defaultLimit : null);
            $this->instagram_include_nested_comments = (bool) ($template['includeNestedComments'] ?? false);
            $this->keyword_field_mapping = 'directUrls';
            return;
        }

        $resultsType = (string) ($template['resultsType'] ?? 'posts');
        $this->instagram_results_type = in_array($resultsType, ['posts', 'reels'], true) ? $resultsType : 'posts';
        $this->instagram_results_limit = is_numeric($template['resultsLimit'] ?? null)
            ? (int) $template['resultsLimit']
            : (is_numeric($this->defaultLimit) ? (int) $this->defaultLimit : null);
        $this->instagram_include_nested_comments = false;
        $this->keyword_field_mapping = 'hashtags';
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
            'maxPosts' => (int) $this->facebook_max_posts,
            'postTimeRange' => $this->facebook_post_time_range ?: '24h',
            'proxyConfiguration' => [
                'useApifyProxy' => (bool) $this->facebook_use_apify_proxy,
            ],
        ]);

        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    protected function buildInstagramOutputMapping(array $data): string
    {
        if ($this->isInstagramCommentsActor()) {
            $payload = [
                'directUrls' => ['{keyword}'],
                'resultsLimit' => (int) ($this->instagram_results_limit ?? $this->defaultLimit ?? 0),
                'includeNestedComments' => (bool) $this->instagram_include_nested_comments,
            ];

            return json_encode($payload, JSON_UNESCAPED_SLASHES);
        }

        $payload = [
            'hashtags' => ['{keyword}'],
            'resultsType' => in_array($this->instagram_results_type, ['posts', 'reels'], true) ? $this->instagram_results_type : 'posts',
            'resultsLimit' => (int) ($this->instagram_results_limit ?? $this->defaultLimit ?? 0),
        ];

        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    protected function tikTokActorDefinitions(): array
    {
        return array_values(array_filter(
            $this->registry()->primaryActors(),
            static fn (array $actor): bool => ($actor['platform'] ?? null) === 'TikTok'
        ));
    }

    protected function instagramActorDefinitions(): array
    {
        return array_values(array_filter(
            $this->registry()->primaryActors(),
            static fn (array $actor): bool => ($actor['platform'] ?? null) === 'Instagram'
        ));
    }

    protected function resolveInstagramActorDefinition(?string $slug = null): ?array
    {
        $definitions = $this->instagramActorDefinitions();

        if ($slug) {
            foreach ($definitions as $definition) {
                if (($definition['actor_slug'] ?? null) === $slug) {
                    return $definition;
                }
            }
        }

        foreach ($definitions as $definition) {
            if ((string) ($definition['function_type'] ?? '') === 'Search Post') {
                return $definition;
            }
        }

        return $definitions[0] ?? null;
    }

    protected function isInstagramCommentsActor(): bool
    {
        return str_contains(strtolower((string) $this->actorSlug), 'instagram-comment-scraper')
            || str_contains(strtolower((string) $this->functionType), 'comment');
    }

    protected function resolveTikTokActorDefinition(?string $slug = null): ?array
    {
        $definitions = $this->tikTokActorDefinitions();

        if ($slug) {
            foreach ($definitions as $definition) {
                if (($definition['actor_slug'] ?? null) === $slug) {
                    return $definition;
                }
            }
        }

        foreach ($definitions as $definition) {
            if ((string) ($definition['function_type'] ?? '') === 'Comment Scraper') {
                return $definition;
            }
        }

        return $definitions[0] ?? null;
    }

    protected function applyActorDefinitionDefaults(array $definition): void
    {
        $this->actorName = (string) ($definition['actor_name'] ?? $this->actorName);
        $this->actorSlug = (string) ($definition['actor_slug'] ?? $this->actorSlug);
        $this->functionType = (string) ($definition['function_type'] ?? $this->functionType);
        $this->actorStatus = (string) ($definition['status'] ?? $this->actorStatus);
        $this->defaultLimit = (string) (int) ($definition['default_limit'] ?? $this->defaultLimit ?: 0);
        $this->keyword_field_mapping = (string) ($definition['keyword_field_mapping'] ?? $this->keyword_field_mapping ?: 'hashtags');
        $this->output_mapping = (string) ($definition['output_mapping'] ?? $this->output_mapping ?: '');
        $this->build = (string) ($definition['build'] ?? $this->build ?: 'latest');
        $this->timeout_seconds = (int) ($definition['timeout_seconds'] ?? $this->timeout_seconds ?: 10000);
        $this->no_timeout = (bool) ($definition['no_timeout'] ?? $this->no_timeout ?: false);
        $this->interval_minutes = (int) ($definition['interval_minutes'] ?? $this->interval_minutes ?: 240);
        $this->memory_limit = (int) ($definition['memory_limit'] ?? $this->memory_limit ?: 0);
        $this->range_mode = (string) ($definition['range_mode'] ?? $this->range_mode ?: '7d');
        $this->priority = (int) ($definition['priority'] ?? $this->priority ?: 1);
    }

    protected function loadTikTokPayloadDefaults(?string $actorSlug = null): void
    {
        $definition = $this->resolveTikTokActorDefinition($actorSlug);

        $this->keyword_field_mapping = (string) ($definition['keyword_field_mapping'] ?? 'hashtags');
    }

    protected function buildTikTokOutputMapping(array $data, ?array $definition = null): string
    {
        $definition ??= $this->resolveTikTokActorDefinition($data['actorSlug'] ?? null);
        $keywordField = (string) ($definition['keyword_field_mapping'] ?? $this->keyword_field_mapping ?? 'hashtags');

        if ($keywordField === 'postURLs') {
            $payload = [
                'postURLs' => ['{keyword}'],
                'commentsPerPost' => (int) ($data['defaultLimit'] ?? $this->defaultLimit ?? 0),
                'proxyConfiguration' => [
                    'useApifyProxy' => true,
                ],
            ];

            return json_encode($payload, JSON_UNESCAPED_SLASHES);
        }

        $payload = [
            'customMapFunction' => '(object) => { return {...object} }',
            'endPage' => 1,
            'extendOutputFunction' => '($) => { return {} }',
            'maxItems' => (int) ($data['defaultLimit'] ?? $this->defaultLimit ?? 0),
            'hashtags' => ['{keyword}'],
        ];

        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    public function previewActorPayloadJson(): string
    {
        $resolvedPreviewLimit = (int) ($this->defaultLimit ?: 0);
        if ($resolvedPreviewLimit < 1) {
            return json_encode([
                'error' => 'Limit actor belum valid. Isi limit actor atau atur limit di paket sebelum preview payload.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        }

        $definition = null;
        $outputMapping = null;

        if ($this->platform === 'Facebook') {
            $outputMapping = $this->buildFacebookOutputMapping([
                'defaultLimit' => $resolvedPreviewLimit,
            ]);
        } elseif ($this->platform === 'Instagram') {
            $outputMapping = $this->buildInstagramOutputMapping([
                'defaultLimit' => $resolvedPreviewLimit,
            ]);
        } elseif ($this->platform === 'TikTok') {
            $definition = $this->resolveTikTokActorDefinition($this->actorSlug);
            $outputMapping = $this->buildTikTokOutputMapping([
                'actorSlug' => $this->actorSlug,
                'defaultLimit' => $resolvedPreviewLimit,
            ], $definition);
        }

        $actor = new ApifyActor([
            'platform' => $this->platform,
            'actor_slug' => $this->actorSlug,
            'keyword_field_mapping' => $this->keyword_field_mapping,
            'output_mapping' => $outputMapping,
            'default_limit' => (int) ($this->defaultLimit ?: 0),
            'default_keyword' => $this->defaultKeyword ?: null,
        ]);

        $previewKeyword = $this->defaultKeyword ?: null;
        if ($this->platform === 'TikTok' && $definition && ($definition['function_type'] ?? null) === 'Comment Scraper' && blank($previewKeyword)) {
            $previewKeyword = 'https://www.tiktok.com/@user/video/1234567890';
        }
        if ($this->platform === 'Instagram' && $this->isInstagramCommentsActor() && blank($previewKeyword)) {
            $previewKeyword = 'https://www.instagram.com/p/Cx123456789/';
        }
        if ($this->platform !== 'TikTok' && blank($previewKeyword)) {
            $previewKeyword = 'wagub kaltim';
        }

        $payload = $actor->buildInputPayload(
            $previewKeyword,
            $resolvedPreviewLimit,
            $this->dateFrom,
            $this->dateTo
        );

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
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
