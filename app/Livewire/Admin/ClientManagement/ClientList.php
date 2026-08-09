<?php

namespace App\Livewire\Admin\ClientManagement;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;

class ClientList extends Component
{
    use WithPagination;

    public $search = '';

    public function mount()
    {
        abort_if(!auth()->check() || auth()->user()->isClient(), 403, 'Akses ditolak. Klien tidak dapat mengakses halaman ini.');
    }

    public function toggleStatus($clientId)
    {
        $client = User::where('role', 'client')->findOrFail($clientId);
        $client->status = $client->status === 'active' ? 'inactive' : 'active';
        $client->save();
        
        session()->flash('message', 'Status klien berhasil diubah.');
    }

    public function deleteClient($clientId)
    {
        $client = User::where('role', 'client')->findOrFail($clientId);
        
        // Hapus juga ClientSetting jika ada (bisa melalui relasi jika didefinisikan ON DELETE CASCADE)
        if ($client->clientSettings) {
            $client->clientSettings()->delete();
        }
        
        $client->delete();
        
        session()->flash('message', 'Klien berhasil dihapus.');
    }

    public function render()
    {
        $query = User::where('role', 'client');

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('email', 'ilike', '%' . $this->search . '%');
            });
        }

        $clients = $query->with('creator')->orderBy('created_at', 'desc')->paginate(15);

        return view('livewire.admin.client-management.client-list', [
            'clients' => $clients
        ]);
    }
}
