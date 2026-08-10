<?php

namespace Tests\Feature;

use App\Models\ApifyActor;
use App\Models\ApifySetting;
use App\Models\User;
use App\Services\ApifyActorRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminApifyConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->regularUser = User::factory()->create([
            'role' => 'user',
        ]);

        // Setup base settings
        ApifySetting::create([
            'api_token' => 'apify_api_testtoken123456',
            'connection_status' => 'connected',
        ]);
    }

    public function test_non_admins_cannot_access_apify_configuration_page()
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.apify'))
            ->assertStatus(403);
    }

    public function test_admins_can_access_apify_configuration_page()
    {
        $this->actingAs($this->adminUser)
            ->get(route('admin.apify'))
            ->assertStatus(200)
            ->assertSee('Konfigurasi Scraper Apify')
            ->assertDontSee('Konfigurasi Performa & Jadwal')
            ->assertDontSee('Interval Scraping')
            ->assertSee('Aktor Bawaan Sistem')
            ->assertDontSee('Legacy / Inactive');
    }

    public function test_actor_form_does_not_expose_interval_minutes_as_editable_state()
    {
        $this->assertFalse(property_exists(\App\Livewire\Admin\ApifyConfiguration::class, 'interval_minutes'));
    }

    public function test_registry_sync_keeps_primary_actors_and_uses_hashtag_instagram_actor()
    {
        $registry = app(ApifyActorRegistry::class);

        $synced = $registry->syncManagedActors();

        $this->assertCount(6, $synced->filter(fn ($actor) => $registry->isPrimarySlug($actor->actor_slug)));

        foreach ($registry->primaryActors() as $actorDef) {
            $this->assertDatabaseHas('apify_actors', [
                'actor_slug' => $actorDef['actor_slug'],
                'status' => 'active',
            ]);
        }

        $this->assertTrue($registry->isPrimarySlug('apify/instagram-hashtag-scraper'));
        $this->assertFalse($registry->isLegacySlug('apify/instagram-hashtag-scraper'));
    }

    public function test_admin_can_toggle_actor_status()
    {
        $actor = ApifyActor::create([
            'platform' => 'Instagram',
            'actor_name' => 'Instagram Hashtag Scraper',
            'actor_slug' => 'apify/instagram-hashtag-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 20,
            'memory_limit' => 1024,
            'range_mode' => '7d',
            'keyword_field_mapping' => 'hashtags',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\ApifyConfiguration::class)
            ->call('requestToggleActorStatus', $actor->id)
            ->call('toggleActorStatusConfirmed')
            ->assertHasNoErrors();

        $this->assertEquals('inactive', $actor->fresh()->status);
    }

    public function test_save_actor_slug_must_be_in_whitelist()
    {
        // Whitelisted slug should succeed
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\ApifyConfiguration::class)
            ->set('platform', 'Instagram')
            ->set('actorName', 'Instagram Hashtag Scraper')
            ->set('actorSlug', 'apify/instagram-hashtag-scraper')
            ->set('functionType', 'Search Post')
            ->set('keyword_field_mapping', 'hashtags')
            ->set('defaultLimit', 50)
            ->set('instagram_results_type', 'posts')
            ->set('instagram_results_limit', 50)
            ->set('memory_limit', 1024)
            ->set('range_mode', '7d')
            ->set('priority', 1)
            ->call('saveActor')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('apify_actors', [
            'actor_slug' => 'apify/instagram-hashtag-scraper',
        ]);

        // Non-whitelisted slug should fail validation.
        // Facebook platform is used here because Instagram auto-resolves unknown slugs to a
        // known default definition (by design), whereas Facebook preserves whatever slug the user sets.
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\ApifyConfiguration::class)
            ->set('platform', 'Facebook')
            ->set('actorName', 'Random Scraper')
            ->set('actorSlug', 'some-random/custom-scraper')
            ->set('functionType', 'Search Post')
            ->set('keyword_field_mapping', 'searchQueries')
            ->set('defaultLimit', 50)
            ->set('facebook_use_apify_proxy', true)
            ->set('facebook_post_time_range', '24h')
            ->set('facebook_max_posts', 50)
            ->set('memory_limit', 1024)
            ->set('range_mode', '7d')
            ->set('priority', 1)
            ->call('saveActor')
            ->assertHasErrors(['actorSlug']);
    }

    public function test_saving_actor_configuration_preserves_legacy_interval_minutes_column()
    {
        $actor = ApifyActor::create([
            'platform' => 'Instagram',
            'actor_name' => 'Instagram Hashtag Scraper',
            'actor_slug' => 'apify/instagram-hashtag-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 20,
            'interval_minutes' => 240,
            'memory_limit' => 1024,
            'range_mode' => '7d',
            'keyword_field_mapping' => 'hashtags',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\ApifyConfiguration::class)
            ->call('editActor', $actor->id)
            ->set('memory_limit', 2048)
            ->set('priority', 2)
            ->call('saveActor')
            ->assertHasNoErrors();

        $saved = $actor->fresh();

        $this->assertSame(240, (int) $saved->interval_minutes);
        $this->assertSame(2048, (int) $saved->memory_limit);
        $this->assertSame(2, (int) $saved->priority);
    }
}
