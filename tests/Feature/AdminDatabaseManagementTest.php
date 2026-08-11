<?php

namespace Tests\Feature;

use App\Livewire\Admin\DatabaseManagement;
use App\Models\User;
use App\Services\Admin\DatabaseManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class AdminDatabaseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_database_page_is_forbidden_to_non_admin_users(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('admin.database'))
            ->assertForbidden();
    }

    public function test_admin_database_component_accepts_valid_sql_backup_with_exact_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $fakeService = new class extends DatabaseManagementService {
            public bool $restoreCalled = false;
            public ?string $restorePath = null;

            public function restoreBackup(string $sqlFilePath): void
            {
                $this->restoreCalled = true;
                $this->restorePath = $sqlFilePath;
            }

            public function exportBackup(): string
            {
                $path = tempnam(sys_get_temp_dir(), 'db_backup_') . '.sql';
                file_put_contents($path, "-- PostgreSQL database dump\n");

                return $path;
            }
        };

        $this->app->instance(DatabaseManagementService::class, $fakeService);

        $dumpFile = UploadedFile::fake()->createWithContent(
            'restore.sql',
            "-- PostgreSQL database dump\nCREATE TABLE test (id integer);\n"
        );

        Livewire::actingAs($admin)
            ->test(DatabaseManagement::class)
            ->set('databaseFile', $dumpFile)
            ->set('restoreConfirmation', 'PULIHKAN DATABASE')
            ->call('import')
            ->assertHasNoErrors();

        $this->assertTrue($fakeService->restoreCalled);
        $this->assertNotNull($fakeService->restorePath);
    }

    public function test_admin_database_component_rejects_invalid_extension_and_missing_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $fakeService = new class extends DatabaseManagementService {
            public bool $restoreCalled = false;

            public function restoreBackup(string $sqlFilePath): void
            {
                $this->restoreCalled = true;
            }
        };

        $this->app->instance(DatabaseManagementService::class, $fakeService);

        $badFile = UploadedFile::fake()->createWithContent('restore.txt', 'not a dump');

        Livewire::actingAs($admin)
            ->test(DatabaseManagement::class)
            ->set('databaseFile', $badFile)
            ->call('import')
            ->assertHasErrors(['databaseFile', 'restoreConfirmation']);

        $this->assertFalse($fakeService->restoreCalled);
    }

    public function test_admin_database_component_hides_raw_stderr_on_export_failure(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $fakeService = new class extends DatabaseManagementService {
            public function exportBackup(): string
            {
                throw new RuntimeException('PGPASSWORD=super-secret Authorization: Bearer token123');
            }

            public function restoreBackup(string $sqlFilePath): void
            {
                //
            }
        };

        $this->app->instance(DatabaseManagementService::class, $fakeService);

        Livewire::actingAs($admin)
            ->test(DatabaseManagement::class)
            ->call('download')
            ->assertDispatched('toast', function (string $event, array $params): bool {
                return str_contains($params['message'], 'Gagal membuat cadangan database')
                    && ! str_contains($params['message'], 'super-secret')
                    && ! str_contains($params['message'], 'Bearer');
            });
    }

    public function test_admin_database_component_hides_raw_stderr_on_restore_failure(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $fakeService = new class extends DatabaseManagementService {
            public function exportBackup(): string
            {
                $path = tempnam(sys_get_temp_dir(), 'db_backup_') . '.sql';
                file_put_contents($path, "-- PostgreSQL database dump\n");

                return $path;
            }

            public function restoreBackup(string $sqlFilePath): void
            {
                throw new RuntimeException('password=abc123 api_token=xyz');
            }
        };

        $this->app->instance(DatabaseManagementService::class, $fakeService);

        $dumpFile = UploadedFile::fake()->createWithContent(
            'restore.sql',
            "-- PostgreSQL database dump\nCREATE TABLE test (id integer);\n"
        );

        Livewire::actingAs($admin)
            ->test(DatabaseManagement::class)
            ->set('databaseFile', $dumpFile)
            ->set('restoreConfirmation', 'PULIHKAN DATABASE')
            ->call('import')
            ->assertDispatched('toast', function (string $event, array $params): bool {
                return str_contains($params['message'], 'Pemulihan database gagal')
                    && ! str_contains($params['message'], 'abc123')
                    && ! str_contains($params['message'], 'xyz');
            });
    }

    public function test_admin_database_component_is_forbidden_for_non_admin_users(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        Livewire::actingAs($user)
            ->test(DatabaseManagement::class)
            ->assertForbidden();
    }
}
