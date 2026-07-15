<?php

namespace App\Livewire\Admin;

use App\Models\NewsSource;
use App\Services\NewsSourceIconResolver;
use Livewire\Component;
use Livewire\WithPagination;
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
    public bool $confirmingDelete = false;
    public ?string $flashMessage = null;
    public ?string $flashType = null;

    // AI Suggestion variables
    public bool $showTestModal = false;
    public ?int $selectedSuggestionId = null;
    public ?array $testResult = null;
    public ?string $testStatus = null;
    public ?int $testingSuggestionId = null;
    public string $suggSourceName = '';
    public string $suggDomain = '';
    public bool $showSuggestInputModal = false;
    public bool $isViewingResultOnly = false;
    public string $testKeyword = 'politik';
    public string $manualArticleUrl = '';

    protected function adminOnly(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Akses ditolak.');
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('news_sources', 'domain')->ignore($this->selected_id)],
            'base_url' => ['nullable', 'url'],
            'feed_url' => ['nullable', 'url'],
            'search_url' => ['nullable', 'string', 'max:500'],
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

        $suggestions = \App\Models\NewsSourceSuggestion::with('newsSource')
            ->whereIn('id', function ($query) {
                $query->select(\Illuminate\Support\Facades\DB::raw('MAX(id)'))
                    ->from('news_source_suggestions')
                    ->groupBy('domain');
            })
            ->orderBy('id', 'desc')
            ->paginate(10, ['*'], 'suggestionsPage');

        $suggestionSourceIds = [];
        $suggestionDomains = [];
        \App\Models\NewsSourceSuggestion::query()
            ->select(['news_source_id', 'domain'])
            ->orderBy('id')
            ->chunk(200, function ($rows) use (&$suggestionSourceIds, &$suggestionDomains) {
                foreach ($rows as $row) {
                    if ($row->news_source_id) {
                        $suggestionSourceIds[(int) $row->news_source_id] = true;
                    }

                    $domain = $this->normalizeDomain((string) $row->domain);
                    if ($domain !== '') {
                        $suggestionDomains[$domain] = true;
                    }
                }
            });

        return view('livewire.admin.news-sources', [
            'sources' => $sources,
            'suggestions' => $suggestions,
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
        $this->manualArticleUrl = '';
        $this->resetErrorBag();
    }

    public function create(): void
    {
        $this->adminOnly();
        $this->resetForm();
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
    public function openSuggestInput(): void
    {
        $this->suggSourceName = '';
        $this->suggDomain = '';
        $this->manualArticleUrl = '';
        $this->showSuggestInputModal = true;
    }

    public function generateSuggestionForNew(): void
    {
        $this->adminOnly();
        $this->validate([
            'suggSourceName' => ['required', 'string', 'max:255'],
            'suggDomain' => ['required', 'string', 'max:255'],
        ]);

        $this->generateSuggestionLogic($this->suggSourceName, $this->suggDomain, null);
        $this->showSuggestInputModal = false;
    }

    public function generateSuggestionForExisting(int $id): void
    {
        $this->adminOnly();
        $source = NewsSource::findOrFail($id);
        $this->generateSuggestionLogic($source->name, $source->domain, $source->id);
    }

    private function generateSuggestionLogic(string $name, string $domain, ?int $sourceId): void
    {
        $normalizedDomain = $this->normalizeDomain($domain);
        if ($normalizedDomain === '') {
            $this->notify('error', 'Domain portal tidak valid.');
            return;
        }

        if ($this->hasActiveDuplicateSuggestion($normalizedDomain, $sourceId)) {
            $this->notify('info', 'Saran tersedia.');
            return;
        }

        $provider = \App\Models\AiProvider::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('id', 'asc')
            ->first();

        if (!$provider) {
            $this->notify('error', 'AI Provider belum siap.');
            return;
        }

        try {
            $prompt = "Berikan saran konfigurasi metadata scraping untuk portal berita dengan nama '{$name}' dan domain '{$domain}'. 
            Rincian yang harus Anda analisis dan sarankan:
            1. base_url (URL dasar portal berita, diawali http:// atau https://)
            2. search_url (URL pencarian kustom dengan placeholder {keyword}, contoh: https://example.com/search?q={keyword} atau search?q={keyword})
            3. feed_url (URL Feed RSS jika ada, nullable)
            4. sitemap_url (URL XML Sitemap jika ada, nullable)
            5. search_result_selector (Selector HTML/CSS untuk membungkus list hasil pencarian)
            6. article_link_selector (Selector HTML/CSS untuk mendapatkan link artikel langsung dari list pencarian)
            7. article_content_selector (Selector HTML/CSS untuk mengambil isi konten artikel penuh dari halaman artikel)
            8. article_noise_selector (Opsional. Selector HTML/CSS untuk elemen sampah di dalam konten seperti .ads, .related-post, .baca-juga, dll yang harus dibuang, pisahkan dengan koma jika lebih dari satu)
            9. article_author_selector (Opsional. Selector HTML/CSS untuk mengambil nama penulis/author artikel)
            10. article_date_selector (Opsional. Selector HTML/CSS untuk mengambil tanggal publikasi artikel)
            11. ai_reason (Alasan singkat Anda menyarankan konfigurasi ini)
            12. confidence (Tingkat kepercayaan Anda terhadap saran ini antara 0.0 sampai 1.0)

            Balas HANYA dengan format JSON valid sebagai berikut:
            {
              \"base_url\": \"...\",
              \"search_url\": \"...\",
              \"feed_url\": \"...\",
              \"sitemap_url\": \"...\",
              \"search_result_selector\": \"...\",
              \"article_link_selector\": \"...\",
              \"article_content_selector\": \"...\",
              \"article_noise_selector\": \"...\",
              \"article_author_selector\": \"...\",
              \"article_date_selector\": \"...\",
              \"ai_reason\": \"...\",
              \"confidence\": 0.85
            }";

            $rawText = '';
            if ($provider->provider_type === 'Gemini') {
                $baseUrl = rtrim((string) $provider->base_url, '/');
                $model = trim((string) $provider->model_name);
                $apiKey = trim((string) $provider->api_key);
                $endpoint = str_contains($baseUrl, '/models/') ? $baseUrl : $baseUrl . '/models/' . $model . ':generateContent';

                $response = Http::timeout(30)->post($endpoint . '?key=' . urlencode($apiKey), [
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 1000,
                    ],
                    'contents' => [[
                        'parts' => [[
                            'text' => $prompt,
                        ]],
                    ]],
                ]);
                $rawText = data_get($response->json(), 'candidates.0.content.parts.0.text');
            } else {
                $baseUrl = rtrim((string) $provider->base_url, '/');
                $apiKey = trim((string) $provider->api_key);
                $model = trim((string) $provider->model_name);
                $response = Http::withToken($apiKey)->timeout(30)->post($baseUrl . '/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'max_tokens' => 1000,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a metadata extraction expert for web scraping.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
                $rawText = data_get($response->json(), 'choices.0.message.content');
            }

            if (blank($rawText)) {
                throw new \RuntimeException('Response dari AI kosong.');
            }

            if (preg_match('/\{.*\}/s', $rawText, $matches)) {
                $rawText = $matches[0];
            }
            $result = json_decode($rawText, true);
            if (!$result) {
                throw new \RuntimeException('Gagal mengurai JSON dari respons AI.');
            }

            \App\Models\NewsSourceSuggestion::create([
                'news_source_id' => $sourceId,
                'suggested_by' => 'ai',
                'source_name' => $name,
                'domain' => $domain,
                'base_url' => $result['base_url'] ?? ('https://' . $domain),
                'search_url' => $result['search_url'] ?? null,
                'feed_url' => $result['feed_url'] ?? null,
                'sitemap_url' => $result['sitemap_url'] ?? null,
                'search_result_selector' => $result['search_result_selector'] ?? null,
                'article_link_selector' => $result['article_link_selector'] ?? null,
                'article_content_selector' => $result['article_content_selector'] ?? null,
                'article_noise_selector' => $result['article_noise_selector'] ?? null,
                'article_author_selector' => $result['article_author_selector'] ?? null,
                'article_date_selector' => $result['article_date_selector'] ?? null,
                'confidence' => $result['confidence'] ?? 0.5,
                'ai_reason' => $result['ai_reason'] ?? null,
                'status' => 'draft_ai',
            ]);

            $this->notify('success', 'Saran AI berhasil dibuat.');
        } catch (\Throwable $e) {
            $this->notify('error', 'Gagal memanggil AI: ' . $e->getMessage());
        }
    }

    public function testSuggestion(int $id): void
    {
        $this->adminOnly();
        $this->isViewingResultOnly = false;
        $suggestion = \App\Models\NewsSourceSuggestion::findOrFail($id);
        $this->testingSuggestionId = $id;

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

        $suggestion->status = 'testing';
        $suggestion->save();

        try {
            $result = \App\Services\NewsSourceSuggestionTester::testManualUrl($suggestion, $this->manualArticleUrl);

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
        $this->testResult = $suggestion->test_result_json;
        $this->testStatus = $suggestion->status;
        $this->testKeyword = (string) (data_get($suggestion->test_result_json, 'keyword') ?: 'politik');
        $this->manualArticleUrl = '';

        $this->showTestModal = true;
    }

    public function updatedManualArticleUrl(): void
    {
        $this->manualArticleUrl = trim($this->manualArticleUrl);
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

        if ($testMode !== 'manual_url') {
            $data['feed_url'] = $suggestion->feed_url ?: $suggestion->newsSource?->feed_url;
            $data['search_url'] = $suggestion->search_url ?: $suggestion->newsSource?->search_url;
            $data['sitemap_url'] = $suggestion->sitemap_url ?: $suggestion->newsSource?->sitemap_url;
            $data['search_result_selector'] = $suggestion->search_result_selector ?: $suggestion->newsSource?->search_result_selector;
            $data['article_link_selector'] = $suggestion->article_link_selector ?: $suggestion->newsSource?->article_link_selector;
        }

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

        $this->showTestModal = false;
        $this->notify('success', 'Saran berhasil diverifikasi dan diterapkan ke News Sources.');
    }

    public function saveAsDraft(int $id): void
    {
        $this->adminOnly();
        $suggestion = \App\Models\NewsSourceSuggestion::findOrFail($id);

        $suggestion->status = 'needs_review';
        $suggestion->save();

        $this->showTestModal = false;
        $this->notify('success', 'Saran disimpan sebagai draf review.');
    }

    public function rejectSuggestion(int $id): void
    {
        $this->adminOnly();
        $suggestion = \App\Models\NewsSourceSuggestion::findOrFail($id);
        $suggestion->status = 'rejected';
        $suggestion->save();

        $this->showTestModal = false;
        $this->notify('success', 'Saran ditolak.');
    }

    public function deleteSuggestion(int $id): void
    {
        $this->adminOnly();
        $suggestion = \App\Models\NewsSourceSuggestion::findOrFail($id);
        $suggestion->delete();

        if ($this->selectedSuggestionId === $id) {
            $this->selectedSuggestionId = null;
            $this->testResult = null;
            $this->testStatus = null;
            $this->showTestModal = false;
            $this->manualArticleUrl = '';
        }

        $this->notify('success', 'Saran berhasil dihapus.');
    }

    public function cancelTesting(): void
    {
        $this->testingSuggestionId = null;
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = trim(strtolower($domain));
        $domain = preg_replace('~^https?://~', '', $domain) ?? $domain;
        $domain = preg_replace('~^www\.~', '', $domain) ?? $domain;
        $domain = preg_replace('~/.*$~', '', $domain) ?? $domain;

        return trim($domain);
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
    }
}
