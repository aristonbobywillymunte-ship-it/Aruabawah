<?php

namespace App\Livewire\Admin;

use App\Models\BrandingSetting;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class BrandingManager extends Component
{
    use WithFileUploads;

    public string $app_name = '';
    public $app_logo = null;
    public ?string $current_logo_path = null;

    // UI state
    public ?string $flashMessage = null;
    public ?string $flashType = null;

    protected function adminOnly(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Akses ditolak.');
    }

    protected function branding(): BrandingSetting
    {
        return BrandingSetting::firstOrCreate(['id' => 1]);
    }

    protected function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'min:3', 'max:50'],
            'app_logo' => ['nullable', 'image', 'max:2048'], // max 2MB
        ];
    }

    public function mount(): void
    {
        $this->adminOnly();
        $this->loadBranding();
    }

    public function render()
    {
        $this->adminOnly();
        return view('livewire.admin.branding-manager', [
            'branding' => $this->branding(),
        ]);
    }

    public function loadBranding(): void
    {
        $branding = $this->branding();
        $this->app_name = (string) ($branding->app_name ?? 'ARUSBAWAH');
        $this->current_logo_path = $branding->app_logo_path;
        $this->app_logo = null;
    }

    public function save(): void
    {
        $this->adminOnly();
        $this->validate();

        $branding = $this->branding();
        $logoPath = $branding->app_logo_path;

        if ($this->app_logo) {
            // Delete old logo if exists
            if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                Storage::disk('public')->delete($logoPath);
            }
            // Store new logo publicly
            $logoPath = $this->app_logo->store('branding', 'public');
        }

        $branding->update([
            'app_name' => $this->app_name,
            'app_logo_path' => $logoPath,
        ]);

        \App\Helpers\AppBrandingHelper::clearCache();

        $this->loadBranding();
        $this->notify('success', 'Branding aplikasi berhasil diperbarui.');
    }

    public function deleteLogo(): void
    {
        $this->adminOnly();
        $branding = $this->branding();
        if ($branding->app_logo_path && Storage::disk('public')->exists($branding->app_logo_path)) {
            Storage::disk('public')->delete($branding->app_logo_path);
        }

        $branding->update([
            'app_logo_path' => null,
        ]);

        \App\Helpers\AppBrandingHelper::clearCache();

        $this->loadBranding();
        $this->notify('success', 'Logo branding berhasil dihapus.');
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

        $this->dispatch('admin-toast', payload: $payload);
    }
}
