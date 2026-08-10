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
    public array $news_run_times_override = [];
    public array $social_run_times_override = [];
    
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

    protected function selectedPackage(): ?Package
    {
        if (! $this->packageId) {
            return null;
        }

        return Package::query()->where('is_active', true)->find($this->packageId);
    }

    protected function resizeOverrideSlots(array $values, ?int $count): array
    {
        $count = blank($count) ? 0 : min(24, max(0, (int) $count));
        $values = array_values($values);

        if ($count <= 0) {
            return [];
        }

        if (count($values) > $count) {
            return array_slice($values, 0, $count);
        }

        while (count($values) < $count) {
            $values[] = '';
        }

        return $values;
    }

    protected function normalizeOverrideGroup(array $values, string $field): ?array
    {
        $trimmed = array_map(static fn ($value) => is_string($value) ? trim($value) : '', $values);
        $filled = array_values(array_filter($trimmed, static fn ($value) => $value !== ''));

        if ($filled === []) {
            return null;
        }

        if (count($filled) !== count($trimmed)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => $field === 'news_run_times_override'
                    ? 'Lengkapi seluruh jadwal Portal Proyek atau kosongkan semuanya untuk mengikuti Paket.'
                    : 'Lengkapi seluruh jadwal Sosial Proyek atau kosongkan semuanya untuk mengikuti Paket.',
            ]);
        }

        $normalized = [];
        $seen = [];

        foreach ($filled as $time) {
            if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $field => 'Gunakan format jam 24 jam HH:MM.',
                ]);
            }

            if (isset($seen[$time])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $field => 'Jam yang sama tidak boleh dipakai dua kali.',
                ]);
            }

            $seen[$time] = true;
            $normalized[] = $time;
        }

        sort($normalized);

        return $normalized;
    }

    protected function syncOverrideSlotsFromPackage(?Package $package): void
    {
        $this->news_run_times_override = $this->resizeOverrideSlots(
            $this->news_run_times_override,
            $package?->news_runs_per_day
        );

        $this->social_run_times_override = $this->resizeOverrideSlots(
            $this->social_run_times_override,
            $package?->social_runs_per_day
        );
    }

    public function updatedPackageId($value): void
    {
        $package = $this->selectedPackage();
        $this->syncOverrideSlotsFromPackage($package);
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

        // Limit Check: Max Projects (Hanya menghitung global limit untuk Klien, HANYA proyek aktif)
        if ($user && $user->isClient()) {
            $effectiveMaxProjects = $user->getEffectiveMaxProjects();
            if ($effectiveMaxProjects !== null) {
                $currentProjectsCount = $user->projects()->where('is_active', true)->count();
                if ($currentProjectsCount >= $effectiveMaxProjects) {
                    $this->addError('name', 'Anda telah mencapai batas maksimal pembuatan proyek aktif (Batas: ' . $effectiveMaxProjects . ' proyek).');
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

        $this->news_run_times_override = $this->normalizeOverrideGroup($this->news_run_times_override, 'news_run_times_override') ?? [];
        $this->social_run_times_override = $this->normalizeOverrideGroup($this->social_run_times_override, 'social_run_times_override') ?? [];

        $project = Project::create([
            'name' => $this->name,
            'topics' => array_values($topics),
            'context_keywords' => $this->parseOptionalKeywordString((string) $this->contextKeywords),
            'exclude_keywords' => $this->parseOptionalKeywordString((string) $this->excludeKeywords),
            'package_id' => $package->id,
            'news_run_times_override' => $this->news_run_times_override ?: null,
            'social_run_times_override' => $this->social_run_times_override ?: null,
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

        session()->flash('toast', [
            'type' => 'success',
            'message' => 'Proyek berhasil dibuat.'
        ]);
        
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
            'selectedPackage' => $this->selectedPackage(),
        ]);
    }
}
