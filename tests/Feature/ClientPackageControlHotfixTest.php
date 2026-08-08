<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Package;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ClientPackageControlHotfixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        
        $this->manager = User::create([
            'name' => 'Manager',
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);
        
        $this->proPackage = Package::create([
            'name' => 'PRO',
            'price' => 100000,
            'is_active' => true,
            'max_projects' => 5,
            'max_keywords_per_project' => 25,
        ]);
        
        $this->enterprisePackage = Package::create([
            'name' => 'Enterprise',
            'price' => 500000,
            'is_active' => true,
            'max_projects' => 20,
            'max_keywords_per_project' => 50,
        ]);
        
        $this->unlimitedPackage = Package::create([
            'name' => 'Unlimited',
            'price' => 1000000,
            'is_active' => true,
            'max_projects' => null,
            'max_keywords_per_project' => null,
        ]);
    }

    public function test_client_settings_partial_save_prevention()
    {
        $client = User::create(['name' => 'Client', 'email' => 'c1@test.com', 'password' => Hash::make('password'), 'role' => 'client']);
        $client->clientSettings()->create([]);
        $client->allowedPackages()->sync([$this->proPackage->id]);
        
        // Simulasikan Livewire form
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Admin\ClientManagement\ClientSettings::class, ['user' => $client->id])
            ->set('max_projects', 100) // Melebihi Enterprise (20)
            ->set('allowedPackages', [$this->enterprisePackage->id])
            ->call('saveSettings')
            ->assertHasErrors(['max_projects']);
            
        // Verifikasi bahwa allowedPackages tetap PRO di database (TIDAK menjadi Enterprise)
        $this->assertTrue($client->fresh()->allowedPackages->contains($this->proPackage->id));
        $this->assertFalse($client->fresh()->allowedPackages->contains($this->enterprisePackage->id));
    }

    public function test_null_entitlement_logic()
    {
        // 1. PRO (5) + Enterprise (NULL) -> 5
        $client = User::create(['name' => 'C1', 'email' => 'c1x@test.com', 'password' => 'password', 'role' => 'client']);
        $this->enterprisePackage->update(['max_projects' => null]); // Ubah enterprise jadi null
        
        $client->allowedPackages()->sync([$this->proPackage->id, $this->enterprisePackage->id]);
        $this->assertEquals(5, $client->getMaxProjectEntitlement());
        
        // 2. PRO (NULL) + Enterprise (20) -> 20
        $this->proPackage->update(['max_projects' => null]);
        $this->enterprisePackage->update(['max_projects' => 20]);
        $this->assertEquals(20, $client->getMaxProjectEntitlement());
        
        // 3. PRO (NULL) + Enterprise (NULL) -> NULL
        $this->enterprisePackage->update(['max_projects' => null]);
        $this->assertNull($client->getMaxProjectEntitlement());
        
        // 4. No packages -> 0
        $client->allowedPackages()->sync([]);
        $this->assertEquals(0, $client->getMaxProjectEntitlement());
        
        // Restore enterprise package default for other tests
        $this->enterprisePackage->update(['max_projects' => 20]);
        $this->proPackage->update(['max_projects' => 5]);
    }

    public function test_real_project_create_boundary_and_keyword_limit()
    {
        $client = User::create(['name' => 'Client', 'email' => 'boundary@test.com', 'password' => Hash::make('password'), 'role' => 'client']);
        $client->clientSettings()->create(['max_projects' => 8, 'max_keywords_per_project' => 15]);
        $client->allowedPackages()->sync([$this->proPackage->id, $this->enterprisePackage->id]); // Entitlement = 20, tapi client max 8
        
        // Buat 7 proyek
        for ($i = 0; $i < 7; $i++) {
            Project::create([
                'name' => "Project $i",
                'topics' => ['A'],
                'package_id' => $this->proPackage->id,
            ])->users()->attach($client->id);
        }
        
        // Proyek ke-8 menggunakan PRO (PRO limit 5, tapi Klien limit 8, ini HARUS SUCCESS)
        Livewire::actingAs($client)
            ->test(\App\Livewire\ProjectCreate::class)
            ->set('name', 'Project 8')
            ->set('topicsString', 'A, B, C')
            ->set('packageId', $this->proPackage->id)
            ->call('submit')
            ->assertHasNoErrors(['name']);
            
        // Proyek ke-9 akan ditolak karena max_projects = 8
        Livewire::actingAs($client)
            ->test(\App\Livewire\ProjectCreate::class)
            ->set('name', 'Project 9')
            ->set('topicsString', 'A, B, C')
            ->set('packageId', $this->proPackage->id)
            ->call('submit')
            ->assertHasErrors(['name']);
            
        // Test limit keyword: Enterprise max=50, Client max=15. Input 16 -> Ditolak.
        Livewire::actingAs($client)
            ->test(\App\Livewire\ProjectCreate::class)
            ->set('name', 'Keyword Project')
            ->set('topicsString', implode(', ', range(1, 16))) // 16 keywords
            ->set('packageId', $this->enterprisePackage->id)
            ->call('submit')
            ->assertHasErrors(['topicsString']);
            
        // Test limit keyword: Enterprise max=50, Client max=15. Input 15 -> Diterima.
        Livewire::actingAs($client)
            ->test(\App\Livewire\ProjectCreate::class)
            ->set('name', 'Keyword Project 15')
            ->set('topicsString', implode(', ', range(1, 15))) // 15 keywords
            ->set('packageId', $this->enterprisePackage->id)
            ->call('submit')
            ->assertHasNoErrors(['topicsString']);
    }

    public function test_real_client_create_logic()
    {
        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Admin\ClientManagement\ClientCreate::class)
            ->set('name', 'New Client')
            ->set('email', 'newclient@test.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('createClient');
            
        $client = User::where('email', 'newclient@test.com')->first();
        
        $this->assertNotNull($client);
        $this->assertEquals('client', $client->role);
        $this->assertEquals($this->manager->id, $client->parent_user_id);
        
        $settings = $client->clientSettings;
        $this->assertNotNull($settings);
        $this->assertTrue($settings->can_create_projects);
        $this->assertFalse($settings->can_edit_projects);
        $this->assertFalse($settings->can_delete_projects);
    }
}
