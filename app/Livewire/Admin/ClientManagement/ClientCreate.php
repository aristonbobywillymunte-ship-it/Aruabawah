<?php

namespace App\Livewire\Admin\ClientManagement;

use Livewire\Component;
use App\Models\User;
use App\Models\ClientSetting;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.admin')]
#[Title('Tambah Klien Baru')]
class ClientCreate extends Component
{
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';

    public function mount()
    {
        abort_if(!auth()->check() || auth()->user()->isClient(), 403, 'Akses ditolak.');
    }

    public function createClient()
    {
        $this->validate([
            'name' => 'required|min:3',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ], [
            'name.required' => 'Nama wajib diisi.',
            'name.min' => 'Nama minimal 3 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
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

        session()->flash('message', 'Klien berhasil dibuat.');
        
        return $this->redirectRoute('admin.clients', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.client-management.client-create');
    }
}
