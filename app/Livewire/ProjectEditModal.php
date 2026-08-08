<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Project;
use App\Models\Package;
use App\Jobs\ProjectContentResyncJob;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class ProjectEditModal extends Component
{
    public $showModal = false;
    
    public $editProjectId = null;
    public $editName = '';
    public $editTopicsString = '';
    public $contextKeywords = '';
    public $excludeKeywords = '';
    public $packageId = null;
    public $telegramChatId = '';
    
    public $projectPackage = null;

    #[On('open-project-edit')]
    public function open($projectId)
    {
        $project = Project::accessibleBy(auth()->user())->findOrFail($projectId);
        
        $this->editProjectId = $project->id;
        $this->editName = $project->name;
        $this->editTopicsString = implode(', ', $project->topics ?? []);
        $this->contextKeywords = implode(', ', $project->context_keywords ?? []);
        $this->excludeKeywords = implode(', ', $project->exclude_keywords ?? []);
        $this->packageId = $project->package_id;
        
        // Eager load only needed package for display
        $this->projectPackage = $project->package_id ? Package::find($project->package_id) : null;
        
        $recipients = DB::table('project_telegram_recipients')
            ->where('project_id', $project->id)
            ->pluck('chat_id')
            ->toArray();
            
        $this->telegramChatId = implode(', ', $recipients);
        
        $this->resetValidation();
        $this->showModal = true;
    }

    protected function parseOptionalKeywordString(string $value): array
    {
        $items = array_map('trim', explode(',', $value));
        $items = array_filter($items);

        return array_values(array_unique($items));
    }

    protected function parseMultiChatIds(string $value): array
    {
        $normalized = str_replace([';', ' '], ',', $value);
        $items = array_map('trim', explode(',', $normalized));
        $items = array_map(function($item) {
            return ltrim($item, '-');
        }, $items);
        $items = array_filter($items);

        return array_values(array_unique($items));
    }

    public function updateProject()
    {
        $this->validate([
            'editName'         => 'required|min:3|unique:projects,name,' . $this->editProjectId,
            'editTopicsString' => 'required',
            'telegramChatId'   => 'required',
        ], [
            'editName.required'         => 'Nama proyek wajib diisi.',
            'editName.min'              => 'Nama proyek minimal 3 karakter.',
            'editName.unique'           => 'Nama proyek sudah digunakan.',
            'editTopicsString.required' => 'Kata kunci pencarian (scraping) wajib diisi.',
            'telegramChatId.required'   => 'Telegram Chat ID wajib diisi.',
        ]);

        $user = auth()->user();
        if ($user && $user->isClient()) {
            if (! optional($user->clientSettings)->can_edit_projects) {
                $this->addError('editName', 'Anda tidak memiliki izin untuk mengedit proyek.');
                return;
            }
        }

        $project = Project::accessibleBy($user)->findOrFail($this->editProjectId);

        if (str_starts_with(trim($this->editTopicsString), '{') || str_starts_with(trim($this->editTopicsString), '[')) {
            $this->addError('editTopicsString', 'Format JSON tidak diperbolehkan. Gunakan kata kunci yang dipisahkan koma.');
            return;
        }

        $topics = array_values(array_unique(array_filter(array_map('trim', explode(',', $this->editTopicsString)))));

        if (empty($topics)) {
            $this->addError('editTopicsString', 'Topik wajib diisi minimal satu kata kunci valid.');
            return;
        }

        // Limit Check: Max Keywords per Project (Tergantung paket yang dipilih dan setting klien)
        $package = $project->package;
        $packageMaxKeywords = $package ? $package->max_keywords_per_project : null;
        $clientMaxKeywords = ($user && $user->isClient()) ? optional($user->clientSettings)->max_keywords_per_project : null;

        $effectiveMaxKeywords = null;
        $kwLimits = array_filter([$packageMaxKeywords, $clientMaxKeywords], fn($val) => $val !== null);
        if (count($kwLimits) > 0) {
            $effectiveMaxKeywords = min($kwLimits);
        }

        if ($effectiveMaxKeywords !== null) {
            if (count($topics) > $effectiveMaxKeywords) {
                $this->addError('editTopicsString', 'Jumlah kata kunci melebihi batas maksimal yang diizinkan (Maksimal: ' . $effectiveMaxKeywords . '). Anda memasukkan ' . count($topics) . ' kata kunci.');
                return;
            }
        }

        $project->update([
            'name' => $this->editName,
            'topics' => $topics,
            'context_keywords' => $this->parseOptionalKeywordString((string) $this->contextKeywords),
            'exclude_keywords' => $this->parseOptionalKeywordString((string) $this->excludeKeywords),
        ]);

        DB::table('project_telegram_recipients')->where('project_id', $project->id)->delete();
        $chatIds = $this->parseMultiChatIds((string) $this->telegramChatId);
        foreach ($chatIds as $cId) {
            DB::table('project_telegram_recipients')->insert([
                'project_id' => $project->id,
                'chat_id' => $cId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        ProjectContentResyncJob::dispatch($project);

        $this->showModal = false;
        
        $this->dispatch('project-updated', projectId: $project->id);
        $this->dispatch('project-action-toast', type: 'success', message: 'Proyek berhasil diperbarui.');
    }

    public function close()
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.project-edit-modal');
    }
}
