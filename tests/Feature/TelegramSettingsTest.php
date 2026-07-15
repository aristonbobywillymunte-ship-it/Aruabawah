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
}
