<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectsListDefaultViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_project_list_when_no_project_query_is_present(): void
    {
        $user = User::factory()->create();

        Project::create([
            'name' => 'Iswandi',
            'topics' => ['iswandi'],
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Daftar Proyek Anda')
            ->assertSee('Buat Proyek Baru')
            ->assertDontSee('Penyebutan');
    }
}
