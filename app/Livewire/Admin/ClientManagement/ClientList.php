<?php

namespace App\Livewire\Admin\ClientManagement;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;

class ClientList extends Component
{
    use WithPagination;

    public $search = '';

    // Confirmation Modal States
    public bool $confirmingDelete = false;
    public ?int $deleteClientId = null;
    public string $deleteClientName = '';

    public bool $confirmingStatusChange = false;
    public ?int $statusClientId = null;
    public string $statusClientName = '';
    public string $targetStatus = '';

    public function mount()
    {
        abort_if(!auth()->check() || auth()->user()->isClient(), 403, 'Akses ditolak. Klien tidak dapat mengakses halaman ini.');
    }

    public function requestToggleStatus(int $clientId): void
    {
        $client = User::where('role', 'client')->findOrFail($clientId);
        $this->statusClientId = $client->id;
        $this->statusClientName = $client->name;
        $this->targetStatus = $client->status === 'active' ? 'inactive' : 'active';
        $this->confirmingStatusChange = true;
    }

    public function toggleStatusConfirmed(): void
    {
        if (!$this->statusClientId) {
            $this->confirmingStatusChange = false;
            return;
        }

        $client = User::where('role', 'client')->findOrFail($this->statusClientId);
        $client->status = $this->targetStatus;
        $client->save();

        $statusText = $client->status === 'active' ? 'diaktifkan' : 'dinonaktifkan';
        $this->confirmingStatusChange = false;
        $this->statusClientId = null;
        $this->statusClientName = '';
        $this->targetStatus = '';

        $message = "Status akun klien '{$client->name}' berhasil {$statusText}.";
        session()->flash('success', $message);
        $this->dispatch('admin-toast', type: 'success', title: 'Status Berubah', message: $message);
    }

    public function requestDelete(int $clientId): void
    {
        $client = User::where('role', 'client')->findOrFail($clientId);
        $this->deleteClientId = $client->id;
        $this->deleteClientName = $client->name;
        $this->confirmingDelete = true;
    }

    public function deleteConfirmed(): void
    {
        if (!$this->deleteClientId) {
            $this->confirmingDelete = false;
            return;
        }

        $client = User::where('role', 'client')->findOrFail($this->deleteClientId);
        $clientName = $client->name;

        // Hapus juga ClientSetting jika ada
        if ($client->clientSettings) {
            $client->clientSettings()->delete();
        }

        $client->delete();

        $this->confirmingDelete = false;
        $this->deleteClientId = null;
        $this->deleteClientName = '';

        $message = "Klien '{$clientName}' berhasil dihapus permanen.";
        session()->flash('success', $message);
        $this->dispatch('admin-toast', type: 'success', title: 'Klien Dihapus', message: $message);
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
