<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

class UsersManager extends Component
{
    public string $search = '';
    public bool $showForm = false;
    public bool $isEditing = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role = 'user';
    public string $status = 'active';

    public ?string $flashMessage = null;
    public ?string $flashType = null;
    public bool $confirmingDelete = false;
    public ?int $deleteId = null;

    protected function currentUser(): ?User
    {
        return auth()->user();
    }

    protected function adminOnly(): void
    {
        abort_unless($this->currentUser()?->isAdmin(), 403, 'Hanya admin yang dapat mengakses halaman ini.');
    }

    protected function rulesForSave(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];

        $rules['password'] = $this->isEditing
            ? ['nullable', 'string', 'min:8', 'confirmed']
            : ['required', 'string', 'min:8', 'confirmed'];

        return $rules;
    }

    public function render()
    {
        $this->adminOnly();

        $users = User::query()
            ->when($this->search, function ($query) {
                $search = trim($this->search);

                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        return view('livewire.admin.users-manager', [
            'users' => $users,
        ]);
    }

    public function create(): void
    {
        $this->adminOnly();

        $this->resetForm();
        $this->showForm = true;
        $this->isEditing = false;
    }

    public function edit(int $id): void
    {
        $this->adminOnly();

        $user = User::findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role ?? 'user';
        $this->status = $user->status ?? 'active';
        $this->password = '';
        $this->password_confirmation = '';
        $this->showForm = true;
        $this->isEditing = true;
    }

    public function save(): void
    {
        $this->adminOnly();

        $validated = $this->validate($this->rulesForSave());

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'status' => $validated['status'],
        ];

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        if ($this->isEditing && $this->editingId) {
            User::findOrFail($this->editingId)->update($data);
            $this->notify('success', 'User berhasil diperbarui.');
        } else {
            User::create($data);
            $this->notify('success', 'User berhasil ditambahkan.');
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function requestDelete(int $id): void
    {
        $this->adminOnly();

        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    public function deleteConfirmed(): void
    {
        $this->adminOnly();

        abort_unless($this->deleteId, 400);
        User::findOrFail($this->deleteId)->delete();

        $this->confirmingDelete = false;
        $this->deleteId = null;
        $this->notify('success', 'User berhasil dihapus.');
    }

    public function toggleStatus(int $id): void
    {
        $this->adminOnly();

        $user = User::findOrFail($id);
        $user->status = $user->status === 'active' ? 'inactive' : 'active';
        $user->save();

        $this->notify('success', 'Status user berhasil diperbarui.');
    }

    public function resetPassword(int $id): void
    {
        $this->adminOnly();

        $user = User::findOrFail($id);
        $user->update([
            'password' => Hash::make('password'),
        ]);

        $this->notify('success', 'Password berhasil di-reset ke "password".');
    }

    protected function notify(string $type, string $message): void
    {
        $this->flashType = $type;
        $this->flashMessage = $message;
        $payload = [
            'type' => $type,
            'title' => $message,
            'message' => '',
        ];

        if (method_exists($this, 'dispatchBrowserEvent')) {
            $this->dispatchBrowserEvent('admin-toast', $payload);
        }

        $this->dispatch('admin-toast', payload: $payload);
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId',
            'name',
            'email',
            'password',
            'password_confirmation',
            'role',
            'status',
        ]);

        $this->role = 'user';
        $this->status = 'active';
        $this->isEditing = false;
    }
}
