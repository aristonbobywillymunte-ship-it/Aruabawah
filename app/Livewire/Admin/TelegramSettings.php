<?php

namespace App\Livewire\Admin;

use App\Models\Project;
use App\Models\ProjectTelegramRecipient;
use App\Models\TelegramSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Livewire\WithPagination;

class TelegramSettings extends Component
{
    use WithPagination;

    // Search
    public string $search = '';

    // Global settings fields
    public string $bot_token = '';
    public string $default_chat_id = '';
    public bool $is_active = false;

    // Project recipient fields
    public bool $showRecipientModal = false;
    public bool $editingRecipient = false;
    public ?int $editingRecipientId = null;
    public ?int $project_id = null;
    public string $chat_id = '';
    public bool $recipient_is_active = true;

    // Test send fields
    public bool $showTestModal = false;
    public string $test_chat_id = '';
    public string $test_message = 'Uji coba pengiriman notifikasi krisis dari Arusbawah Media Intelligence.';
    public string $testResultStatus = '';
    public string $testResultError = '';

    // UI Feedback
    public ?string $flashMessage = null;
    public ?string $flashType = null;
    public bool $confirmingDelete = false;
    public ?int $deleteId = null;

    protected function adminOnly(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    protected function setting(): TelegramSetting
    {
        return TelegramSetting::firstOrCreate(['id' => 1]);
    }

    protected function normalizeTelegramTokenInput(?string $value): string
    {
        return preg_replace('/\s+/u', '', trim((string) $value)) ?? '';
    }

    protected function normalizeTelegramChatIdInput(?string $value): string
    {
        return preg_replace('/\s+/u', '', trim((string) $value)) ?? '';
    }

    public function mount(): void
    {
        $this->adminOnly();
        $this->loadGlobalSettings();
    }

    public function render()
    {
        $this->adminOnly();

        $recipients = ProjectTelegramRecipient::query()
            ->with('project')
            ->when($this->search, function ($query) {
                $query->whereHas('project', function ($inner) {
                    $inner->where('name', 'like', '%' . $this->search . '%');
                })->orWhere('chat_id', 'like', '%' . $this->search . '%');
            })
            ->paginate(10);

        return view('livewire.admin.telegram-settings', [
            'recipients' => $recipients,
            'projects' => Project::orderBy('name')->get(),
            'setting' => $this->setting(),
        ]);
    }

    public function loadGlobalSettings(): void
    {
        $setting = $this->setting();
        $this->bot_token = $setting->bot_token ?? '';
        $this->default_chat_id = $setting->default_chat_id ?? '';
        $this->is_active = (bool) ($setting->is_active ?? false);
    }

    public function saveGlobalSettings(): void
    {
        $this->adminOnly();

        $this->bot_token = $this->normalizeTelegramTokenInput($this->bot_token);
        $this->default_chat_id = $this->normalizeTelegramChatIdInput($this->default_chat_id);

        $this->validate([
            'bot_token' => ['nullable', 'string', 'max:255', 'regex:/^\d{5,}:[A-Za-z0-9_-]{20,}$/'],
            'default_chat_id' => ['nullable', 'string', 'max:255', 'regex:/^-?\d+$/'],
            'is_active' => ['boolean'],
        ], [
            'bot_token.regex' => 'Format token Telegram tidak valid. Gunakan token asli tanpa spasi atau karakter asing.',
            'default_chat_id.regex' => 'Chat/Group ID harus berupa angka dan boleh diawali tanda minus.',
        ]);

        $setting = $this->setting();
        $setting->update([
            'bot_token' => $this->bot_token ?: null,
            'default_chat_id' => $this->default_chat_id ?: null,
            'is_active' => $this->is_active,
        ]);

        $this->notify('success', 'Konfigurasi global Telegram berhasil disimpan.');
    }

    public function toggleGlobalStatus(): void
    {
        $this->adminOnly();
        $setting = $this->setting();
        $setting->is_active = !$setting->is_active;
        $setting->save();
        $this->is_active = $setting->is_active;

        $this->notify('success', 'Status bot Telegram berhasil diperbarui.');
    }

    // Custom Recipient actions
    public function createRecipient(): void
    {
        $this->adminOnly();
        $this->resetRecipientForm();
        $this->showRecipientModal = true;
        $this->editingRecipient = false;
    }

    public function editRecipient(int $id): void
    {
        $this->adminOnly();
        $this->resetRecipientForm();

        $rec = ProjectTelegramRecipient::findOrFail($id);
        $this->editingRecipientId = $rec->id;
        $this->project_id = $rec->project_id;
        $this->chat_id = $rec->chat_id;
        $this->recipient_is_active = $rec->is_active;

        $this->editingRecipient = true;
        $this->showRecipientModal = true;
    }

    public function saveRecipient(): void
    {
        $this->adminOnly();

        $this->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'chat_id' => ['required', 'string', 'max:255'],
            'recipient_is_active' => ['boolean'],
        ]);

        $exists = ProjectTelegramRecipient::where('project_id', $this->project_id)
            ->where('chat_id', $this->chat_id)
            ->where('id', '!=', $this->editingRecipientId)
            ->exists();

        if ($exists) {
            $this->addError('chat_id', 'Tautan proyek dan chat ID ini sudah ada.');
            return;
        }

        $payload = [
            'project_id' => $this->project_id,
            'chat_id' => $this->chat_id,
            'is_active' => $this->recipient_is_active,
        ];

        if ($this->editingRecipient && $this->editingRecipientId) {
            ProjectTelegramRecipient::findOrFail($this->editingRecipientId)->update($payload);
            $this->notify('success', 'Penerima khusus proyek berhasil diperbarui.');
        } else {
            ProjectTelegramRecipient::create($payload);
            $this->notify('success', 'Penerima khusus proyek berhasil ditambahkan.');
        }

        $this->showRecipientModal = false;
        $this->resetRecipientForm();
    }

    public function toggleRecipientStatus(int $id): void
    {
        $this->adminOnly();
        $rec = ProjectTelegramRecipient::findOrFail($id);
        $rec->is_active = !$rec->is_active;
        $rec->save();

        $this->notify('success', 'Status penerima khusus berhasil diperbarui.');
    }

    public function requestDeleteRecipient(int $id): void
    {
        $this->adminOnly();
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    public function deleteRecipientConfirmed(): void
    {
        $this->adminOnly();
        if ($this->deleteId) {
            ProjectTelegramRecipient::findOrFail($this->deleteId)->delete();
            $this->notify('success', 'Penerima khusus berhasil dihapus.');
        }
        $this->confirmingDelete = false;
        $this->deleteId = null;
    }

    // Real Telegram Message transmission test
    public function openTestModal(): void
    {
        $this->adminOnly();
        $this->loadGlobalSettings();
        $this->test_chat_id = $this->default_chat_id ?: '';
        $this->testResultStatus = '';
        $this->testResultError = '';
        $this->showTestModal = true;
    }

    public function runTestSend(): void
    {
        $this->adminOnly();
        $this->loadGlobalSettings();
        $this->bot_token = $this->normalizeTelegramTokenInput($this->bot_token);
        $this->test_chat_id = $this->normalizeTelegramChatIdInput($this->test_chat_id);

        $this->validate([
            'test_chat_id' => ['required', 'string', 'max:255', 'regex:/^-?\d+$/'],
            'test_message' => ['required', 'string', 'max:1000'],
        ], [
            'test_chat_id.regex' => 'Chat/Group ID uji harus berupa angka dan boleh diawali tanda minus.',
        ]);

        $testSetting = new TelegramSetting([
            'bot_token' => $this->bot_token ?: null,
            'default_chat_id' => $this->test_chat_id ?: null,
            'is_active' => $this->is_active,
        ]);

        $credentialStatus = $testSetting->notificationCredentialStatus();
        if (! $credentialStatus['ready']) {
            $this->testResultStatus = 'failed';
            $this->testResultError = 'Gagal mengirim: Kredensial Telegram belum valid (' . implode(', ', $credentialStatus['issues']) . ').';
            $this->notify('error', $this->testResultError);
            return;
        }

        try {
            // Real Telegram API Request Call
            $response = Http::timeout(10)
                ->post("https://api.telegram.org/bot{$this->bot_token}/sendMessage", [
                    'chat_id' => $this->test_chat_id,
                    'text' => $this->test_message
                ]);

            if ($response->successful()) {
                $this->testResultStatus = 'success';
                $this->testResultError = '';
                Log::channel('telegram')->info('Telegram test send succeeded', [
                    'provider' => 'telegram',
                    'status' => 'success',
                    'chat_id' => $this->test_chat_id,
                ]);
                $this->notify('success', 'Pesan uji coba berhasil dikirim ke Telegram.');
            } else {
                throw new \Exception('Telegram API Response ' . $response->status() . ': ' . $response->body());
            }
        } catch (\Throwable $e) {
            $this->testResultStatus = 'failed';
            $this->testResultError = 'Gagal mengirim pesan Telegram. Periksa token, chat ID, dan koneksi provider.';
            Log::channel('telegram')->warning('Telegram test send failed', [
                'provider' => 'telegram',
                'status' => 'failed',
                'error_code' => class_basename($e),
            ]);
            $this->notify('error', $this->testResultError);
        }
    }

    public function closeRecipientModal(): void
    {
        $this->showRecipientModal = false;
        $this->resetRecipientForm();
    }

    protected function resetRecipientForm(): void
    {
        $this->editingRecipient = false;
        $this->editingRecipientId = null;
        $this->project_id = null;
        $this->chat_id = '';
        $this->recipient_is_active = true;
        $this->resetErrorBag();
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
}
