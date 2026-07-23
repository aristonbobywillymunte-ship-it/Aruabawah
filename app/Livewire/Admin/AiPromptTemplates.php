<?php

namespace App\Livewire\Admin;

use App\Models\AiPromptTemplate;
use App\Models\AiProvider;
use App\Services\AiProviderClient;
use Livewire\Component;
use Livewire\WithPagination;

class AiPromptTemplates extends Component
{
    use WithPagination;

    // Search and filter
    public string $search = '';

    // Form fields
    public ?int $selected_id = null;
    public string $name = '';
    public string $source_type = 'article'; // article, social
    public string $system_prompt = '';
    public string $user_prompt_template = '';
    public string $output_schema = '';
    public bool $is_active = true;
    public bool $is_default = false;

    // UI state
    public bool $showFormModal = false;
    public bool $isEditing = false;
    public bool $showTestModal = false;
    public bool $confirmingDelete = false;
    public ?int $testingTemplateId = null;
    public string $test_name = '';
    public string $test_domain = '';
    public string $test_base_url = '';
    public string $test_article_url = '';
    public string $test_rendered_prompt = '';
    public ?string $test_raw_output = null;
    public ?string $test_error = null;
    public ?string $flashMessage = null;
    public ?string $flashType = null;

    protected function adminOnly(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Akses ditolak.');
    }

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $normalized = mb_strtolower(trim((string) $value));

                    $query = AiPromptTemplate::query()
                        ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
                        ->where('source_type', $this->source_type);

                    if ($this->selected_id) {
                        $query->where('id', '!=', $this->selected_id);
                    }

                    if ($query->exists()) {
                        $fail('Nama template untuk tipe sumber ini sudah digunakan. Saran Portal Manual harus tunggal.');
                    }
                },
            ],
            'source_type' => ['required', 'string', 'in:article,social'],
            'system_prompt' => ['required', 'string'],
            'user_prompt_template' => ['required', 'string'],
            'output_schema' => ['required', 'string'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->adminOnly();
        $this->ensureSaranPortalManualDefault();

        $templates = AiPromptTemplate::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('source_type', 'like', '%' . $this->search . '%');
            })
            ->orderBy('source_type')
            ->orderBy('name')
            ->paginate(10);

        $duplicateNames = AiPromptTemplate::query()
            ->select('name', 'source_type')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('name', 'source_type')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('source_type')
            ->orderBy('name')
            ->get();

        return view('livewire.admin.ai-prompt-templates', [
            'templates' => $templates,
            'duplicateNames' => $duplicateNames,
        ]);
    }

    public function resetForm(): void
    {
        $this->selected_id = null;
        $this->name = '';
        $this->source_type = 'article';
        $this->system_prompt = '';
        $this->user_prompt_template = '';
        $this->output_schema = '';
        $this->is_active = true;
        $this->is_default = false;
        $this->isEditing = false;
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

        $template = AiPromptTemplate::findOrFail($id);
        $this->selected_id = $template->id;
        $this->name = $template->name;
        $this->source_type = $template->source_type;
        $this->system_prompt = $template->system_prompt;
        $this->user_prompt_template = $template->user_prompt_template ?? '';
        $this->output_schema = $template->output_schema ?? '';
        $this->is_active = $template->is_active;
        $this->is_default = $template->is_default;

        $this->isEditing = true;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->adminOnly();
        $this->validate();

        if (trim($this->name) === 'Saran Portal Manual' && $this->source_type === 'article') {
            $duplicateExists = AiPromptTemplate::query()
                ->where('name', 'Saran Portal Manual')
                ->where('source_type', 'article')
                ->when($this->selected_id, fn ($query) => $query->where('id', '!=', $this->selected_id))
                ->exists();

            if ($duplicateExists) {
                $this->notify('error', 'Saran Portal Manual wajib satu dan tidak boleh double.');
                return;
            }
        }

        $data = [
            'name' => $this->name,
            'source_type' => $this->source_type,
            'system_prompt' => $this->system_prompt,
            'user_prompt_template' => $this->user_prompt_template,
            'output_schema' => $this->output_schema,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
        ];

        if ($this->is_default) {
            // Remove default from other templates of the same type
            AiPromptTemplate::where('source_type', $this->source_type)
                ->where('id', '!=', $this->selected_id)
                ->update(['is_default' => false]);
        }

        if ($this->isEditing) {
            $template = AiPromptTemplate::findOrFail($this->selected_id);
            $template->update($data);
            $this->notify('success', 'Template prompt berhasil diperbarui.');
        } else {
            // If this is the first template for this source_type, make it default
            $exists = AiPromptTemplate::where('source_type', $this->source_type)->exists();
            if (!$exists) {
                $data['is_default'] = true;
            }
            AiPromptTemplate::create($data);
            $this->notify('success', 'Template prompt baru berhasil ditambahkan.');
        }

        if (trim($this->name) === 'Saran Portal Manual' && $this->source_type === 'article') {
            $templateId = $this->isEditing ? $this->selected_id : null;
            AiPromptTemplate::query()
                ->where('source_type', 'article')
                ->where('name', 'Saran Portal Manual')
                ->where('id', '!=', $templateId)
                ->update(['is_default' => false]);

            if ($this->isEditing && $this->selected_id) {
                AiPromptTemplate::query()
                    ->whereKey($this->selected_id)
                    ->update(['is_default' => true, 'is_active' => true]);
            } else {
                AiPromptTemplate::query()
                    ->where('name', 'Saran Portal Manual')
                    ->where('source_type', 'article')
                    ->where('is_active', true)
                    ->orderByDesc('id')
                    ->limit(1)
                    ->update(['is_default' => true]);
            }
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function toggleStatus(int $id): void
    {
        $this->adminOnly();
        $template = AiPromptTemplate::findOrFail($id);
        
        // Cannot deactivate default template unless another exists and is default
        if ($template->is_default && $template->is_active) {
            $this->notify('error', 'Tidak dapat menonaktifkan template default utama.');
            return;
        }

        $template->is_active = !$template->is_active;
        $template->save();

        $this->notify('success', 'Status template prompt berhasil diperbarui.');
    }

    public function setDefault(int $id): void
    {
        $this->adminOnly();
        $template = AiPromptTemplate::findOrFail($id);
        
        if (!$template->is_active) {
            $this->notify('error', 'Template nonaktif tidak bisa dijadikan default.');
            return;
        }

        // Set all other templates of this type to non-default
        AiPromptTemplate::where('source_type', $template->source_type)
            ->where('id', '!=', $template->id)
            ->update(['is_default' => false]);

        $template->is_default = true;
        $template->save();

        $this->notify('success', 'Template default baru berhasil dipasang.');
    }

    public function requestDelete(int $id): void
    {
        $this->adminOnly();
        $template = AiPromptTemplate::findOrFail($id);

        if ($template->is_default) {
            $this->notify('error', 'Tidak dapat menghapus template default utama.');
            return;
        }

        $this->selected_id = $id;
        $this->confirmingDelete = true;
    }

    public function deleteConfirmed(): void
    {
        $this->adminOnly();
        if ($this->selected_id) {
            $template = AiPromptTemplate::findOrFail($this->selected_id);
            $template->delete();
            $this->notify('success', 'Template prompt berhasil dihapus.');
        }
        $this->confirmingDelete = false;
        $this->resetForm();
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function openTestModal(int $id): void
    {
        $this->adminOnly();
        $template = AiPromptTemplate::findOrFail($id);

        $this->testingTemplateId = $template->id;
        $this->test_name = 'Arusbawah.co';
        $this->test_domain = 'arusbawah.co';
        $this->test_base_url = 'https://arusbawah.co';
        $this->test_article_url = 'https://arusbawah.co/contoh-artikel';
        $this->test_rendered_prompt = '';
        $this->test_raw_output = null;
        $this->test_error = null;
        $this->showTestModal = true;
    }

    public function closeTestModal(): void
    {
        $this->showTestModal = false;
        $this->testingTemplateId = null;
        $this->test_name = '';
        $this->test_domain = '';
        $this->test_base_url = '';
        $this->test_article_url = '';
        $this->test_rendered_prompt = '';
        $this->test_raw_output = null;
        $this->test_error = null;
    }

    public function runTemplateTest(): void
    {
        $this->adminOnly();

        $template = AiPromptTemplate::findOrFail($this->testingTemplateId);
        $provider = AiProvider::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if (! $provider) {
            $this->test_error = 'AI provider belum tersedia.';
            $this->test_raw_output = null;
            return;
        }

        $renderedPrompt = $this->renderTemplatePrompt($template, [
            'name' => $this->test_name,
            'domain' => $this->test_domain,
            'base_url' => $this->test_base_url,
            'article_url' => $this->test_article_url,
        ]);
        $schemaPrompt = trim((string) ($template->output_schema ?? ''));
        if ($schemaPrompt !== '') {
            $renderedPrompt .= "\n\nWAJIB IKUTI SCHEMA OUTPUT INI TANPA MENAMBAH KEY LAIN:\n" . $schemaPrompt;
        }
        $this->test_rendered_prompt = $renderedPrompt;
        $this->test_error = null;

        try {
            $client = app(AiProviderClient::class);
            $response = $client->sendRequest($provider, trim($template->system_prompt), trim($renderedPrompt), [
                'temperature' => 0.0,
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('HTTP ' . $response->status() . ': ' . $response->body());
            }

            $this->test_raw_output = $client->parseResponse($provider, $response) ?? '';
        } catch (\Throwable $e) {
            $this->test_error = $e->getMessage();
            $this->test_raw_output = null;
        }
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

    private function renderTemplatePrompt(AiPromptTemplate $template, array $context): string
    {
        $name = trim((string) ($context['name'] ?? ''));
        $domain = trim((string) ($context['domain'] ?? ''));
        $baseUrl = trim((string) ($context['base_url'] ?? ''));
        $articleUrl = trim((string) ($context['article_url'] ?? ''));

        $name = $name !== '' ? $name : 'Arusbawah.co';
        $domain = $domain !== '' ? $domain : 'arusbawah.co';
        $baseUrl = $baseUrl !== '' ? $baseUrl : 'https://arusbawah.co';
        $articleUrl = $articleUrl !== '' ? $articleUrl : 'https://arusbawah.co/contoh-artikel';

        $replacements = [
            '{name}' => $name,
            '{domain}' => $domain,
            '{base_url}' => $baseUrl,
            '{article_url}' => $articleUrl,
            '{html}' => $articleUrl,
            '{url}' => $articleUrl,
            '{keyword}' => 'politik',
            '{query}' => 'politik',
        ];

        return strtr($template->user_prompt_template, $replacements);
    }

    private function ensureSaranPortalManualDefault(): void
    {
        $template = AiPromptTemplate::query()
            ->where('name', 'Saran Portal Manual')
            ->where('source_type', 'article')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if (! $template) {
            return;
        }

        if ($template->is_default) {
            return;
        }

        AiPromptTemplate::query()
            ->where('source_type', 'article')
            ->where('id', '!=', $template->id)
            ->update(['is_default' => false]);

        $template->forceFill(['is_default' => true])->save();
    }
}
