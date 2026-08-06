<?php

namespace App\Livewire\Admin;

use App\Models\AiPromptTemplate;
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
    public string $source_type = 'article'; // article, social, report, portal_suggestion
    public string $system_prompt = '';
    public string $user_prompt_template = '';
    public string $output_schema = '';
    public bool $is_active = true;
    public bool $is_default = false;

    // UI state
    public bool $showFormModal = false;
    public bool $isEditing = false;
    public bool $confirmingDelete = false;
    public ?string $flashMessage = null;
    public ?string $flashType = null;

    // Trash and Confirmation Modals
    public bool $showTrashModal = false;
    public ?int $confirmingRestoreTemplateId = null;
    public ?int $confirmingForceDeleteTemplateId = null;

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
            'source_type' => ['required', 'string', 'in:article,social,report,portal_suggestion'],
            'system_prompt' => ['required', 'string'],
            'user_prompt_template' => ['required', 'string'],
            'output_schema' => ['required', 'string'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required'               => 'Nama Template wajib diisi.',
            'name.max'                    => 'Nama Template tidak boleh lebih dari 255 karakter.',
            'source_type.required'        => 'Tipe Sumber Data wajib dipilih.',
            'source_type.in'              => 'Tipe Sumber Data yang dipilih tidak valid.',
            'system_prompt.required'      => 'Prompt Utama (System Prompt) wajib diisi.',
            'user_prompt_template.required' => 'User Prompt Template wajib diisi.',
            'output_schema.required'      => 'Output Schema (JSON Schema) wajib diisi.',
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
        AiPromptTemplate::ensureDefaultForSourceType('article');
        AiPromptTemplate::ensureDefaultForSourceType('social');
        AiPromptTemplate::ensureDefaultForSourceType('report');

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
            'trashTemplates' => AiPromptTemplate::onlyTrashed()->orderByDesc('deleted_at')->paginate(5, pageName: 'trashPage'),
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

        if (trim($this->name) === 'Saran Portal Manual' && $this->source_type === 'portal_suggestion') {
            $duplicateExists = AiPromptTemplate::query()
                ->where('name', 'Saran Portal Manual')
                ->where('source_type', 'portal_suggestion')
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
            // Set new template as default, and remove default from other templates of this source_type
            $data['is_default'] = true;
            AiPromptTemplate::where('source_type', $this->source_type)
                ->update(['is_default' => false]);

            AiPromptTemplate::create($data);
            $this->notify('success', 'Template prompt baru berhasil ditambahkan.');
        }

        AiPromptTemplate::ensureDefaultForSourceType($this->source_type);

        if (trim($this->name) === 'Saran Portal Manual' && $this->source_type === 'portal_suggestion') {
            $templateId = $this->isEditing ? $this->selected_id : null;
            AiPromptTemplate::query()
                ->where('source_type', 'portal_suggestion')
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
                    ->where('source_type', 'portal_suggestion')
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

        AiPromptTemplate::ensureDefaultForSourceType($template->source_type);

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
            AiPromptTemplate::ensureDefaultForSourceType($template->source_type);
            $this->notify('success', 'Template prompt berhasil dihapus.');
        }
        $this->confirmingDelete = false;
        $this->resetForm();
    }

    public function openTrashModal(): void
    {
        $this->adminOnly();
        $this->showTrashModal = true;
    }

    public function closeTrashModal(): void
    {
        $this->showTrashModal = false;
        $this->confirmingRestoreTemplateId = null;
        $this->confirmingForceDeleteTemplateId = null;
    }

    public function confirmRestoreTemplate(int $id): void
    {
        $this->adminOnly();
        $this->confirmingRestoreTemplateId = $id;
    }

    public function cancelRestore(): void
    {
        $this->confirmingRestoreTemplateId = null;
    }

    public function restoreTemplateConfirmed(): void
    {
        $this->adminOnly();
        if ($this->confirmingRestoreTemplateId) {
            $template = AiPromptTemplate::onlyTrashed()->find($this->confirmingRestoreTemplateId);
            if ($template) {
                $template->restore();
                AiPromptTemplate::ensureDefaultForSourceType($template->source_type);
                $this->notify('success', 'Template prompt berhasil dipulihkan.');
            }
        }
        $this->confirmingRestoreTemplateId = null;
    }

    public function confirmForceDeleteTemplate(int $id): void
    {
        $this->adminOnly();
        $this->confirmingForceDeleteTemplateId = $id;
    }

    public function cancelForceDelete(): void
    {
        $this->confirmingForceDeleteTemplateId = null;
    }

    public function forceDeleteTemplateConfirmed(): void
    {
        $this->adminOnly();
        if ($this->confirmingForceDeleteTemplateId) {
            $template = AiPromptTemplate::onlyTrashed()->find($this->confirmingForceDeleteTemplateId);
            if ($template) {
                $sourceType = $template->source_type;
                $template->forceDelete();
                AiPromptTemplate::ensureDefaultForSourceType($sourceType);
                $this->notify('success', 'Template prompt berhasil dihapus secara permanen.');
            }
        }
        $this->confirmingForceDeleteTemplateId = null;
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

    private function ensureSaranPortalManualDefault(): void
    {
        $template = AiPromptTemplate::query()
            ->where('name', 'Saran Portal Manual')
            ->where('source_type', 'portal_suggestion')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if (! $template) {
            return;
        }

        $canonicalSystemPrompt = AiPromptTemplate::saranPortalManualSystemPrompt();
        $canonicalUserPrompt = AiPromptTemplate::saranPortalManualUserPromptTemplate();
        $canonicalOutputSchema = AiPromptTemplate::saranPortalManualOutputSchema();

        $dirty = false;
        if ($template->system_prompt !== $canonicalSystemPrompt) {
            $template->system_prompt = $canonicalSystemPrompt;
            $dirty = true;
        }
        if ($template->user_prompt_template !== $canonicalUserPrompt) {
            $template->user_prompt_template = $canonicalUserPrompt;
            $dirty = true;
        }
        if ($template->output_schema !== $canonicalOutputSchema) {
            $template->output_schema = $canonicalOutputSchema;
            $dirty = true;
        }
        if ($dirty) {
            $template->save();
        }

        if ($template->is_default) {
            return;
        }

        AiPromptTemplate::query()
            ->where('source_type', 'portal_suggestion')
            ->where('id', '!=', $template->id)
            ->update(['is_default' => false]);

        $template->forceFill(['is_default' => true])->save();
    }
}
