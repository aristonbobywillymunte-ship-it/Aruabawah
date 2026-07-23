<?php

namespace App\Livewire\Admin;

use App\Models\NewsSource;
use App\Models\AiPromptTemplate;
use App\Services\AiProviderClient;
use App\Services\NewsSourceIconResolver;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NewsSources extends Component
{
    use WithPagination;

    // Search and filter
    public string $search = '';

    // Form fields
    public ?int $selected_id = null;
    public string $name = '';
    public string $domain = '';
    public ?string $base_url = '';
    public ?string $feed_url = '';
    public ?string $search_url = '';
    public ?string $sitemap_url = '';
    public ?string $search_result_selector = '';
    public ?string $article_link_selector = '';
    public ?string $article_content_selector = '';
    public ?string $article_author_selector = '';
    public ?string $article_date_selector = '';
    public bool $is_search_enabled = false;
    public bool $is_feed_enabled = false;
    public bool $is_sitemap_enabled = false;
    public string $crawling_type = 'html'; // html, rss, api
    public ?string $selector = '';
    public ?string $article_noise_selector = '';
    public ?int $timeout_seconds = null;
    public ?string $notes = '';
    public bool $is_active = true;

    // UI state
    public bool $showFormModal = false;
    public bool $isEditing = false;
    public int $formVersion = 0;
    public bool $confirmingDelete = false;
    public ?string $flashMessage = null;
    public ?string $flashType = null;

    // AI Suggestion variables
    public bool $showTestModal = false;
    public ?int $selectedSuggestionId = null;
    public ?array $testResult = null;
    public ?string $testStatus = null;
    public ?int $testingSuggestionId = null;
    public ?int $testingSourceId = null;
    public ?NewsSource $testingSource = null;
    public ?string $testingSearchUrl = null;
    public bool $showSuggestInputModal = false;
    public bool $isViewingResultOnly = false;
    public string $testKeyword = 'politik';
    public string $manualHtmlInput = '';
    public ?int $suggestInputSourceId = null;
    public ?string $suggestInputSourceLabel = null;

    protected function adminOnly(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Akses ditolak.');
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('news_sources', 'domain')
                    ->ignore($this->selected_id)
                    ->whereNull('deleted_at'),
            ],
            'base_url' => ['nullable', 'url'],
            'feed_url' => ['nullable', 'url'],
            'search_url' => ['required', 'string', 'max:500'],
            'sitemap_url' => ['nullable', 'url'],
            'search_result_selector' => ['nullable', 'string', 'max:255'],
            'article_link_selector' => ['nullable', 'string', 'max:255'],
            'article_content_selector' => ['nullable', 'string', 'max:255'],
            'article_author_selector' => ['nullable', 'string', 'max:255'],
            'article_date_selector' => ['nullable', 'string', 'max:255'],
            'article_noise_selector' => ['nullable', 'string', 'max:255'],
            'is_search_enabled' => ['boolean'],
            'is_feed_enabled' => ['boolean'],
            'is_sitemap_enabled' => ['boolean'],
            'crawling_type' => ['required', 'string', 'in:html,rss,api'],
            'selector' => ['nullable', 'string', 'max:255'],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:300'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Nama portal wajib diisi.',
            'name.string' => 'Nama portal harus berupa teks.',
            'name.max' => 'Nama portal maksimal 255 karakter.',
            'domain.required' => 'Domain portal wajib diisi.',
            'domain.string' => 'Domain portal harus berupa teks.',
            'domain.max' => 'Domain portal maksimal 255 karakter.',
            'domain.unique' => 'Domain portal sudah digunakan.',
            'base_url.url' => 'Base URL harus berupa URL yang valid.',
            'feed_url.url' => 'Feed URL harus berupa URL yang valid.',
            'search_url.string' => 'Search URL Template harus berupa teks.',
            'search_url.required' => 'Search URL Template wajib diisi untuk portal manual.',
            'search_url.max' => 'Search URL Template maksimal 500 karakter.',
            'sitemap_url.url' => 'Sitemap URL harus berupa URL yang valid.',
            'search_result_selector.string' => 'Search Result Selector harus berupa teks.',
            'search_result_selector.max' => 'Search Result Selector maksimal 255 karakter.',
            'article_link_selector.string' => 'Article Link Selector harus berupa teks.',
            'article_link_selector.max' => 'Article Link Selector maksimal 255 karakter.',
            'article_content_selector.string' => 'Article Content Selector harus berupa teks.',
            'article_content_selector.max' => 'Article Content Selector maksimal 255 karakter.',
            'article_author_selector.string' => 'Article Author Selector harus berupa teks.',
            'article_author_selector.max' => 'Article Author Selector maksimal 255 karakter.',
            'article_date_selector.string' => 'Article Date Selector harus berupa teks.',
            'article_date_selector.max' => 'Article Date Selector maksimal 255 karakter.',
            'article_noise_selector.string' => 'Article Noise Selector harus berupa teks.',
            'article_noise_selector.max' => 'Article Noise Selector maksimal 255 karakter.',
            'crawling_type.required' => 'Tipe crawling wajib dipilih.',
            'crawling_type.in' => 'Tipe crawling tidak valid.',
            'selector.string' => 'Selector Legacy harus berupa teks.',
            'selector.max' => 'Selector Legacy maksimal 255 karakter.',
            'timeout_seconds.integer' => 'Timeout harus berupa angka.',
            'timeout_seconds.min' => 'Timeout minimal 1 detik.',
            'timeout_seconds.max' => 'Timeout maksimal 300 detik.',
            'notes.string' => 'Catatan harus berupa teks.',
            'notes.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }

    protected function syncIconUrl(array $data, ?NewsSource $existing = null): array
    {
        $resolver = app(NewsSourceIconResolver::class);
        $baseUrl = $data['base_url'] ?? $existing?->base_url;
        $domain = $data['domain'] ?? $existing?->domain;
        $name = $data['name'] ?? $existing?->name;

        $resolved = $resolver->resolve($baseUrl, $domain, $name);
        if ($resolved) {
            $data['icon_url'] = $resolved;
        } elseif (empty($data['icon_url']) && $existing?->icon_url) {
            $data['icon_url'] = $existing->icon_url;
        } else {
            $data['icon_url'] = $data['icon_url'] ?? null;
        }

        return $data;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->adminOnly();

        $sources = NewsSource::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('domain', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name')
            ->paginate(10, ['*'], 'sourcesPage');

        $suggestionSourceIds = $this->getSuggestionSourceIds();
        $suggestionDomains = $this->getSuggestionDomains();

        return view('livewire.admin.news-sources', [
            'sources' => $sources,
            'suggestionSourceIds' => $suggestionSourceIds,
            'suggestionDomains' => $suggestionDomains,
        ]);
    }

    public function resetForm(): void
    {
        $this->selected_id = null;
        $this->name = '';
        $this->domain = '';
        $this->base_url = '';
        $this->feed_url = '';
        $this->search_url = '';
        $this->sitemap_url = '';
        $this->search_result_selector = '';
        $this->article_link_selector = '';
        $this->article_content_selector = '';
        $this->article_author_selector = '';
        $this->article_date_selector = '';
        $this->article_noise_selector = '';
        $this->is_search_enabled = false;
        $this->is_feed_enabled = false;
        $this->is_sitemap_enabled = false;
        $this->crawling_type = 'html';
        $this->selector = '';
        $this->timeout_seconds = null;
        $this->notes = '';
        $this->is_active = true;
        $this->isEditing = false;
        $this->manualHtmlInput = '';
        $this->suggestInputSourceId = null;
        $this->suggestInputSourceLabel = null;
        $this->resetErrorBag();
    }

    public function create(): void
    {
        $this->adminOnly();
        $this->resetForm();
        $this->formVersion++;
        $this->showFormModal = true;
    }

    public function edit(int $id): void
    {
        $this->adminOnly();
        $this->resetForm();

        $source = NewsSource::findOrFail($id);
        $this->selected_id = $source->id;
        $this->name = $source->name;
        $this->domain = $source->domain;
        $this->base_url = $source->base_url;
        $this->feed_url = $source->feed_url;
        $this->search_url = $source->search_url;
        $this->sitemap_url = $source->sitemap_url;
        $this->search_result_selector = $source->search_result_selector;
        $this->article_link_selector = $source->article_link_selector;
        $this->article_content_selector = $source->article_content_selector;
        $this->article_author_selector = $source->article_author_selector;
        $this->article_date_selector = $source->article_date_selector;
        $this->article_noise_selector = $source->article_noise_selector;
        $this->is_search_enabled = (bool) $source->is_search_enabled;
        $this->is_feed_enabled = (bool) $source->is_feed_enabled;
        $this->is_sitemap_enabled = (bool) $source->is_sitemap_enabled;
        $this->crawling_type = $source->crawling_type;
        $this->selector = $source->selector;
        $this->timeout_seconds = $source->timeout_seconds;
        $this->notes = $source->notes;
        $this->is_active = $source->is_active;

        $this->isEditing = true;
        $this->formVersion++;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->adminOnly();
        $validated = $this->validate();

        $data = [
            'name' => $this->name,
            'domain' => $this->domain,
            'base_url' => $this->base_url ?: null,
            'feed_url' => $this->feed_url ?: null,
            'search_url' => $this->search_url ?: null,
            'sitemap_url' => $this->sitemap_url ?: null,
            'search_result_selector' => $this->search_result_selector ?: null,
            'article_link_selector' => $this->article_link_selector ?: null,
            'article_content_selector' => $validated['article_content_selector'],
            'article_author_selector' => $validated['article_author_selector'],
            'article_date_selector' => $validated['article_date_selector'],
            'article_noise_selector' => $validated['article_noise_selector'],
            'is_search_enabled' => $this->is_search_enabled,
            'is_feed_enabled' => $this->is_feed_enabled,
            'is_sitemap_enabled' => $this->is_sitemap_enabled,
            'crawling_type' => $this->crawling_type,
            'selector' => $this->selector ?: null,
            'timeout_seconds' => $this->timeout_seconds ?: null,
            'notes' => $this->notes ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->isEditing) {
            $source = NewsSource::findOrFail($this->selected_id);
            $data = $this->syncIconUrl($data, $source);
            $source->update($data);
            $this->notify('success', 'Portal berita berhasil diperbarui.');
        } else {
            $data = $this->syncIconUrl($data);
            $source = NewsSource::create($data);
            $this->ensureDraftSuggestionForSource($source);
            $this->notify('success', 'Portal berita baru berhasil ditambahkan.');
        }

        $this->flushSuggestionUiCache();
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function toggleStatus(int $id): void
    {
        $this->adminOnly();
        $source = NewsSource::findOrFail($id);
        $source->is_active = !$source->is_active;
        $source->save();

        $this->notify('success', 'Status portal berita berhasil diperbarui.');
    }

    public function requestDelete(int $id): void
    {
        $this->adminOnly();
        $this->selected_id = $id;
        $this->confirmingDelete = true;
    }

    public function deleteConfirmed(): void
    {
        $this->adminOnly();
        if ($this->selected_id) {
            $source = NewsSource::findOrFail($this->selected_id);
            $source->delete();
            $this->notify('success', 'Portal berita berhasil dihapus.');
        }
        $this->flushSuggestionUiCache();
        $this->confirmingDelete = false;
        $this->resetForm();
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
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

        $this->dispatch('admin-toast', payload: $payload);
    }

    // AI Suggestion & Testing Methods
    public function openSuggestInput(?int $sourceId = null): void
    {
        $this->suggestInputSourceId = $sourceId;
        $this->manualHtmlInput = '';
        $this->suggestInputSourceLabel = null;
        if ($sourceId) {
            $source = NewsSource::find($sourceId);
            $this->suggestInputSourceLabel = $source?->name ?: $source?->domain ?: 'Portal';
        }
        $this->showSuggestInputModal = true;
    }

    public function generateSuggestionForNew(): void
    {
        $this->adminOnly();
        $this->validate([
            'manualHtmlInput' => ['required', 'string', 'max:200000'],
        ]);

        $htmlInput = trim($this->manualHtmlInput);
        $context = $this->extractPortalContextFromHtml($htmlInput);
        $name = $context['name'] ?? '';
        $domain = $context['domain'] ?? '';
        $suggestion = $this->generateSuggestionLogic($name, $domain, null, $htmlInput);
        $this->showSuggestInputModal = false;
        $this->suggestInputSourceId = null;
        $this->suggestInputSourceLabel = null;

        if ($suggestion) {
            $this->applySuggestionToForm($suggestion);
        }
    }

    public function generateSuggestionForExisting(int $id): void
    {
        $this->adminOnly();
        $source = NewsSource::findOrFail($id);
        $htmlInput = trim($this->manualHtmlInput);
        if ($htmlInput === '') {
            $this->notify('error', 'HTML mentah wajib diisi untuk saran AI portal yang sedang diedit.');
            $this->showSuggestInputModal = true;
            $this->suggestInputSourceId = $source->id;
            return;
        }

        $this->generateSuggestionLogic($source->name, $source->domain, $source->id, $htmlInput);
        $this->showSuggestInputModal = false;
        $this->suggestInputSourceId = null;
        $this->suggestInputSourceLabel = null;
    }

    private function generateSuggestionLogic(string $name, string $domain, ?int $sourceId, ?string $htmlInput = null): ?\App\Models\NewsSourceSuggestion
    {
        $normalizedDomain = $this->normalizeDomain($domain);
        $duplicateDomain = $normalizedDomain !== '' ? $normalizedDomain : trim($domain);

        if ($duplicateDomain !== '' && $this->hasActiveDuplicateSuggestion($duplicateDomain, $sourceId)) {
            $existingSuggestion = \App\Models\NewsSourceSuggestion::query()
                ->when($sourceId, fn ($query) => $query->where(function ($q) use ($sourceId) {
                    $q->whereNull('news_source_id')
                        ->orWhere('news_source_id', $sourceId);
                }))
                ->whereRaw('LOWER(TRIM(domain)) = ?', [$duplicateDomain])
                ->whereNotIn('status', ['rejected', 'failed'])
                ->orderByDesc('id')
                ->first();

            if ($existingSuggestion) {
                $this->notify('info', 'Saran tersedia, form akan diisi otomatis.');
                return $existingSuggestion;
            }

            $this->notify('info', 'Saran tersedia.');
            return null;
        }

        $provider = \App\Models\AiProvider::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('id', 'asc')
            ->first();

        if (!$provider) {
            $this->notify('error', 'AI Provider belum siap.');
            return null;
        }

        try {
            $template = AiPromptTemplate::resolveActiveDefaultForSourceType('Saran Portal Manual', 'article');

            if (! $template || blank($template->system_prompt) || blank($template->user_prompt_template)) {
                $this->notify('error', 'Template Saran Portal Manual belum tersedia atau belum lengkap di AI Prompt Templates.');
                return null;
            }

            $systemPrompt = $template->system_prompt;
            $userPrompt = trim($template->user_prompt_template);
            $renderedUserPrompt = strtr($userPrompt, [
                '{name}' => $name,
                '{domain}' => $domain,
                '{html}' => $htmlInput ?: '',
                '{article_url}' => $htmlInput ?: '',
                '{url}' => $htmlInput ?: '',
            ]);

            $schemaPrompt = trim((string) ($template->output_schema ?? ''));
            if ($schemaPrompt !== '') {
                $renderedUserPrompt .= "\n\nWAJIB IKUTI SCHEMA OUTPUT INI TANPA MENAMBAH KEY LAIN:\n" . $schemaPrompt;
            }

            $client = app(AiProviderClient::class);
            $requestOptions = ['temperature' => 0.0];
            if (!str_contains(strtolower($provider->provider_type ?? ''), 'gemini') && !str_contains(strtolower($provider->name ?? ''), 'gemini')) {
                $requestOptions['response_format'] = 'json_object';
            }

            $response = $client->sendRequest($provider, trim($systemPrompt), $renderedUserPrompt, $requestOptions);

            if (!$response->successful()) {
                throw new \RuntimeException(
                    'AI provider mengembalikan HTTP ' . $response->status() . ': ' . $response->body()
                );
            }

            $rawText = $client->parseResponse($provider, $response);
            if (blank($rawText)) {
                throw new \RuntimeException(
                    'Response dari AI kosong atau format respons tidak dikenali. HTTP ' .
                    $response->status() . ': ' . $response->body()
                );
            }

            $result = $this->decodeSuggestionJson($rawText);
            if (!$result) {
                throw new \RuntimeException('Gagal mengurai JSON dari respons AI. Respons mentah: ' . mb_strimwidth($rawText, 0, 500, '...'));
            }

            $validationWarnings = $this->validateSuggestionResult($result, $domain);

            $suggestion = \App\Models\NewsSourceSuggestion::create([
                'news_source_id' => $sourceId,
                'suggested_by' => 'ai',
                'source_name' => $name,
                'domain' => $domain,
                'base_url' => $result['base_url'] ?? ('https://' . $domain),
                'crawling_type' => $this->normalizeSuggestionCrawlingType($result['crawling_type'] ?? null),
                'search_url' => $this->normalizeSuggestionSearchUrl($result),
                'feed_url' => $result['feed_url'] ?? null,
                'sitemap_url' => $result['sitemap_url'] ?? null,
                'search_result_selector' => $result['search_result_selector'] ?? null,
                'article_link_selector' => $result['article_link_selector'] ?? null,
                'article_content_selector' => $result['article_content_selector'] ?? null,
                'article_noise_selector' => $result['article_noise_selector'] ?? null,
                'article_author_selector' => $result['article_author_selector'] ?? null,
                'article_date_selector' => $result['article_date_selector'] ?? null,
                'confidence' => $this->normalizeSuggestionConfidence($result, $validationWarnings),
                'ai_reason' => $this->mergeSuggestionReason(
                    (string) ($result['ai_reason'] ?? ''),
                    $validationWarnings
                ),
                'status' => 'draft_ai',
            ]);

            $this->notify('success', 'Saran AI berhasil dibuat.');
            return $suggestion;
        } catch (\Throwable $e) {
            $this->notify('error', 'Gagal memanggil AI: ' . $e->getMessage());
            return null;
        }
    }

    private function applySuggestionToForm(\App\Models\NewsSourceSuggestion $suggestion): void
    {
        $this->resetForm();
        $this->selected_id = null;
        $this->name = $suggestion->source_name ?: $suggestion->domain;
        $this->domain = $suggestion->domain ?: '';
        $this->base_url = $suggestion->base_url ?: '';
        $this->crawling_type = $suggestion->crawling_type ?: 'html';
        $this->search_url = $suggestion->search_url ?: '';
        $this->search_result_selector = $suggestion->search_result_selector ?: '';
        $this->article_link_selector = $suggestion->article_link_selector ?: '';
        $this->article_content_selector = $suggestion->article_content_selector ?: '';
        $this->article_author_selector = $suggestion->article_author_selector ?: '';
        $this->article_date_selector = $suggestion->article_date_selector ?: '';
        $this->article_noise_selector = $suggestion->article_noise_selector ?: '';
        $this->formVersion++;
        $this->showFormModal = false;
        $this->showFormModal = true;
        $this->isEditing = false;
    }

    private function validateSuggestionResult(array $result, string $domain): array
    {
        $warnings = [];
        $searchUrl = trim((string) ($result['search_url'] ?? ($result['search_url_template'] ?? '')));
        $baseUrl = trim((string) ($result['base_url'] ?? ''));
        $normalizedDomain = $this->normalizeDomain($domain);

        if ($searchUrl === '') {
            $warnings[] = 'AI tidak mengembalikan search_url, jadi hasil dianggap belum valid penuh untuk portal manual.';
            return $warnings;
        }

        $searchUrlLower = strtolower($searchUrl);
        if (str_contains($searchUrlLower, 'google.com') || str_contains($searchUrlLower, 'news.google')) {
            $warnings[] = 'AI mengembalikan search_url Google News atau Google, bukan search internal portal manual.';
        }

        if (! str_contains($searchUrlLower, $normalizedDomain)) {
            $searchHost = $this->normalizeDomain((string) parse_url($searchUrl, PHP_URL_HOST));
            if ($searchHost !== $normalizedDomain) {
                $warnings[] = 'AI search_url tidak sesuai domain target.';
            }
        }

        if ($baseUrl !== '') {
            $baseHost = $this->normalizeDomain((string) parse_url($baseUrl, PHP_URL_HOST));
            if ($baseHost !== '' && $baseHost !== $normalizedDomain) {
                $warnings[] = 'AI base_url tidak sesuai domain target.';
            }
        }

        if (! filled($result['article_link_selector'] ?? null) || ! filled($result['article_content_selector'] ?? null)) {
            $warnings[] = 'AI belum mengembalikan selector artikel minimum.';
        }

        return $warnings;
    }

    private function normalizeSuggestionSearchUrl(array $result): ?string
    {
        $searchUrl = trim((string) ($result['search_url'] ?? ($result['search_url_template'] ?? '')));
        return $searchUrl !== '' ? $searchUrl : null;
    }

    private function normalizeSuggestionConfidence(array $result, array $warnings): float
    {
        $confidence = (float) ($result['confidence'] ?? 0.35);

        if (count($warnings) > 0) {
            $confidence = min($confidence, 0.35);
        }

        if ($confidence <= 0) {
            $confidence = 0.2;
        }

        return round($confidence, 2);
    }

    private function normalizeSuggestionCrawlingType(mixed $value): string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['html', 'rss', 'api'], true) ? $value : 'html';
    }

    private function decodeSuggestionJson(string $rawText): ?array
    {
        $trimmed = trim(html_entity_decode($rawText, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($trimmed === '') {
            return null;
        }

        $direct = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($direct)) {
            return $direct;
        }

        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $trimmed, $matches)) {
            $fenced = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($fenced)) {
                return $fenced;
            }
        }

        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $trimmed, $matches)) {
            $nested = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($nested)) {
                return $nested;
            }
        }

        return null;
    }

    private function mergeSuggestionReason(string $reason, array $warnings): string
    {
        $parts = [];
        $reason = trim($reason);

        if ($reason !== '') {
            $parts[] = $reason;
        }

        foreach ($warnings as $warning) {
            $warning = trim((string) $warning);
            if ($warning !== '') {
                $parts[] = $warning;
            }
        }

        return implode(' ', array_unique($parts));
    }

    public function testSuggestion(int $id): void
    {
        $this->adminOnly();
        $this->isViewingResultOnly = false;
        $suggestion = \App\Models\NewsSourceSuggestion::findOrFail($id);
        $this->testingSuggestionId = $id;
        $this->testingSourceId = null;
        $this->testingSource = null;

        $suggestion->status = 'testing';
        $suggestion->save();

        try {
            $result = \App\Services\NewsSourceSuggestionTester::test($suggestion, $this->testKeyword);

            $nextStatus = ($result['status'] === 'verified')
                ? 'lolos'
                : ($result['status'] === 'failed' ? 'failed' : $result['status']);

            $suggestion->status = $nextStatus;
            $suggestion->test_result_json = $result;
            $suggestion->save();

            $this->selectedSuggestionId = $suggestion->id;
            $this->testResult = $result;
            $this->testStatus = $nextStatus;
            $this->showTestModal = true;

            $flashType = $nextStatus === 'lolos' ? 'success' : 'failed';
            $this->notify($flashType, 'Pengujian selesai dengan status: ' . strtoupper($nextStatus));
        } finally {
            $this->testingSuggestionId = null;
        }
    }

    public function testManualUrl(int $id): void
    {
        $this->adminOnly();
        $this->isViewingResultOnly = false;
        $suggestion = \App\Models\NewsSourceSuggestion::findOrFail($id);
        $this->testingSuggestionId = $id;
        $this->testingSourceId = null;
        $this->testingSource = null;

        $suggestion->status = 'testing';
        $suggestion->save();

        try {
            $result = \App\Services\NewsSourceSuggestionTester::testManualUrl($suggestion, $this->manualHtmlInput);

            $nextStatus = ($result['status'] === 'verified')
                ? 'lolos'
                : ($result['status'] === 'failed' ? 'failed' : $result['status']);

            $suggestion->status = $nextStatus;
            $suggestion->test_result_json = $result;
            $suggestion->save();

            $this->selectedSuggestionId = $suggestion->id;
            $this->testResult = $result;
            $this->testStatus = $nextStatus;
            $this->showTestModal = true;

            $flashType = $nextStatus === 'lolos' ? 'success' : 'failed';
            $this->notify($flashType, 'Pengujian manual URL selesai dengan status: ' . strtoupper($nextStatus));
        } finally {
            $this->testingSuggestionId = null;
        }
    }

    public function showTestResult(int $id): void
    {
        $this->adminOnly();
        $this->isViewingResultOnly = true;
        $suggestion = \App\Models\NewsSourceSuggestion::findOrFail($id);
        $this->selectedSuggestionId = $suggestion->id;
        $this->testingSourceId = null;
        $this->testingSource = null;
        $this->testingSearchUrl = null;
        $this->testResult = $suggestion->test_result_json;
        $this->testStatus = $suggestion->status;
        $this->testKeyword = (string) (data_get($suggestion->test_result_json, 'keyword') ?: 'politik');
        $this->manualHtmlInput = '';

        $this->showTestModal = true;
    }

    public function viewTestResult(int $id): void
    {
        $this->showTestResult($id);
    }

    public function testSource(int $id): void
    {
        $this->adminOnly();
        $this->isViewingResultOnly = false;
        $source = NewsSource::findOrFail($id);
        $this->testingSourceId = $source->id;
        $this->testingSource = $source;
        $this->testingSearchUrl = $this->renderSearchUrlTemplate((string) ($source->search_url ?: ''), $this->testKeyword);
        $this->selectedSuggestionId = null;
        $this->testingSuggestionId = null;

        $suggestion = new \App\Models\NewsSourceSuggestion();
        $suggestion->news_source_id = $source->id;
        $suggestion->suggested_by = 'ai';
        $suggestion->source_name = $source->name;
        $suggestion->domain = $source->domain;
        $suggestion->base_url = $source->base_url;
        $suggestion->search_url = $source->search_url;
        $suggestion->feed_url = $source->feed_url;
        $suggestion->sitemap_url = $source->sitemap_url;
        $suggestion->search_result_selector = $source->search_result_selector;
        $suggestion->article_link_selector = $source->article_link_selector;
        $suggestion->article_content_selector = $source->article_content_selector;
        $suggestion->article_author_selector = $source->article_author_selector;
        $suggestion->article_date_selector = $source->article_date_selector;
        $suggestion->article_noise_selector = $source->article_noise_selector;
        $suggestion->crawling_type = $source->crawling_type;

        $this->testingSuggestionId = $source->id;
        $this->testResult = \App\Services\NewsSourceSuggestionTester::test($suggestion, $this->testKeyword);
        $this->testStatus = data_get($this->testResult, 'status', 'failed');
        $this->showTestModal = true;
    }

    public function updatedManualHtmlInput(): void
    {
        $this->manualHtmlInput = trim($this->manualHtmlInput);
    }

    public function approveSuggestion(int $id): void
    {
        $this->adminOnly();
        $suggestion = \App\Models\NewsSourceSuggestion::findOrFail($id);
        $testMode = data_get($suggestion->test_result_json, 'mode', 'discovery');
        $normalizedDomain = $this->normalizeDomain((string) $suggestion->domain);

        if (!in_array($suggestion->status, ['lolos', 'verified'], true)) {
            $this->notify('error', 'Hanya saran yang lolos uji yang dapat diverifikasi.');
            return;
        }

        if ($normalizedDomain === '') {
            $this->notify('error', 'Domain portal tidak valid.');
            return;
        }

        $data = [
            'name' => $suggestion->source_name ?: ($suggestion->newsSource?->name ?: $suggestion->domain),
            'domain' => $suggestion->domain ?: $suggestion->newsSource?->domain,
            'base_url' => $suggestion->base_url ?: $suggestion->newsSource?->base_url,
            'article_content_selector' => $suggestion->article_content_selector ?: $suggestion->newsSource?->article_content_selector,
            'article_author_selector' => $suggestion->article_author_selector ?: $suggestion->newsSource?->article_author_selector,
            'article_date_selector' => $suggestion->article_date_selector ?: $suggestion->newsSource?->article_date_selector,
            'article_noise_selector' => $suggestion->article_noise_selector ?: $suggestion->newsSource?->article_noise_selector,
        ];
        $data = $this->syncIconUrl($data, $suggestion->newsSource);

        $data['feed_url'] = $suggestion->feed_url ?: $suggestion->newsSource?->feed_url;
        $data['search_url'] = $suggestion->search_url ?: $suggestion->newsSource?->search_url;
        $data['sitemap_url'] = $suggestion->sitemap_url ?: $suggestion->newsSource?->sitemap_url;
        $data['search_result_selector'] = $suggestion->search_result_selector ?: $suggestion->newsSource?->search_result_selector;
        $data['article_link_selector'] = $suggestion->article_link_selector ?: $suggestion->newsSource?->article_link_selector;

        if ($suggestion->news_source_id) {
            // Gunakan withTrashed() agar tidak throw ModelNotFoundException
            // jika source sudah soft-deleted sebelum suggestion di-approve.
            $source = NewsSource::withTrashed()->find($suggestion->news_source_id);

            if (! $source) {
                // Source benar-benar tidak ditemukan (data corrupt)
                $this->notify('error', 'News Source terkait tidak ditemukan. Hubungi administrator.');
                return;
            }

            if ($source->trashed()) {
                // Source sudah dihapus — jangan approve, jangan restore otomatis.
                $this->notify('error', 'News Source terkait sudah dihapus. Pulihkan source atau buat source baru sebelum approve.');
                return;
            }

            if ($this->hasExistingActiveSource($normalizedDomain, $source->id)) {
                $this->notify('info', 'Saran tersedia.');
                return;
            }

            $source->update($data);
        } else {
            if ($this->hasExistingActiveSource($normalizedDomain, null)) {
                $this->notify('info', 'Saran tersedia.');
                return;
            }

            $source = NewsSource::create(array_merge($data, [
                'crawling_type' => 'html',
                'is_active' => true,
                'is_search_enabled' => $testMode !== 'manual_url' ? false : false,
                'is_feed_enabled' => false,
                'is_sitemap_enabled' => false,
            ]));
            $suggestion->news_source_id = $source->id;
        }

        $suggestion->status = 'verified';
        $suggestion->approved_by = auth()->id();
        $suggestion->approved_at = now();
        $suggestion->save();
        $this->flushSuggestionUiCache();

        $this->showTestModal = false;
        $this->notify('success', 'Saran berhasil diverifikasi dan diterapkan ke News Sources.');
    }

    public function saveAsDraft(int $id): void
    {
        $this->adminOnly();
        $suggestion = \App\Models\NewsSourceSuggestion::findOrFail($id);

        $suggestion->status = 'needs_review';
        $suggestion->save();
        $this->flushSuggestionUiCache();

        $this->showTestModal = false;
        $this->notify('success', 'Saran disimpan sebagai draf review.');
    }

    public function rejectSuggestion(int $id): void
    {
        $this->adminOnly();
        $suggestion = \App\Models\NewsSourceSuggestion::findOrFail($id);
        $suggestion->status = 'rejected';
        $suggestion->save();
        $this->flushSuggestionUiCache();

        $this->showTestModal = false;
        $this->notify('success', 'Saran ditolak.');
    }

    public function deleteSuggestion(int $id): void
    {
        $this->adminOnly();
        $suggestion = \App\Models\NewsSourceSuggestion::findOrFail($id);
        $suggestion->delete();
        $this->flushSuggestionUiCache();

        if ($this->selectedSuggestionId === $id) {
            $this->selectedSuggestionId = null;
            $this->testResult = null;
            $this->testStatus = null;
            $this->showTestModal = false;
            $this->manualHtmlInput = '';
        }

        $this->notify('success', 'Saran berhasil dihapus.');
    }

    public function cancelTesting(): void
    {
        $this->testingSuggestionId = null;
        $this->testingSearchUrl = null;
    }

    private function renderSearchUrlTemplate(string $template, string $keyword): string
    {
        $keyword = trim($keyword) !== '' ? trim($keyword) : 'politik';

        return str_replace(
            ['{keyword}', '{query}', '{search}'],
            rawurlencode($keyword),
            $template
        );
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = trim(strtolower($domain));
        $domain = preg_replace('~^https?://~', '', $domain) ?? $domain;
        $domain = preg_replace('~^www\.~', '', $domain) ?? $domain;
        $domain = preg_replace('~/.*$~', '', $domain) ?? $domain;

        return trim($domain);
    }

    private function extractPortalContextFromHtml(string $html): array
    {
        $name = '';
        $domain = '';

        if ($html === '') {
            return compact('name', 'domain');
        }

        if (preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            $name = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if ($name === '' && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title !== '') {
                $parts = preg_split('/\s*[\|\-–]\s*/u', $title);
                $name = trim((string) ($parts[0] ?? $title));
            }
        }

        $domain = $this->extractDominantLinkDomain($html) ?: $domain;

        if ($domain === '' && preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $matches)) {
            $domain = $this->normalizeDomain((string) parse_url(trim($matches[1]), PHP_URL_HOST));
        }

        if ($domain === '' && preg_match('/<meta[^>]+property=["\']og:url["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            $domain = $this->normalizeDomain((string) parse_url(trim($matches[1]), PHP_URL_HOST));
        }

        if ($domain === '' && preg_match('/<base[^>]+href=["\']([^"\']+)["\']/i', $html, $matches)) {
            $domain = $this->normalizeDomain((string) parse_url(trim($matches[1]), PHP_URL_HOST));
        }

        return compact('name', 'domain');
    }

    private function extractDominantLinkDomain(string $html): string
    {
        $matches = [];
        preg_match_all('/<a\b[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
        $hrefs = $matches[1] ?? [];

        if (empty($hrefs)) {
            return '';
        }

        $counts = [];
        foreach ($hrefs as $href) {
            $href = trim(html_entity_decode((string) $href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'javascript:')) {
                continue;
            }

            $host = '';
            if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
                $host = (string) parse_url($href, PHP_URL_HOST);
            } elseif (str_starts_with($href, '//')) {
                $host = (string) parse_url('https:' . $href, PHP_URL_HOST);
            }

            $host = $this->normalizeDomain($host);
            if ($host === '') {
                continue;
            }

            $counts[$host] = ($counts[$host] ?? 0) + 1;
        }

        if (empty($counts)) {
            return '';
        }

        arsort($counts);
        return (string) array_key_first($counts);
    }

    private function hasExistingActiveSource(string $domain, ?int $ignoreSourceId = null): bool
    {
        return NewsSource::query()
            ->when($ignoreSourceId, fn ($query) => $query->where('id', '!=', $ignoreSourceId))
            ->whereRaw('LOWER(TRIM(domain)) = ?', [$domain])
            ->exists();
    }

    private function hasActiveDuplicateSuggestion(string $domain, ?int $ignoreSourceId = null): bool
    {
        return \App\Models\NewsSourceSuggestion::query()
            ->when($ignoreSourceId, fn ($query) => $query->where(function ($q) use ($ignoreSourceId) {
                $q->whereNull('news_source_id')
                  ->orWhere('news_source_id', '!=', $ignoreSourceId);
            }))
            ->whereRaw('LOWER(TRIM(domain)) = ?', [$domain])
            ->whereNotIn('status', ['rejected', 'failed'])
            ->exists();
    }

    private function isDuplicateSuggestion(\App\Models\NewsSourceSuggestion $suggestion): bool
    {
        $domain = $this->normalizeDomain((string) $suggestion->domain);

        if ($domain === '') {
            return true;
        }

        if (in_array($suggestion->status, ['verified', 'approved', 'lolos'], true)) {
            return false;
        }

        if ($suggestion->newsSource && $this->hasExistingActiveSource($domain, $suggestion->newsSource->id)) {
            return true;
        }

        $activeDuplicateExists = \App\Models\NewsSourceSuggestion::query()
            ->where('id', '!=', $suggestion->id)
            ->whereRaw('LOWER(TRIM(domain)) = ?', [$domain])
            ->whereNotIn('status', ['rejected', 'failed'])
            ->exists();

        return $activeDuplicateExists;
    }

    private function ensureDraftSuggestionForSource(NewsSource $source): void
    {
        $domain = $this->normalizeDomain((string) $source->domain);
        if ($domain === '') {
            return;
        }

        $alreadyExists = \App\Models\NewsSourceSuggestion::query()
            ->where('news_source_id', $source->id)
            ->exists();

        if ($alreadyExists) {
            $this->notify('info', 'Sudah ada, tidak perlu dibuat ulang.');
            return;
        }

        \App\Models\NewsSourceSuggestion::create([
            'news_source_id' => $source->id,
            'suggested_by' => 'system',
            'source_name' => $source->name,
            'domain' => $source->domain,
            'base_url' => $source->base_url,
            'search_url' => $source->search_url,
            'feed_url' => $source->feed_url,
            'sitemap_url' => $source->sitemap_url,
            'search_result_selector' => $source->search_result_selector,
            'article_link_selector' => $source->article_link_selector,
            'article_content_selector' => $source->article_content_selector,
            'article_author_selector' => $source->article_author_selector,
            'article_date_selector' => $source->article_date_selector,
            'article_noise_selector' => $source->article_noise_selector,
            'confidence' => 0.5,
            'ai_reason' => 'Otomatis dibuat saat portal baru ditambahkan.',
            'status' => 'draft_ai',
        ]);

        $this->flushSuggestionUiCache();
    }

    private function getSuggestionSourceIds(): array
    {
        return Cache::remember('news_sources:suggestion_source_ids', now()->addMinutes(10), function () {
            return \App\Models\NewsSourceSuggestion::query()
                ->whereNotNull('news_source_id')
                ->distinct()
                ->pluck('news_source_id')
                ->mapWithKeys(fn ($id) => [(int) $id => true])
                ->all();
        });
    }

    private function getSuggestionDomains(): array
    {
        return Cache::remember('news_sources:suggestion_domains', now()->addMinutes(10), function () {
            return \App\Models\NewsSourceSuggestion::query()
                ->whereNotNull('domain')
                ->distinct()
                ->pluck('domain')
                ->map(fn ($domain) => $this->normalizeDomain((string) $domain))
                ->filter()
                ->mapWithKeys(fn ($domain) => [$domain => true])
                ->all();
        });
    }

    private function flushSuggestionUiCache(): void
    {
        Cache::forget('news_sources:suggestion_source_ids');
        Cache::forget('news_sources:suggestion_domains');
    }
}
