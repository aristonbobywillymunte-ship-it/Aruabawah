<?php

namespace App\Livewire\Admin\ClientManagement;

use Livewire\Component;
use App\Models\User;
use App\Models\Package;
use App\Models\ClientSetting;
use Livewire\Attributes\Layout;

#[Layout('admin.layouts.app')]
class ClientSettings extends Component
{
    public $client;
    
    // Permission Settings
    public $can_create_projects;
    public $can_edit_projects;
    public $can_delete_projects;
    public $max_projects;
    public $max_keywords_per_project;

    // Package Permissions
    public $allowedPackages = [];

    public function mount(User $user)
    {
        abort_if(!auth()->check() || auth()->user()->isClient(), 403, 'Akses ditolak.');

        if (!$user->isClient()) {
            abort(404, 'User ini bukan klien.');
        }

        $this->client = $user;
        
        $settings = $user->clientSettings;
        if (!$settings) {
            $settings = ClientSetting::create(['user_id' => $user->id]);
        }

        $this->can_create_projects = $settings->can_create_projects;
        $this->can_edit_projects = $settings->can_edit_projects;
        $this->can_delete_projects = $settings->can_delete_projects;
        $this->max_projects = $settings->max_projects;
        $this->max_keywords_per_project = $settings->max_keywords_per_project;

        $this->allowedPackages = $user->allowedPackages()->pluck('packages.id')->toArray();
    }

    public function saveSettings()
    {
        $this->validate([
            'max_projects' => 'nullable|integer|min:1',
            'max_keywords_per_project' => 'nullable|integer|min:1',
            'allowedPackages' => 'array',
            'allowedPackages.*' => [
                'required',
                'integer',
                \Illuminate\Validation\Rule::exists('packages', 'id')->where(function ($query) {
                    $query->where('is_active', true);
                }),
            ],
        ]);

        // 1. Ambil candidate packages TANPA mengubah database
        $candidatePackages = \App\Models\Package::whereIn('id', $this->allowedPackages)->get();
        
        // 2. Hitung entitlement
        $entitlement = User::calculateMaxProjectEntitlement($candidatePackages);
        
        $inputMaxProjects = empty($this->max_projects) ? null : (int) $this->max_projects;

        // 3. Validasi
        if ($entitlement !== null && $inputMaxProjects !== null) {
            if ($inputMaxProjects > $entitlement) {
                $this->addError('max_projects', 'Batas maksimal proyek tidak boleh melebihi batas dari paket yang diizinkan (' . $entitlement . ').');
                return;
            }
        }

        // 4. Update di dalam transaksi DB
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($inputMaxProjects) {
                $this->client->clientSettings()->update([
                    'can_create_projects' => $this->can_create_projects,
                    'can_edit_projects' => $this->can_edit_projects,
                    'can_delete_projects' => $this->can_delete_projects,
                    'max_projects' => $inputMaxProjects,
                    'max_keywords_per_project' => empty($this->max_keywords_per_project) ? null : (int) $this->max_keywords_per_project,
                ]);

                $this->client->allowedPackages()->sync($this->allowedPackages);
            });
            
            session()->flash('message', 'Pengaturan klien berhasil disimpan.');
        } catch (\Exception $e) {
            session()->flash('error', 'Terjadi kesalahan saat menyimpan pengaturan.');
        }
    }

    public function render()
    {
        return view('livewire.admin.client-management.client-settings', [
            'packages' => Package::where('is_active', true)->get()
        ]);
    }
}
