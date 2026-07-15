<?php

namespace Tests\Feature;

use App\Livewire\Admin\BrandingManager;
use App\Models\User;
use App\Models\BrandingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
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
    }

    public function test_non_admin_cannot_access_branding_manager(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.branding'))
            ->assertStatus(403);
    }

    public function test_admin_can_load_branding_manager(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(BrandingManager::class)
            ->assertSet('app_name', 'ARUSBAWAH')
            ->assertSet('current_logo_path', null);
    }

    public function test_admin_can_access_branding_page(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('admin.branding'))
            ->assertStatus(200)
            ->assertSee('Branding Aplikasi');
    }

    public function test_admin_can_update_app_name(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(BrandingManager::class)
            ->set('app_name', 'My Custom App')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('app_name', 'My Custom App');

        $this->assertEquals('My Custom App', \App\Helpers\AppBrandingHelper::getAppName());
    }

    public function test_admin_can_upload_custom_logo(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.png');

        Livewire::actingAs($this->adminUser)
            ->test(BrandingManager::class)
            ->set('app_logo', $file)
            ->call('save')
            ->assertHasNoErrors();

        $branding = BrandingSetting::first();
        $this->assertNotNull($branding->app_logo_path);
        Storage::disk('public')->assertExists($branding->app_logo_path);
    }
}
