<?php

namespace App\Livewire\Admin\ClientManagement;

use Livewire\Component;
use App\Models\User;
use App\Models\Package;
use App\Models\ClientSetting;
use Illuminate\Support\Facades\Hash;

class ClientCreate extends Component
{
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public array $selectedPackages = [];

    public function mount()
    {
        abort_if(!auth()->check() || auth()->user()->isClient(), 403, 'Akses ditolak.');

        // Default: otomatis pilih seluruh paket aktif yang tersedia
        $this->selectedPackages = Package::where('is_active', true)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();
    }

    public function createClient()
    {
        $this->validate([
            'name' => 'required|min:3',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'selectedPackages' => 'required|array|min:1',
        ], [
            'name.required' => 'Nama wajib diisi.',
            'name.min' => 'Nama minimal 3 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'selectedPackages.required' => 'Pilih minimal satu paket monitoring untuk klien ini.',
            'selectedPackages.min' => 'Pilih minimal satu paket monitoring untuk klien ini.',
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'role' => 'client',
            'status' => 'active',
            'parent_user_id' => auth()->id(),
        ]);

        ClientSetting::create([
            'user_id' => $user->id,
            'can_create_projects' => true,
            'can_edit_projects' => false,
            'can_delete_projects' => false,
        ]);

        // Hubungkan paket monitoring yang diizinkan untuk klien ini
        $user->allowedPackages()->sync($this->selectedPackages);

        session()->flash('success', "Akun klien '{$user->name}' berhasil dibuat dengan akses paket monitoring.");
        
        return $this->redirectRoute('admin.clients', navigate: true);
    }

    public function render()
    {
        $packages = Package::where('is_active', true)->orderBy('price', 'asc')->get();

        return view('livewire.admin.client-management.client-create', [
            'packages' => $packages,
        ]);
    }
}
