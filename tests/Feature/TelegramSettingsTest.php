<?php

namespace Tests\Feature;

use App\Livewire\Admin\TelegramSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TelegramSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);
    }

    public function test_it_rejects_bot_token_with_non_ascii_characters(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(TelegramSettings::class)
            ->call('openTestModal')
            ->set('bot_token', '8652374143:AAEauAAEmd75YZsoqøJJCpKInVV4EBg9TC8')
            ->set('default_chat_id', '-100123456789')
            ->call('saveGlobalSettings')
            ->assertHasErrors(['bot_token']);
    }

    public function test_it_normalizes_telegram_inputs_before_saving(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(TelegramSettings::class)
            ->call('openTestModal')
            ->set('bot_token', " 1234567890:ABCdefGhIJKlmNoPQRsTUVwxyZ \n")
            ->set('default_chat_id', "  -100123456789  \n")
            ->call('saveGlobalSettings')
            ->assertHasNoErrors()
            ->assertSet('bot_token', '1234567890:ABCdefGhIJKlmNoPQRsTUVwxyZ')
            ->assertSet('default_chat_id', '-100123456789');
    }

    public function test_it_saves_custom_project_recipients_successfully(): void
    {
        $project = \App\Models\Project::create([
            'name' => 'Kaltim Berdaulat',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(TelegramSettings::class)
            ->call('createRecipient')
            ->set('project_id', $project->id)
            ->set('chat_id', '1001882739')
            ->set('recipient_is_active', true)
            ->call('saveRecipient')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('project_telegram_recipients', [
            'project_id' => $project->id,
            'chat_id' => '1001882739',
            'is_active' => true,
        ]);
    }

    public function test_it_runs_message_transmission_test_correctly(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.telegram.org/bot*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200)
        ]);

        \App\Models\TelegramSetting::truncate();
        \App\Models\TelegramSetting::create([
            'id' => 1,
            'bot_token' => '9876543210:ZYXwvuTsrQPOnMlKjIHgFeDCba9876',
            'default_chat_id' => '840203231',
            'is_active' => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(TelegramSettings::class)
            ->set('test_chat_id', '840203231')
            ->set('test_message', 'Notif krisis!')
            ->call('runTestSend')
            ->assertHasNoErrors()
            ->assertSet('testResultStatus', 'success');
    }
}
