<?php

namespace App\Livewire\Admin;

use App\Models\ApifyActor;
use App\Models\Package;
use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class PackageManager extends Component
{
    use WithPagination;

    // ─── UI State ─────────────────────────────────────────────────────────
    public string $view = 'list'; // list | form | actors
    public ?int $editingPackageId = null;
    public ?int $managingActorsPackageId = null;

    // ─── Form Fields ──────────────────────────────────────────────────────
    public string $name = '';
    public string $description = '';
    public string $price = '0';
    public string $newSocialFeature = '';
    public array $social_media_features = [];
    public string $newPortalFeature = '';
    public array $news_portal_features = [];
    public string $newAdvantage = '';
    public array $advantages = [];
    public bool $is_active = true;
    public bool $use_portal = true;
    public int $news_interval_minutes = 5;
    public int $social_interval_minutes = 10;
    public bool $is_popular = false;
    public ?int $max_projects = null;
    public ?int $max_keywords_per_project = null;

    // ─── CRUD-based Limits & Features (Advantages Wrapper) ───────────────
    public string $limit_projects = 'unlimited';
    public string $limit_keywords = 'unlimited';
    public string $limit_mentions = '500000';
    public string $limit_users = 'unlimited';
    public bool $feat_ai = false;
    public bool $feat_rss = false;
    public bool $feat_api = false;
    public bool $feat_whitelabel = false;

    // ─── Actor Configuration (per paket) ─────────────────────────────────
    /** @var array<int, array{is_enabled: bool, cost_per_run_usd: string}> keyed by apify_actor_id */
    public array $actorConfig = [];

    // ─── Confirmation ─────────────────────────────────────────────────────
    public ?int $confirmDeleteId = null;
    public string $flash = '';
    public string $flashType = 'success'; // success | error

    // ─── Search ───────────────────────────────────────────────────────────
    public string $search = '';

    protected function isActorPackageConfigComplete(array $config): bool
    {
        if (! (bool) ($config['is_enabled'] ?? false)) {
            return true;
        }

        $cost = $config['cost_per_run_usd'] ?? null;
        $limit = $config['default_limit'] ?? null;
        $memory = $config['memory_limit'] ?? null;

        return $cost !== '' && $cost !== null
            && is_numeric($cost) && (float) $cost >= 0
            && $limit !== '' && $limit !== null
            && is_numeric($limit) && (int) $limit > 0
            && $memory !== '' && $memory !== null
            && is_numeric($memory) && (int) $memory >= 128;
    }

    protected function validateActorPackageConfig(): void
    {
        foreach ($this->actorConfig as $actorId => $config) {
            if (! (bool) ($config['is_enabled'] ?? false)) {
                continue;
            }

            if (! $this->isActorPackageConfigComplete($config)) {
                $actor = ApifyActor::find($actorId);
                $actorName = $actor?->actor_name ?? "Actor #{$actorId}";

                $this->setFlash("Konfigurasi paket untuk {$actorName} belum lengkap. Isi biaya, limit, dan RAM sebelum menyimpan.", 'error');
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "actorConfig.{$actorId}" => "Konfigurasi paket {$actorName} belum lengkap.",
                ]);
            }
        }
    }

    protected function rules(): array
    {
        return [
            'name'                    => 'required|string|max:255',
            'description'             => 'nullable|string|max:1000',
            'price'                   => 'required|numeric|min:0',
            'social_media_features'   => 'nullable|array',
            'news_portal_features'    => 'nullable|array',
            'advantages'              => 'nullable|array',
            'is_active'               => 'boolean',
            'use_portal'              => 'boolean',
            'news_interval_minutes'   => 'required|integer|min:1',
            'social_interval_minutes' => 'required|integer|min:1',
            'is_popular'              => 'boolean',
            'max_projects'            => 'nullable|integer|min:1',
            'max_keywords_per_project'=> 'nullable|integer|min:1',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required'   => 'Hei, nama paket tidak boleh kosong ya! Kasih nama yang gampang diingat.',
            'name.max'        => 'Wah, nama paketnya kepanjangan. Maksimal 255 karakter saja.',
            'price.required'  => 'Harga paket belum diisi nih. Boleh isi 0 kalau memang gratis.',
            'price.numeric'   => 'Format harganya kurang tepat. Masukkan angka saja, tanpa huruf.',
            'price.min'       => 'Harga tidak bisa negatif. Minimal 0 ya.',
            'description.max' => 'Deskripsinya terlalu panjang. Maksimal 1.000 karakter saja.',
            'max_projects.integer' => 'Batas maksimal proyek harus berupa angka.',
            'max_projects.min' => 'Batas maksimal proyek minimal 1.',
            'max_keywords_per_project.integer' => 'Batas maksimal kata kunci harus berupa angka.',
            'max_keywords_per_project.min' => 'Batas maksimal kata kunci minimal 1.',
        ];
    }

    // ─── Feature List Management Actions ──────────────────────────────────
    public function addSocialFeature(): void
    {
        if (trim($this->newSocialFeature) !== '') {
            $this->social_media_features[] = trim($this->newSocialFeature);
            $this->newSocialFeature = '';
        }
    }

    public function removeSocialFeature(int $index): void
    {
        if (isset($this->social_media_features[$index])) {
            unset($this->social_media_features[$index]);
            $this->social_media_features = array_values($this->social_media_features);
        }
    }

    public function editSocialFeature(int $index): void
    {
        if (isset($this->social_media_features[$index])) {
            $this->newSocialFeature = $this->social_media_features[$index];
            $this->removeSocialFeature($index);
        }
    }

    public function addPortalFeature(): void
    {
        if (trim($this->newPortalFeature) !== '') {
            $this->news_portal_features[] = trim($this->newPortalFeature);
            $this->newPortalFeature = '';
        }
    }

    public function removePortalFeature(int $index): void
    {
        if (isset($this->news_portal_features[$index])) {
            unset($this->news_portal_features[$index]);
            $this->news_portal_features = array_values($this->news_portal_features);
        }
    }

    public function editPortalFeature(int $index): void
    {
        if (isset($this->news_portal_features[$index])) {
            $this->newPortalFeature = $this->news_portal_features[$index];
            $this->removePortalFeature($index);
        }
    }

    public function addAdvantage(): void
    {
        if (trim($this->newAdvantage) !== '') {
            $this->advantages[] = trim($this->newAdvantage);
            $this->newAdvantage = '';
        }
    }

    public function removeAdvantage(int $index): void
    {
        if (isset($this->advantages[$index])) {
            unset($this->advantages[$index]);
            $this->advantages = array_values($this->advantages);
        }
    }

    public function editAdvantage(int $index): void
    {
        if (isset($this->advantages[$index])) {
            $this->newAdvantage = $this->advantages[$index];
            $this->removeAdvantage($index);
        }
    }

    // ─── List View ────────────────────────────────────────────────────────

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // ─── Form Actions ─────────────────────────────────────────────────────

    public function createPackage(): void
    {
        $this->resetForm();
        $this->loadDefaultActorConfig();
        $this->editingPackageId = null;
        $this->view = 'form';
    }

    public function editPackage(int $id): void
    {
        $pkg = Package::findOrFail($id);
        $this->editingPackageId      = $id;
        $this->name                  = $pkg->name;
        $this->description           = $pkg->description ?? '';
        $this->price                 = (string) ($pkg->price ?? '0');
        $this->social_media_features = $pkg->social_media_features ?? [];
        $this->news_portal_features  = $pkg->news_portal_features ?? [];
        $this->advantages            = $pkg->advantages ?? [];
        $this->is_active             = $pkg->is_active;
        $this->use_portal            = (bool) ($pkg->use_portal ?? true);
        $this->news_interval_minutes = (int) ($pkg->news_interval_minutes ?? 5);
        $this->social_interval_minutes = (int) ($pkg->social_interval_minutes ?? 10);
        $this->is_popular            = (bool) ($pkg->is_popular ?? false);
        $this->max_projects          = $pkg->max_projects;
        $this->max_keywords_per_project = $pkg->max_keywords_per_project;
        
        // Parse advantages to find toggles and clean them up
        $this->parseAdvantagesToProperties();

        // Backward compatibility: If it is Enterprise and lists are empty/mixed, distribute them
        if (str_contains(strtolower($this->name), 'enterprise')) {
            // Move news portal features out of advantages if present
            foreach ($this->advantages as $key => $adv) {
                $advLower = strtolower($adv);
                if (str_contains($advLower, 'rss feed') || str_contains($advLower, 'portal scraper')) {
                    if (!in_array($adv, $this->news_portal_features)) {
                        $this->news_portal_features[] = $adv;
                    }
                    unset($this->advantages[$key]);
                }
            }
            $this->advantages = array_values($this->advantages);

            // Populate default lists if empty
            if (empty($this->social_media_features)) {
                $this->social_media_features = [
                    'Facebook Scraper (Posts, Comments, Likes)',
                    'Instagram Scraper (Posts, Comments, Profiles)',
                    'TikTok Scraper (Videos, Hashtags, Search)'
                ];
            }
            if (empty($this->news_portal_features)) {
                $this->news_portal_features = [
                    'RSS Feed & Portal Scraper',
                    'Custom Portal Scraping (Detik, Kompas, Tribun, dll.)'
                ];
            }
        }
        
        $this->loadActorConfig($id);
        $this->view = 'form';
    }

    public function savePackage(): void
    {
        $this->validate();
        $this->validateActorPackageConfig();

        // Pastikan parameter limit system di set ke unlimited / 500k penyebutan jika user tidak mengetiknya secara manual
        $this->limit_projects = 'unlimited';
        $this->limit_keywords = 'unlimited';
        $this->limit_mentions = '500000';
        $this->limit_users = 'unlimited';

        $this->compilePropertiesToAdvantages();

        $data = [
            'name'                    => trim($this->name),
            'description'             => trim($this->description) ?: null,
            'price'                   => (float) $this->price,
            'social_media_features'   => $this->social_media_features ?: null,
            'news_portal_features'    => $this->news_portal_features ?: null,
            'advantages'              => $this->advantages ?: null,
            'is_active'               => $this->is_active,
            'use_portal'              => $this->use_portal,
            'news_interval_minutes'   => $this->news_interval_minutes,
            'social_interval_minutes' => $this->social_interval_minutes,
            'is_popular'              => $this->is_popular,
            'max_projects'            => blank($this->max_projects) ? null : (int) $this->max_projects,
            'max_keywords_per_project'=> blank($this->max_keywords_per_project) ? null : (int) $this->max_keywords_per_project,
        ];

        if ($this->editingPackageId) {
            $pkg = Package::findOrFail($this->editingPackageId);
            $pkg->update($data);
            $this->setFlash('Paket berhasil diperbarui.', 'success');
        } else {
            $pkg = Package::create($data);
            $this->setFlash('Paket baru berhasil dibuat.', 'success');
        }

        $this->syncActorConfig($pkg);

        $this->view = 'list';
        $this->resetForm();
    }

    public function cancelForm(): void
    {
        $this->view = 'list';
        $this->resetForm();
    }

    // ─── Delete ───────────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
    }

    public function deletePackage(): void
    {
        $pkg = Package::find($this->confirmDeleteId);
        if (! $pkg) {
            $this->confirmDeleteId = null;
            return;
        }

        $usedCount = Project::where('package_id', $pkg->id)->count();
        if ($usedCount > 0) {
            $this->setFlash("Paket tidak bisa dihapus karena masih digunakan oleh {$usedCount} project.", 'error');
            $this->confirmDeleteId = null;
            return;
        }

        $pkg->actors()->detach();
        $pkg->delete();
        $this->confirmDeleteId = null;
        $this->setFlash('Paket berhasil dihapus.', 'success');
    }

    // ─── Manage Actors ────────────────────────────────────────────────────

    public function manageActors(int $packageId): void
    {
        $this->managingActorsPackageId = $packageId;
        $this->loadActorConfig($packageId);
        $this->view = 'actors';
    }

    protected function loadActorConfig(int $packageId): void
    {
        $pkg    = Package::with('actors')->findOrFail($packageId);
        $pivots = $pkg->actors->keyBy('id');

        $this->actorConfig = [];
        foreach (ApifyActor::orderBy('platform')->orderBy('actor_name')->get() as $actor) {
            $pivot = $pivots->get($actor->id)?->pivot;
            $this->actorConfig[$actor->id] = [
                'is_enabled'       => $pivot ? (bool) $pivot->is_enabled : false,
                'cost_per_run_usd' => $pivot?->cost_per_run_usd !== null ? (string) $pivot->cost_per_run_usd : '',
                'default_limit'    => $pivot?->default_limit !== null ? (string) $pivot->default_limit : '',
                'memory_limit'     => $pivot?->memory_limit !== null ? (string) $pivot->memory_limit : '',
            ];
        }
    }

    protected function loadDefaultActorConfig(): void
    {
        $this->actorConfig = [];

        foreach (ApifyActor::orderBy('platform')->orderBy('actor_name')->get() as $actor) {
            $this->actorConfig[$actor->id] = [
                'is_enabled' => false,
                'cost_per_run_usd' => '',
                'default_limit' => '',
                'memory_limit' => '',
            ];
        }
    }

    protected function syncActorConfig(Package $pkg): void
    {
        $syncData = [];

        foreach ($this->actorConfig as $actorId => $config) {
            $cost = $config['cost_per_run_usd'] !== '' && $config['cost_per_run_usd'] !== null
                ? (float) $config['cost_per_run_usd']
                : null;

            $limit = $config['default_limit'] !== '' && $config['default_limit'] !== null
                ? (int) $config['default_limit']
                : null;

            $memory = $config['memory_limit'] !== '' && $config['memory_limit'] !== null
                ? (int) $config['memory_limit']
                : null;

            $syncData[(int) $actorId] = [
                'is_enabled'       => (bool) ($config['is_enabled'] ?? false),
                'cost_per_run_usd' => $cost,
                'default_limit'    => $limit,
                'memory_limit'     => $memory,
            ];
        }

        $pkg->actors()->sync($syncData);
    }

    public function saveActors(): void
    {
        $pkg = Package::findOrFail($this->managingActorsPackageId);
        $this->validateActorPackageConfig();
        $this->syncActorConfig($pkg);
        $this->setFlash('Konfigurasi actor berhasil disimpan.', 'success');
        $this->view = 'list';
        $this->managingActorsPackageId = null;
    }

    public function cancelActors(): void
    {
        $this->view = 'list';
        $this->managingActorsPackageId = null;
        $this->actorConfig = [];
    }

    // ─── Toggle All Actors ────────────────────────────────────────────────

    public function enableAllActors(): void
    {
        foreach ($this->actorConfig as $id => $cfg) {
            $this->actorConfig[$id]['is_enabled'] = true;
        }
    }

    public function disableAllActors(): void
    {
        foreach ($this->actorConfig as $id => $cfg) {
            $this->actorConfig[$id]['is_enabled'] = false;
        }
    }

    // ─── Toggle Actor Directly ───────────────────────────────────────────

    public function toggleActor(int $actorId): void
    {
        if (isset($this->actorConfig[$actorId])) {
            $this->actorConfig[$actorId]['is_enabled'] = !($this->actorConfig[$actorId]['is_enabled'] ?? false);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    protected function parseAdvantagesToProperties(): void
    {
        $this->limit_projects = 'unlimited';
        $this->limit_keywords = 'unlimited';
        $this->limit_mentions = '500000';
        $this->limit_users = 'unlimited';
        $this->feat_ai = false;
        $this->feat_rss = false;
        $this->feat_api = false;
        $this->feat_whitelabel = false;

        $cleanAdvantages = [];
        foreach ($this->advantages as $adv) {
            $advLower = strtolower($adv);
            $isSystemOrToggle = false;
            
            if (str_contains($advLower, 'proyek')) {
                $isSystemOrToggle = true;
                if (str_contains($advLower, 'tak terbatas') || str_contains($advLower, 'unlimited')) {
                    $this->limit_projects = 'unlimited';
                } else {
                    $num = (int) filter_var(str_replace(['.', ','], '', $adv), FILTER_SANITIZE_NUMBER_INT);
                    $this->limit_projects = $num > 0 ? (string)$num : 'unlimited';
                }
            }
            
            if (str_contains($advLower, 'kata kunci')) {
                $isSystemOrToggle = true;
                if (str_contains($advLower, 'tak terbatas') || str_contains($advLower, 'unlimited')) {
                    $this->limit_keywords = 'unlimited';
                } else {
                    $num = (int) filter_var(str_replace(['.', ','], '', $adv), FILTER_SANITIZE_NUMBER_INT);
                    $this->limit_keywords = $num > 0 ? (string)$num : 'unlimited';
                }
            }
            
            if (str_contains($advLower, 'penyebutan')) {
                $isSystemOrToggle = true;
                if (str_contains($advLower, 'tak terbatas') || str_contains($advLower, 'unlimited')) {
                    $this->limit_mentions = 'unlimited';
                } else {
                    $num = (int) filter_var(str_replace(['.', ','], '', $adv), FILTER_SANITIZE_NUMBER_INT);
                    $this->limit_mentions = $num > 0 ? (string)$num : '500000';
                }
            }
            
            if (str_contains($advLower, 'pengguna') || str_contains($advLower, 'user')) {
                $isSystemOrToggle = true;
                if (str_contains($advLower, 'tak terbatas') || str_contains($advLower, 'unlimited')) {
                    $this->limit_users = 'unlimited';
                } else {
                    $num = (int) filter_var(str_replace(['.', ','], '', $adv), FILTER_SANITIZE_NUMBER_INT);
                    $this->limit_users = $num > 0 ? (string)$num : 'unlimited';
                }
            }
            
            if (str_contains($advLower, 'fitur ai') || str_contains($advLower, 'kecerdasan buatan')) {
                $this->feat_ai = true;
                $isSystemOrToggle = true;
            }
            if (str_contains($advLower, 'rss feed') || str_contains($advLower, 'portal scraper')) {
                $this->feat_rss = true;
                $isSystemOrToggle = true;
            }
            if (str_contains($advLower, 'telegram') || str_contains($advLower, 'integrasi api') || str_contains($advLower, 'akses api')) {
                $this->feat_api = true;
                $isSystemOrToggle = true;
            }
            if (str_contains($advLower, 'branding') || str_contains($advLower, 'whitelabel') || str_contains($advLower, 'dashboard custom')) {
                $this->feat_whitelabel = true;
                $isSystemOrToggle = true;
            }

            if (!$isSystemOrToggle) {
                $cleanAdvantages = [];
                // pastikan tidak masuk clean
            }
            
            // Perbaikan logic looping filter
            $cleanAdvantages = array_filter($this->advantages, function($item) {
                $itemLower = strtolower($item);
                return !(
                    str_contains($itemLower, 'proyek') ||
                    str_contains($itemLower, 'kata kunci') ||
                    str_contains($itemLower, 'penyebutan') ||
                    str_contains($itemLower, 'pengguna') ||
                    str_contains($itemLower, 'user') ||
                    str_contains($itemLower, 'fitur ai') ||
                    str_contains($itemLower, 'kecerdasan buatan') ||
                    str_contains($itemLower, 'rss feed') ||
                    str_contains($itemLower, 'portal scraper') ||
                    str_contains($itemLower, 'telegram') ||
                    str_contains($itemLower, 'integrasi api') ||
                    str_contains($itemLower, 'akses api') ||
                    str_contains($itemLower, 'branding') ||
                    str_contains($itemLower, 'whitelabel') ||
                    str_contains($itemLower, 'dashboard custom')
                );
            });
        }
        $this->advantages = array_values($cleanAdvantages);
    }

    public function compilePropertiesToAdvantages(): void
    {
        $newAdvantages = [];
        
        // Pelihara keuntungan kustom murni yang tidak bertabrakan dengan string limit/toggles
        foreach ($this->advantages as $adv) {
            $advLower = strtolower($adv);
            if (str_contains($advLower, 'proyek') ||
                str_contains($advLower, 'kata kunci') ||
                str_contains($advLower, 'penyebutan') ||
                str_contains($advLower, 'pengguna') ||
                str_contains($advLower, 'user') ||
                str_contains($advLower, 'fitur ai') ||
                str_contains($advLower, 'kecerdasan buatan') ||
                str_contains($advLower, 'rss feed') ||
                str_contains($advLower, 'portal scraper') ||
                str_contains($advLower, 'telegram') ||
                str_contains($advLower, 'integrasi api') ||
                str_contains($advLower, 'akses api') ||
                str_contains($advLower, 'branding') ||
                str_contains($advLower, 'whitelabel') ||
                str_contains($advLower, 'dashboard custom')) {
                continue;
            }
            $newAdvantages[] = $adv;
        }
        
        if ($this->feat_ai) {
            $newAdvantages[] = 'Fitur AI Tingkat Lanjut';
        }
        if ($this->feat_rss) {
            $newAdvantages[] = 'RSS Feed & Portal Scraper';
        }
        if ($this->feat_api) {
            $newAdvantages[] = 'Integrasi Telegram';
        }
        if ($this->feat_whitelabel) {
            $newAdvantages[] = 'Branding Aplikasi';
        }
        
        $this->advantages = $newAdvantages;
    }

    protected function resetForm(): void
    {
        $this->name                  = '';
        $this->description           = '';
        $this->price                 = '0';
        $this->newSocialFeature      = '';
        $this->social_media_features = [];
        $this->newPortalFeature      = '';
        $this->news_portal_features  = [];
        $this->newAdvantage          = '';
        $this->advantages            = [];
        $this->is_active             = true;
        $this->use_portal            = true;
        $this->news_interval_minutes = 5;
        $this->social_interval_minutes = 10;
        $this->is_popular            = false;
        $this->max_projects          = null;
        $this->max_keywords_per_project = null;
        
        // Reset limit properties
        $this->limit_projects        = 'unlimited';
        $this->limit_keywords        = 'unlimited';
        $this->limit_mentions        = '500000';
        $this->limit_users           = 'unlimited';
        $this->feat_ai               = false;
        $this->feat_rss              = false;
        $this->feat_api              = false;
        $this->feat_whitelabel       = false;
        
        $this->actorConfig           = [];
        $this->editingPackageId      = null;
        $this->resetValidation();
    }

    protected function setFlash(string $message, string $type = 'success'): void
    {
        $this->flash     = $message;
        $this->flashType = $type;

        $this->dispatch('admin-toast', payload: [
            'type'    => $type,
            'title'   => $message,
            'message' => '',
        ]);
    }

    public function dismissFlash(): void
    {
        $this->flash = '';
    }

    // ─── Render ───────────────────────────────────────────────────────────

    public function render()
    {
        if (! Schema::hasTable('packages')) {
            return view('livewire.admin.package-manager', [
                'packages' => new LengthAwarePaginator([], 0, 10),
                'allActors' => ApifyActor::where('status', 'active')->orderBy('platform')->orderBy('actor_name')->get(),
                'managingPackage' => null,
                'schemaMissing' => true,
            ]);
        }

        $packages = Package::query()
            ->when($this->search, fn($q) => $q->where('name', 'ilike', "%{$this->search}%")
                ->orWhere('description', 'ilike', "%{$this->search}%"))
            ->withCount(['projects', 'enabledActors as actors_count'])
            ->orderBy('name')
            ->paginate(10);

        $allActors = ApifyActor::where('status', 'active')->orderBy('platform')->orderBy('actor_name')->get();

        $managingPackage = $this->managingActorsPackageId
            ? Package::find($this->managingActorsPackageId)
            : null;

        return view('livewire.admin.package-manager', [
            'packages'        => $packages,
            'allActors'       => $allActors,
            'managingPackage' => $managingPackage,
            'schemaMissing'   => false,
        ]);
    }
}
