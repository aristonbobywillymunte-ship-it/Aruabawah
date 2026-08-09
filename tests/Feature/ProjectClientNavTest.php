<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Livewire\Admin\ClientManagement\ClientCreate;
use Livewire\Livewire;

class ProjectClientNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_see_create_client_button_on_projects_page()
    {
        $user = User::factory()->create(['role' => 'user']);
        
        Livewire::actingAs($user)
            ->test(\App\Livewire\ProjectsList::class)
            ->call('loadProjects')
            ->assertSee('Kelola Client')
            ->assertSee('wire:navigate')
            ->assertSee(route('admin.clients'));
    }

    public function test_client_cannot_see_create_client_button_on_projects_page()
    {
        $client = User::factory()->create(['role' => 'client']);
        
        Livewire::actingAs($client)
            ->test(\App\Livewire\ProjectsList::class)
            ->call('loadProjects')
            ->assertDontSee('Kelola Client');
    }

    public function test_client_gets_403_when_accessing_client_create_directly()
    {
        $client = User::factory()->create(['role' => 'client']);
        
        $this->actingAs($client)
            ->get(route('admin.clients.create'))
            ->assertForbidden();
            
        // Or using Livewire testing API:
        // Livewire::actingAs($client)->test(ClientCreate::class)->assertForbidden();
    }

    public function test_user_can_access_client_create_directly()
    {
        $user = User::factory()->create(['role' => 'user']);
        
        $this->actingAs($user)
            ->get(route('admin.clients.create'))
            ->assertOk();
    }
}
