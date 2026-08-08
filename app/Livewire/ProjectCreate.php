<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Project;
use App\Models\Package;
use App\Jobs\BootstrapNewProjectScrapingJob;
use App\Services\ContentMatchingService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;

#[Layout('welcome')]
class ProjectCreate extends Component
{
    public $name = '';
    public $topicsString = '';
    public $contextKeywords = '';
    public $excludeKeywords = '';
    public $telegramChatId = '';
    
    public $createStep = 1;
    public $packageId = null;

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

    public function createProject()
    {
        $this->validate([
            'name' => 'required|min:3|unique:projects,name',
            'topicsString' => 'required',
            'telegramChatId' => 'required',
            'packageId' => 'required',
        ], [
            'name.required' => 'Nama proyek wajib diisi.',
            'name.min' => 'Nama proyek minimal harus 3 karakter.',
            'name.unique' => 'Nama proyek ini sudah digunakan, silakan pilih nama lain.',
            'topicsString.required' => 'Kata kunci pencarian (scraping) wajib diisi.',
            'telegramChatId.required' => 'Telegram Chat ID wajib diisi.',
            'packageId.required' => 'Silakan pilih paket terlebih dahulu.',
        ]);

        $user = auth()->user();

        // Cek izin membuat proyek jika user adalah Klien
        if ($user && $user->isClient()) {
            if (! optional($user->clientSettings)->can_create_projects) {
                $this->addError('name', 'Anda tidak memiliki izin untuk membuat proyek baru.');
                return;
            }

            // Validasi apakah paket yang dipilih ada di dalam allowedPackages
            if (! $user->allowedPackages()->where('packages.id', $this->packageId)->exists()) {
                $this->addError('packageId', 'Anda tidak memiliki akses ke paket yang dipilih.');
                return;
            }
        }

        // Validate package security & existence
        $package = Package::query()->where('is_active', true)->findOrFail($this->packageId);

        // Limit Check: Max Projects (Hanya menghitung global limit untuk Klien)
        if ($user && $user->isClient()) {
            $effectiveMaxProjects = $user->getEffectiveMaxProjects();
            if ($effectiveMaxProjects !== null) {
                $currentProjectsCount = $user->projects()->count();
                if ($currentProjectsCount >= $effectiveMaxProjects) {
                    $this->addError('name', 'Anda telah mencapai batas maksimal pembuatan proyek secara keseluruhan (Batas: ' . $effectiveMaxProjects . ' proyek).');
                    return;
                }
            }
        }

        // Validate JSON string
        if (str_starts_with(trim($this->topicsString), '{') || str_starts_with(trim($this->topicsString), '[')) {
            $this->addError('topicsString', 'Format JSON tidak diperbolehkan. Gunakan kata kunci yang dipisahkan koma.');
            return;
        }

        // Parse comma-separated topics
        $topics = array_map('trim', explode(',', $this->topicsString));
        $topics = array_filter($topics); // remove empty elements
        $topics = array_unique($topics); // remove duplicates
        $topics = array_values($topics);

        if (empty($topics)) {
            $this->addError('topicsString', 'Topik wajib diisi minimal satu kata kunci valid.');
            return;
        }

        // Limit Check: Max Keywords per Project (Tergantung paket yang dipilih dan setting klien)
        $packageMaxKeywords = $package->max_keywords_per_project;
        $clientMaxKeywords = ($user && $user->isClient()) ? optional($user->clientSettings)->max_keywords_per_project : null;

        $effectiveMaxKeywords = null;
        $kwLimits = array_filter([$packageMaxKeywords, $clientMaxKeywords], fn($val) => $val !== null);
        if (count($kwLimits) > 0) {
            $effectiveMaxKeywords = min($kwLimits);
        }

        if ($effectiveMaxKeywords !== null) {
            if (count($topics) > $effectiveMaxKeywords) {
                $this->addError('topicsString', 'Jumlah kata kunci melebihi batas maksimal yang diizinkan (Maksimal: ' . $effectiveMaxKeywords . '). Anda memasukkan ' . count($topics) . ' kata kunci.');
                return;
            }
        }

        $project = Project::create([
            'name' => $this->name,
            'topics' => array_values($topics),
            'context_keywords' => $this->parseOptionalKeywordString((string) $this->contextKeywords),
            'exclude_keywords' => $this->parseOptionalKeywordString((string) $this->excludeKeywords),
            'package_id' => $package->id,
        ]);

        // Save telegram recipients without minus (-) sign (supporting multi chat ids)
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

        // Auto-assign project to the creator if they are a regular user or client
        if ($user && !$user->isAdmin()) {
            $project->users()->attach($user->id);
        }

        app(ContentMatchingService::class)->resyncProjectContent($project);
        BootstrapNewProjectScrapingJob::dispatch($project->id)->onQueue('news');

        $this->dispatch('project-action-toast', type: 'success', message: 'Proyek berhasil dibuat.');
        
        return $this->redirect('/', navigate: true);
    }

    public function render()
    {
        $user = auth()->user();
        $query = Package::where('is_active', true);
        
        if ($user && $user->isClient()) {
            $allowedPackageIds = $user->allowedPackages()->pluck('packages.id');
            $query->whereIn('id', $allowedPackageIds);
        }

        $packages = $query->orderBy('price', 'asc')->get();

        return view('livewire.project-create', [
            'packages' => $packages,
            'selectedPackage' => $this->packageId ? Package::find($this->packageId) : null,
        ]);
    }
}
