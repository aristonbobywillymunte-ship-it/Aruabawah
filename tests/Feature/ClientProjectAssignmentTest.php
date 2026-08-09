<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Package;
use App\Models\ClientSetting;
use Livewire\Livewire;
use App\Livewire\Admin\ClientManagement\ClientSettings;
use Illuminate\Support\Facades\Hash;

class ClientProjectAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $client;
    protected $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin'
        ]);
        
        $this->client = User::create([
            'name' => 'Client Test',
            'email' => 'client@test.com',
            'password' => Hash::make('password'),
            'role' => 'client'
        ]);
        
        $this->package = Package::create([
            'name' => 'Test Package',
            'price' => 100000,
            'is_active' => true,
            'max_projects' => 2,
        ]);

        ClientSetting::create([
            'user_id' => $this->client->id,
            'max_projects' => null, // fallback to package
        ]);
        
        $this->client->allowedPackages()->attach($this->package->id);
    }

    public function test_internal_user_can_assign_project_to_client()
    {
        $project = Project::create([
            'name' => 'Test Proj 1', 
            'package_id' => $this->package->id,
            'is_active' => true,
        ]);
        
        Livewire::actingAs($this->admin)
            ->test(ClientSettings::class, ['user' => $this->client])
            ->set('selectedProjectId', $project->id)
            ->call('assignProject')
            ->assertHasNoErrors()
            ->assertSee('Proyek berhasil ditambahkan ke klien.');

        $this->assertDatabaseHas('project_user', [
            'user_id' => $this->client->id,
            'project_id' => $project->id,
        ]);
    }

    public function test_assigning_same_project_twice_does_not_duplicate_pivot()
    {
        $project = Project::create([
            'name' => 'Test Proj 2', 
            'package_id' => $this->package->id,
            'is_active' => true,
        ]);
        $this->client->projects()->attach($project->id);

        Livewire::actingAs($this->admin)
            ->test(ClientSettings::class, ['user' => $this->client])
            ->set('selectedProjectId', $project->id)
            ->call('assignProject');

        $count = \DB::table('project_user')
            ->where('user_id', $this->client->id)
            ->where('project_id', $project->id)
            ->count();
            
        $this->assertEquals(1, $count);
    }

    public function test_internal_user_can_detach_project_without_deleting_it()
    {
        $project = Project::create([
            'name' => 'Test Proj 3', 
            'package_id' => $this->package->id,
            'is_active' => true,
        ]);
        $this->client->projects()->attach($project->id);

        Livewire::actingAs($this->admin)
            ->test(ClientSettings::class, ['user' => $this->client])
            ->call('detachProject', $project->id)
            ->assertSee('Proyek berhasil dilepas dari klien.');

        $this->assertDatabaseMissing('project_user', [
            'user_id' => $this->client->id,
            'project_id' => $project->id,
        ]);
        
        // Project still exists
        $this->assertDatabaseHas('projects', [
            'id' => $project->id
        ]);
    }

    public function test_client_cannot_access_client_settings()
    {
        Livewire::actingAs($this->client)
            ->test(ClientSettings::class, ['user' => $this->client])
            ->assertForbidden();
    }

    public function test_assigned_project_visible_to_client_via_accessibleby()
    {
        $project = Project::create([
            'name' => 'Test Proj 4', 
            'package_id' => $this->package->id,
            'is_active' => true,
        ]);
        $this->client->projects()->attach($project->id);
        
        $projects = Project::accessibleBy($this->client)->get();
        $this->assertTrue($projects->contains($project->id));
    }

    public function test_detached_project_not_visible_to_client()
    {
        $project = Project::create([
            'name' => 'Test Proj 5', 
            'package_id' => $this->package->id,
            'is_active' => true,
        ]);
        
        $projects = Project::accessibleBy($this->client)->get();
        $this->assertFalse($projects->contains($project->id));
    }

    public function test_active_quota_enforced_when_assigning_active_project()
    {
        $project1 = Project::create(['name' => 'Proj A', 'package_id' => $this->package->id, 'is_active' => true]);
        $project2 = Project::create(['name' => 'Proj B', 'package_id' => $this->package->id, 'is_active' => true]);
        
        $this->client->projects()->attach([$project1->id, $project2->id]);
        
        // Active quota is 2 (from package). Try assigning 3rd active project.
        $project3 = Project::create(['name' => 'Proj C', 'package_id' => $this->package->id, 'is_active' => true]);

        Livewire::actingAs($this->admin)
            ->test(ClientSettings::class, ['user' => $this->client])
            ->set('selectedProjectId', $project3->id)
            ->call('assignProject')
            ->assertHasErrors(['selectedProjectId']);
    }

    public function test_inactive_project_bypasses_active_quota()
    {
        $project1 = Project::create(['name' => 'Proj X', 'package_id' => $this->package->id, 'is_active' => true]);
        $project2 = Project::create(['name' => 'Proj Y', 'package_id' => $this->package->id, 'is_active' => true]);
        
        $this->client->projects()->attach([$project1->id, $project2->id]);
        
        // Active quota is 2. 3rd project is inactive. Should pass.
        $project3 = Project::create(['name' => 'Proj Z', 'package_id' => $this->package->id, 'is_active' => false]);

        Livewire::actingAs($this->admin)
            ->test(ClientSettings::class, ['user' => $this->client])
            ->set('selectedProjectId', $project3->id)
            ->call('assignProject')
            ->assertHasNoErrors();
            
        $this->assertDatabaseHas('project_user', [
            'user_id' => $this->client->id,
            'project_id' => $project3->id,
        ]);
    }
}
