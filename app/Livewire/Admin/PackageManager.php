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
    public bool $is_active = true;
    public bool $use_portal = true;

    // ─── Actor Configuration (per paket) ─────────────────────────────────
    /** @var array<int, array{is_enabled: bool, cost_per_run_usd: string}> keyed by apify_actor_id */
    public array $actorConfig = [];

    // ─── Confirmation ─────────────────────────────────────────────────────
    public ?int $confirmDeleteId = null;
    public string $flash = '';
    public string $flashType = 'success'; // success | error

    // ─── Search ───────────────────────────────────────────────────────────
    public string $search = '';

    protected function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
            'use_portal'  => 'boolean',
        ];
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
        $this->editingPackageId = $id;
        $this->name        = $pkg->name;
        $this->description = $pkg->description ?? '';
        $this->is_active   = $pkg->is_active;
        $this->use_portal  = (bool) ($pkg->use_portal ?? true);
        $this->loadActorConfig($id);
        $this->view = 'form';
    }

    public function savePackage(): void
    {
        $this->validate();

        if ($this->editingPackageId) {
            $pkg = Package::findOrFail($this->editingPackageId);
            $pkg->update([
                'name'        => trim($this->name),
                'description' => trim($this->description) ?: null,
                'is_active'   => $this->is_active,
                'use_portal'  => $this->use_portal,
            ]);
            $this->setFlash('Paket berhasil diperbarui.', 'success');
        } else {
            $pkg = Package::create([
                'name'        => trim($this->name),
                'description' => trim($this->description) ?: null,
                'is_active'   => $this->is_active,
                'use_portal'  => $this->use_portal,
            ]);
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

            $syncData[(int) $actorId] = [
                'is_enabled'       => (bool) ($config['is_enabled'] ?? false),
                'cost_per_run_usd' => $cost,
            ];
        }

        $pkg->actors()->sync($syncData);
    }

    public function saveActors(): void
    {
        $pkg = Package::findOrFail($this->managingActorsPackageId);
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

    // ─── Helpers ──────────────────────────────────────────────────────────

    protected function resetForm(): void
    {
        $this->name            = '';
        $this->description     = '';
        $this->is_active       = true;
        $this->use_portal      = true;
        $this->actorConfig     = [];
        $this->editingPackageId = null;
        $this->resetValidation();
    }

    protected function setFlash(string $message, string $type = 'success'): void
    {
        $this->flash     = $message;
        $this->flashType = $type;
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
                'allActors' => ApifyActor::orderBy('platform')->orderBy('actor_name')->get(),
                'managingPackage' => null,
                'schemaMissing' => true,
            ]);
        }

        $packages = Package::query()
            ->when($this->search, fn($q) => $q->where('name', 'ilike', "%{$this->search}%")
                ->orWhere('description', 'ilike', "%{$this->search}%"))
            ->withCount(['projects', 'actors' => fn($q) => $q->wherePivot('is_enabled', true)])
            ->orderBy('name')
            ->paginate(10);

        $allActors = ApifyActor::orderBy('platform')->orderBy('actor_name')->get();

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
