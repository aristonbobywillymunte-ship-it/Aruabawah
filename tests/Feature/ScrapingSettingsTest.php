<?php

namespace Tests\Feature;

use App\Livewire\Admin\ScrapingSettings;
use App\Models\ScrapingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScrapingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_master_and_engine_switches_default_to_enabled_on_first_load(): void
    {
        $component = Livewire::actingAs($this->admin)->test(ScrapingSettings::class);

        $component->assertSet('is_active', true);
        $component->assertSet('google_news_enabled', true);
        $component->assertSet('manual_portal_enabled', true);
        $component->assertSet('apify_enabled', true);
    }

    public function test_admin_can_persist_engine_switches_and_master_switch(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ScrapingSettings::class)
            ->set('is_active', false)
            ->set('google_news_enabled', false)
            ->set('manual_portal_enabled', false)
            ->set('apify_enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('scraping_settings', [
            'id' => 1,
            'is_active' => false,
            'google_news_enabled' => false,
            'manual_portal_enabled' => false,
            'apify_enabled' => false,
        ]);
    }

    public function test_saved_values_reload_correctly_after_refresh(): void
    {
        ScrapingSetting::create([
            'id' => 1,
            'google_news_interval' => 15,
            'portal_crawling_interval' => 720,
            'limit_per_run' => 50,
            'timeout_seconds' => 30,
            'retry_limit' => 3,
            'retry_delay_minutes' => 10,
            'is_active' => false,
            'google_news_enabled' => false,
            'manual_portal_enabled' => true,
            'apify_enabled' => false,
            'enable_realtime' => true,
        ]);

        $component = Livewire::actingAs($this->admin)->test(ScrapingSettings::class);

        $component->assertSet('is_active', false);
        $component->assertSet('google_news_enabled', false);
        $component->assertSet('manual_portal_enabled', true);
        $component->assertSet('apify_enabled', false);
        $component->assertSet('google_news_interval', 15);
        $component->assertSet('portal_crawling_interval', 720);
    }

    public function test_changing_one_engine_switch_does_not_mutate_the_others(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ScrapingSettings::class)
            ->set('google_news_enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $setting = ScrapingSetting::firstOrFail();

        $this->assertFalse($setting->google_news_enabled);
        $this->assertTrue($setting->manual_portal_enabled);
        $this->assertTrue($setting->apify_enabled);
        $this->assertTrue($setting->is_active);
    }

    public function test_legacy_interval_fields_remain_intact_when_switches_are_saved(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ScrapingSettings::class)
            ->set('google_news_interval', 25)
            ->set('portal_crawling_interval', 480)
            ->set('limit_per_run', 75)
            ->set('timeout_seconds', 45)
            ->set('retry_limit', 4)
            ->set('retry_delay_minutes', 12)
            ->set('google_news_enabled', false)
            ->set('manual_portal_enabled', false)
            ->set('apify_enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('scraping_settings', [
            'id' => 1,
            'google_news_interval' => 25,
            'portal_crawling_interval' => 480,
            'limit_per_run' => 75,
            'timeout_seconds' => 45,
            'retry_limit' => 4,
            'retry_delay_minutes' => 12,
            'google_news_enabled' => false,
            'manual_portal_enabled' => false,
            'apify_enabled' => false,
        ]);
    }
}
