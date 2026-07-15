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
    public string $source_type = 'article'; // article, social
    public string $system_prompt = '';
    public string $user_prompt_template = '';
    public ?string $output_schema = '';
    public bool $is_active = true;
    public bool $is_default = false;

    // UI state
    public bool $showFormModal = false;
    public bool $isEditing = false;
    public bool $confirmingDelete = false;
    public ?string $flashMessage = null;
    public ?string $flashType = null;

    protected function adminOnly(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Akses ditolak.');
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'source_type' => ['required', 'string', 'in:article,social'],
            'system_prompt' => ['required', 'string'],
            'user_prompt_template' => ['required', 'string'],
            'output_schema' => ['nullable', 'string'],
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

        $templates = AiPromptTemplate::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('source_type', 'like', '%' . $this->search . '%');
            })
            ->orderBy('source_type')
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.admin.ai-prompt-templates', [
            'templates' => $templates,
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
        $this->user_prompt_template = $template->user_prompt_template;
        $this->output_schema = $template->output_schema;
        $this->is_active = $template->is_active;
        $this->is_default = $template->is_default;

        $this->isEditing = true;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->adminOnly();
        $this->validate();

        // Validate JSON format for output_schema if provided
        if ($this->output_schema) {
            json_decode($this->output_schema);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError('output_schema', 'Format JSON Schema tidak valid.');
                return;
            }
        }

        $data = [
            'name' => $this->name,
            'source_type' => $this->source_type,
            'system_prompt' => $this->system_prompt,
            'user_prompt_template' => $this->user_prompt_template,
            'output_schema' => $this->output_schema ?: null,
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
}
