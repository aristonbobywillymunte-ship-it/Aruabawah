<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Article;
use App\Models\SocialMediaItem;
use App\Models\ClientSetting;
use Livewire\Livewire;
use App\Http\Livewire\ProjectsList;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ProjectTrashAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $internalUser;
    protected User $clientWithDelete;
    protected User $clientNoDelete;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@test.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        $this->internalUser = User::create([
            'name'     => 'Internal User',
            'email'    => 'internal@test.com',
            'password' => Hash::make('password'),
            'role'     => 'user',
        ]);

        $this->clientWithDelete = User::create([
            'name'     => 'Client With Delete',
            'email'    => 'client-delete@test.com',
            'password' => Hash::make('password'),
            'role'     => 'client',
        ]);
        ClientSetting::create([
            'user_id'             => $this->clientWithDelete->id,
            'can_delete_projects' => true,
            'max_projects'        => null,
        ]);

        $this->clientNoDelete = User::create([
            'name'     => 'Client No Delete',
            'email'    => 'client-nodelete@test.com',
            'password' => Hash::make('password'),
            'role'     => 'client',
        ]);
        ClientSetting::create([
            'user_id'             => $this->clientNoDelete->id,
            'can_delete_projects' => false,
            'max_projects'        => null,
        ]);
    }

    // ── Admin / Internal User ─────────────────────────────────────────────────

    public function test_admin_can_deactivate_project()
    {
        $project = Project::create(['name' => 'Proj A', 'is_active' => true]);

        Livewire::actingAs($this->admin)
            ->test(ProjectsList::class)
            ->call('confirmDeleteProject', $project->id)
            ->assertSet('showConfirmModal', true)
            ->assertSet('confirmAction', 'delete');

        Livewire::actingAs($this->admin)
            ->test(ProjectsList::class)
            ->call('deleteProject', $project->id);

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_internal_user_can_deactivate_project()
    {
        $project = Project::create(['name' => 'Proj B', 'is_active' => true]);

        Livewire::actingAs($this->internalUser)
            ->test(ProjectsList::class)
            ->call('confirmDeleteProject', $project->id)
            ->assertSet('showConfirmModal', true);

        Livewire::actingAs($this->internalUser)
            ->test(ProjectsList::class)
            ->call('deleteProject', $project->id);

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_admin_can_open_trashed_projects_modal()
    {
        Livewire::actingAs($this->admin)
            ->test(ProjectsList::class)
            ->call('openTrashedProjectsModal')
            ->assertSet('showTrashedModal', true);
    }

    public function test_internal_user_can_open_trashed_projects_modal()
    {
        Livewire::actingAs($this->internalUser)
            ->test(ProjectsList::class)
            ->call('openTrashedProjectsModal')
            ->assertSet('showTrashedModal', true);
    }

    public function test_admin_can_restore_project()
    {
        $project = Project::create(['name' => 'Proj C', 'is_active' => false]);
        $project->delete();

        Livewire::actingAs($this->admin)
            ->test(ProjectsList::class)
            ->call('confirmRestoreProject', $project->id)
            ->assertSet('showConfirmModal', true)
            ->assertSet('confirmAction', 'restore');

        Livewire::actingAs($this->admin)
            ->test(ProjectsList::class)
            ->call('restoreProject', $project->id);

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'deleted_at' => null]);
    }

    public function test_admin_can_force_delete_project()
    {
        $project = Project::create(['name' => 'Proj D', 'is_active' => false]);
        $project->delete();

        Livewire::actingAs($this->admin)
            ->test(ProjectsList::class)
            ->call('confirmForceDeleteProject', $project->id)
            ->assertSet('showConfirmModal', true)
            ->assertSet('confirmAction', 'force_delete');

        Livewire::actingAs($this->admin)
            ->test(ProjectsList::class)
            ->call('forceDeleteProject', $project->id);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    // ── Client WITHOUT can_delete_projects ────────────────────────────────────

    public function test_client_without_delete_perm_cannot_open_confirm_modal()
    {
        $project = Project::create(['name' => 'Proj E', 'is_active' => true]);
        $this->clientNoDelete->projects()->attach($project->id);

        Livewire::actingAs($this->clientNoDelete)
            ->test(ProjectsList::class)
            ->call('confirmDeleteProject', $project->id)
            ->assertSet('showConfirmModal', false)
            ->assertSet('confirmAction', null);
    }

    public function test_client_without_delete_perm_cannot_directly_deactivate()
    {
        $project = Project::create(['name' => 'Proj F', 'is_active' => true]);
        $this->clientNoDelete->projects()->attach($project->id);

        Livewire::actingAs($this->clientNoDelete)
            ->test(ProjectsList::class)
            ->call('deleteProject', $project->id);

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'deleted_at' => null]);
    }

    public function test_client_without_delete_perm_cannot_open_trashed_modal()
    {
        Livewire::actingAs($this->clientNoDelete)
            ->test(ProjectsList::class)
            ->call('openTrashedProjectsModal')
            ->assertSet('showTrashedModal', false);
    }

    public function test_client_without_delete_perm_cannot_restore()
    {
        $project = Project::create(['name' => 'Proj G', 'is_active' => false]);
        $project->delete();

        Livewire::actingAs($this->clientNoDelete)
            ->test(ProjectsList::class)
            ->call('confirmRestoreProject', $project->id)
            ->assertSet('showConfirmModal', false)
            ->assertSet('confirmAction', null);

        Livewire::actingAs($this->clientNoDelete)
            ->test(ProjectsList::class)
            ->call('restoreProject', $project->id);

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_client_without_delete_perm_cannot_force_delete()
    {
        $project = Project::create(['name' => 'Proj H', 'is_active' => false]);
        $project->delete();

        Livewire::actingAs($this->clientNoDelete)
            ->test(ProjectsList::class)
            ->call('confirmForceDeleteProject', $project->id)
            ->assertSet('showConfirmModal', false)
            ->assertSet('confirmAction', null);

        Livewire::actingAs($this->clientNoDelete)
            ->test(ProjectsList::class)
            ->call('forceDeleteProject', $project->id);

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    // ── Client WITH can_delete_projects = true ────────────────────────────────

    public function test_client_with_delete_perm_can_deactivate_accessible_project()
    {
        $project = Project::create(['name' => 'Proj I', 'is_active' => true]);
        $this->clientWithDelete->projects()->attach($project->id);

        Livewire::actingAs($this->clientWithDelete)
            ->test(ProjectsList::class)
            ->call('confirmDeleteProject', $project->id)
            ->assertSet('showConfirmModal', true)
            ->assertSet('confirmAction', 'delete');

        Livewire::actingAs($this->clientWithDelete)
            ->test(ProjectsList::class)
            ->call('deleteProject', $project->id);

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_client_with_delete_perm_cannot_open_trashed_modal()
    {
        Livewire::actingAs($this->clientWithDelete)
            ->test(ProjectsList::class)
            ->call('openTrashedProjectsModal')
            ->assertSet('showTrashedModal', false);
    }

    public function test_client_with_delete_perm_cannot_restore()
    {
        $project = Project::create(['name' => 'Proj J', 'is_active' => false]);
        $project->delete();

        Livewire::actingAs($this->clientWithDelete)
            ->test(ProjectsList::class)
            ->call('confirmRestoreProject', $project->id)
            ->assertSet('showConfirmModal', false);

        Livewire::actingAs($this->clientWithDelete)
            ->test(ProjectsList::class)
            ->call('restoreProject', $project->id);

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_client_with_delete_perm_cannot_force_delete()
    {
        $project = Project::create(['name' => 'Proj K', 'is_active' => false]);
        $project->delete();

        Livewire::actingAs($this->clientWithDelete)
            ->test(ProjectsList::class)
            ->call('confirmForceDeleteProject', $project->id)
            ->assertSet('showConfirmModal', false);

        Livewire::actingAs($this->clientWithDelete)
            ->test(ProjectsList::class)
            ->call('forceDeleteProject', $project->id);

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    // ── Source data preservation ──────────────────────────────────────────────

    public function test_force_delete_preserves_source_articles()
    {
        $project = Project::create(['name' => 'Proj L', 'is_active' => false]);

        $article = Article::create([
            'title'      => 'Test Article',
            'url'        => 'https://example.com/art1',
            'project_id' => $project->id,
            'source_url' => 'https://example.com',
        ]);

        $project->delete();

        Livewire::actingAs($this->admin)
            ->test(ProjectsList::class)
            ->call('forceDeleteProject', $project->id);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('articles', ['id' => $article->id]);
    }

    public function test_force_delete_preserves_source_social_media_items()
    {
        $project = Project::create(['name' => 'Proj M', 'is_active' => false]);

        $item = SocialMediaItem::create([
            'platform'   => 'twitter',
            'content'    => 'Test tweet content',
            'project_id' => $project->id,
            'post_url'   => 'https://twitter.com/test',
        ]);

        $project->delete();

        Livewire::actingAs($this->admin)
            ->test(ProjectsList::class)
            ->call('forceDeleteProject', $project->id);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('social_media_items', ['id' => $item->id]);
    }

    public function test_force_delete_removes_project_user_pivot()
    {
        $project = Project::create(['name' => 'Proj N', 'is_active' => false]);

        DB::table('project_user')->insert([
            'project_id' => $project->id,
            'user_id'    => $this->admin->id,
        ]);

        $project->delete();

        Livewire::actingAs($this->admin)
            ->test(ProjectsList::class)
            ->call('forceDeleteProject', $project->id);

        $this->assertEquals(0, DB::table('project_user')
            ->where('project_id', $project->id)
            ->count());
    }
}
