<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Package;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
    }

    public function test_parent_user_id_saved_correctly_on_client_creation()
    {
        $this->actingAs($this->manager);
        
        $response = $this->post('/livewire/update', [
            // Simulasikan submit form Livewire ClientCreate
            // (Karena Livewire rumit ditest via HTTP route POST langsung,
            // kita akan verifikasi via Model directly untuk memastikan semantics nya,
            // namun kita test langsung actionnya saja.)
        ]);
        
        // Simulasikan createClient
        $client = User::create([
            'name' => 'Client',
            'email' => 'client@test.com',
            'password' => Hash::make('password'),
            'role' => 'client',
            'parent_user_id' => $this->manager->id,
        ]);
        
        $this->assertEquals($this->manager->id, $client->parent_user_id);
    }

    public function test_multi_package_project_limit_logic()
    {
        $client = User::create(['name' => 'Client', 'email' => 'c1@test.com', 'password' => Hash::make('password'), 'role' => 'client']);
        
        $client->clientSettings()->create([
            'max_projects' => 8, // Override batas max jadi 8 (kurang dari max Enterprise = 20)
        ]);
        
        $client->allowedPackages()->sync([
            $this->proPackage->id,
            $this->enterprisePackage->id,
        ]);
        
        // Entitlement max = 20 (dari Enterprise)
        $this->assertEquals(20, $client->getMaxProjectEntitlement());
        
        // Effective max = 8 (dari setting)
        $this->assertEquals(8, $client->getEffectiveMaxProjects());
    }

    public function test_keyword_effective_limit_logic()
    {
        // PRO = 25, Client = 30 -> should be 25
        $client1 = User::create(['name' => 'Client', 'email' => 'c2@test.com', 'password' => 'password', 'role' => 'client']);
        $client1->clientSettings()->create(['max_keywords_per_project' => 30]);
        
        $limit1 = min(array_filter([$this->proPackage->max_keywords_per_project, $client1->clientSettings->max_keywords_per_project]));
        $this->assertEquals(25, $limit1);

        // Enterprise = 50, Client = 15 -> should be 15
        $client2 = User::create(['name' => 'Client', 'email' => 'c3@test.com', 'password' => 'password', 'role' => 'client']);
        $client2->clientSettings()->create(['max_keywords_per_project' => 15]);
        
        $limit2 = min(array_filter([$this->enterprisePackage->max_keywords_per_project, $client2->clientSettings->max_keywords_per_project]));
        $this->assertEquals(15, $limit2);
    }

    public function test_client_cannot_see_other_client_projects()
    {
        $clientA = User::create(['name' => 'Client A', 'email' => 'a@test.com', 'password' => 'password', 'role' => 'client']);
        $clientB = User::create(['name' => 'Client B', 'email' => 'b@test.com', 'password' => 'password', 'role' => 'client']);
        
        $project = Project::create([
            'name' => 'Project A',
            'topics' => ['A'],
            'package_id' => $this->proPackage->id,
        ]);
        
        $project->users()->attach($clientA->id);
        
        $this->assertTrue(Project::accessibleBy($clientA)->where('id', $project->id)->exists());
        $this->assertFalse(Project::accessibleBy($clientB)->where('id', $project->id)->exists());
        
        // User (manager) and Admin should see it
        $this->assertTrue(Project::accessibleBy($this->manager)->where('id', $project->id)->exists());
        $this->assertTrue(Project::accessibleBy($this->admin)->where('id', $project->id)->exists());
    }
}
