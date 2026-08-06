<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class SystemLogsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;
    protected string $tempLogFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->regularUser = User::factory()->create(['role' => 'user', 'status' => 'active']);

        // Create a temporary log file inside logs directory
        $this->tempLogFile = storage_path('logs/laravel-queue.log');
        File::ensureDirectoryExists(storage_path('logs'));
        File::put($this->tempLogFile, "[2026-08-07 12:00:00] local.INFO: Processing job Project: Kaltim (ID: 1) source: Telegram status: success message: OK\n");
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tempLogFile)) {
            File::delete($this->tempLogFile);
        }
        parent::tearDown();
    }

    public function test_it_denies_access_to_non_admins()
    {
        $this->actingAs($this->regularUser)
            ->get('/admin/logs')
            ->assertStatus(403);
    }

    public function test_it_allows_access_to_admins()
    {
        $this->actingAs($this->adminUser)
            ->get('/admin/logs')
            ->assertStatus(200)
            ->assertSeeLivewire(\App\Livewire\Admin\SystemLogs::class);
    }

    public function test_it_filters_logs_by_selected_file()
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\SystemLogs::class)
            ->set('selectedFile', 'laravel-queue.log')
            ->assertSet('selectedFile', 'laravel-queue.log')
            ->assertSee('OK');
    }

    public function test_it_filters_logs_by_status()
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\SystemLogs::class)
            ->set('selectedFile', 'laravel-queue.log')
            ->set('statusFilter', 'success')
            ->assertSee('OK')
            ->set('statusFilter', 'failed')
            ->assertDontSee('OK');
    }

    public function test_it_searches_logs_by_general_term()
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\SystemLogs::class)
            ->set('selectedFile', 'laravel-queue.log')
            ->set('searchTerm', 'Kaltim')
            ->assertSee('OK')
            ->set('searchTerm', 'NonexistentWord')
            ->assertDontSee('OK');
    }
}
