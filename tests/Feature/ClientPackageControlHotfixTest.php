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
        $client->load('allowedPackages'); // Wajib reload relation
        $this->assertEquals(5, $client->getMaxProjectEntitlement());
        
        // 2. PRO (NULL) + Enterprise (20) -> 20
        $this->proPackage->update(['max_projects' => null]);
        $this->enterprisePackage->update(['max_projects' => 20]);
        $client->load('allowedPackages');
        $this->assertEquals(20, $client->getMaxProjectEntitlement());
        
        // 3. PRO (NULL) + Enterprise (NULL) -> NULL
        $this->enterprisePackage->update(['max_projects' => null]);
        $client->load('allowedPackages');
        $this->assertNull($client->getMaxProjectEntitlement());
        
        // 4. No packages -> 0
        $client->allowedPackages()->sync([]);
        $client->load('allowedPackages');
        $this->assertEquals(0, $client->getMaxProjectEntitlement());
        
        // Restore enterprise package default for other tests
        $this->enterprisePackage->update(['max_projects' => 20]);
        $this->proPackage->update(['max_projects' => 5]);
    }

    public function test_real_project_create_boundary_and_keyword_limit()
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->mock(\App\Services\ContentMatchingService::class, function ($mock) {
            $mock->shouldReceive('resyncProjectContent')->andReturn([]);
        });

        $client = User::create(['name' => 'Client', 'email' => 'boundary@test.com', 'password' => Hash::make('password'), 'role' => 'client']);
        $client->clientSettings()->create(['max_projects' => 8, 'max_keywords_per_project' => 15]);
        $client->allowedPackages()->sync([$this->proPackage->id, $this->enterprisePackage->id]); // Entitlement = 20, tapi client max 8
        
        // Buat 7 proyek aktif
        for ($i = 0; $i < 7; $i++) {
            Project::create([
                'name' => "Project $i",
                'topics' => ['A'],
                'package_id' => $this->proPackage->id,
                'is_active' => true,
            ])->users()->attach($client->id);
        }
        
        // Proyek ke-8 menggunakan PRO (PRO limit 5, tapi Klien limit 8, ini HARUS SUCCESS)
        Livewire::actingAs($client)
            ->test(\App\Livewire\ProjectCreate::class)
            ->set('name', 'Project 8 Unik')
            ->set('topicsString', 'A, B, C')
            ->set('telegramChatId', '123456789')
            ->set('packageId', $this->proPackage->id)
            ->call('createProject')
            ->assertHasNoErrors();
            
        $this->assertDatabaseHas('projects', [
            'name' => 'Project 8 Unik',
        ]);
            
        // Proyek ke-9 akan ditolak karena max_projects = 8
        Livewire::actingAs($client)
            ->test(\App\Livewire\ProjectCreate::class)
            ->set('name', 'Project 9 Unik')
            ->set('topicsString', 'A, B, C')
            ->set('telegramChatId', '123456789')
            ->set('packageId', $this->proPackage->id)
            ->call('createProject')
            ->assertHasErrors(['name']);
            
        $this->assertDatabaseMissing('projects', [
            'name' => 'Project 9 Unik',
        ]);
            
        // Untuk test limit keyword, buat klien baru agar tidak terkena validasi limit jumlah project
        $keywordClient = User::create(['name' => 'Client K', 'email' => 'kw@test.com', 'password' => Hash::make('password'), 'role' => 'client']);
        $keywordClient->clientSettings()->create(['max_projects' => 8, 'max_keywords_per_project' => 15]);
        $keywordClient->allowedPackages()->sync([$this->proPackage->id, $this->enterprisePackage->id]);
        
        // Test limit keyword: Enterprise max=50, Client max=15. Input 16 -> Ditolak.
        Livewire::actingAs($keywordClient)
            ->test(\App\Livewire\ProjectCreate::class)
            ->set('name', 'Keyword Project Unik')
            ->set('topicsString', implode(', ', range(1, 16))) // 16 keywords
            ->set('telegramChatId', '123456789')
            ->set('packageId', $this->enterprisePackage->id)
            ->call('createProject')
            ->assertHasErrors(['topicsString']);
            
        $this->assertDatabaseMissing('projects', [
            'name' => 'Keyword Project Unik',
        ]);
            
        // Test limit keyword: Enterprise max=50, Client max=15. Input 15 -> Diterima.
        Livewire::actingAs($keywordClient)
            ->test(\App\Livewire\ProjectCreate::class)
            ->set('name', 'Keyword Project 15 Unik')
            ->set('topicsString', implode(', ', range(1, 15))) // 15 keywords
            ->set('telegramChatId', '123456789')
            ->set('packageId', $this->enterprisePackage->id)
            ->call('createProject')
            ->assertHasNoErrors();
            
        $this->assertDatabaseHas('projects', [
            'name' => 'Keyword Project 15 Unik',
        ]);
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

    public function test_client_settings_security_validation()
    {
        $client = User::create(['name' => 'C', 'email' => 'csec@test.com', 'password' => 'password', 'role' => 'client']);
        $client->clientSettings()->create([]);
        $client->allowedPackages()->sync([$this->proPackage->id]);
        
        $inactivePackage = Package::create([
            'name' => 'Inactive', 'price' => 0, 'is_active' => false, 'max_projects' => 10, 'max_keywords_per_project' => 10,
        ]);

        // Tes: package inactive
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Admin\ClientManagement\ClientSettings::class, ['user' => $client->id])
            ->set('allowedPackages', [$inactivePackage->id])
            ->call('saveSettings')
            ->assertHasErrors(['allowedPackages.0']);
            
        $this->assertFalse($client->fresh()->allowedPackages->contains($inactivePackage->id));

        // Tes: fake package ID
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Admin\ClientManagement\ClientSettings::class, ['user' => $client->id])
            ->set('allowedPackages', [999999])
            ->call('saveSettings')
            ->assertHasErrors(['allowedPackages.0']);
    }

    public function test_active_project_quota_only()
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->mock(\App\Services\ContentMatchingService::class, function ($mock) {
            $mock->shouldReceive('resyncProjectContent')->andReturn([]);
        });

        $client = User::create(['name' => 'C2', 'email' => 'cact@test.com', 'password' => 'password', 'role' => 'client']);
        $client->clientSettings()->create(['max_projects' => 5]);
        $client->allowedPackages()->sync([$this->proPackage->id]);
        
        // Buat 3 aktif, 2 inaktif
        for ($i = 0; $i < 3; $i++) {
            Project::create(['name' => "Act $i", 'topics' => ['A'], 'package_id' => $this->proPackage->id, 'is_active' => true])->users()->attach($client->id);
        }
        for ($i = 0; $i < 2; $i++) {
            Project::create(['name' => "Inact $i", 'topics' => ['A'], 'package_id' => $this->proPackage->id, 'is_active' => false])->users()->attach($client->id);
        }
        
        // Total project = 5. Aktif = 3. Limit = 5.
        // Project baru (ke-4 aktif) harus bisa
        Livewire::actingAs($client)
            ->test(\App\Livewire\ProjectCreate::class)
            ->set('name', 'Act 4 Unik')
            ->set('topicsString', 'A')
            ->set('telegramChatId', '123456789')
            ->set('packageId', $this->proPackage->id)
            ->call('createProject')
            ->assertHasNoErrors();
            
        $this->assertDatabaseHas('projects', [
            'name' => 'Act 4 Unik',
        ]);
            
        // Buat lagi manual 1 project aktif agar total aktif = 5
        Project::create(['name' => "Act 5", 'topics' => ['A'], 'package_id' => $this->proPackage->id, 'is_active' => true])->users()->attach($client->id);
        
        // Coba buat project aktif ke-6, ditolak
        Livewire::actingAs($client)
            ->test(\App\Livewire\ProjectCreate::class)
            ->set('name', 'Act 6 Unik')
            ->set('topicsString', 'A')
            ->set('telegramChatId', '123456789')
            ->set('packageId', $this->proPackage->id)
            ->call('createProject')
            ->assertHasErrors(['name']);
            
        $this->assertDatabaseMissing('projects', [
            'name' => 'Act 6 Unik',
        ]);
    }
}
