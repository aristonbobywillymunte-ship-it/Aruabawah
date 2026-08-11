<?php

namespace Tests\Feature;

use App\Livewire\Admin\SystemMaintenance;
use App\Models\User;
use App\Services\Admin\SystemMaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SystemMaintenanceStabilizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    private ?string $databasePath = null;

    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-maintenance@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->member = User::create([
            'name' => 'Member',
            'email' => 'member-maintenance@test.com',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);
    }

    public function createApplication()
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'system-maintenance-');
        if ($this->databasePath === false) {
            throw new \RuntimeException('Unable to create temporary SQLite database file.');
        }

        file_put_contents($this->databasePath, '');

        putenv('DB_CONNECTION=sqlite');
        putenv('DB_SQLITE_DATABASE=' . $this->databasePath);
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_SQLITE_DATABASE'] = $this->databasePath;
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_SQLITE_DATABASE'] = $this->databasePath;

        $app = require $this->projectRoot . '/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->call('migrate', ['--force' => true]);

        return $app;
    }

    protected function tearDown(): void
    {
        if ($this->databasePath && file_exists($this->databasePath)) {
            @unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_admin_can_open_maintenance_page(): void
    {
        $this->bindFakeService();

        $this->actingAs($this->admin)
            ->get('/admin/maintenance')
            ->assertOk()
            ->assertSee('System Maintenance');
    }

    public function test_non_admin_is_blocked_from_maintenance_page(): void
    {
        $this->bindFakeService();

        $this->actingAs($this->member)
            ->get('/admin/maintenance')
            ->assertForbidden();
    }

    public function test_clear_queue_requires_exact_confirmation_phrase(): void
    {
        $service = $this->bindFakeService();

        Livewire::actingAs($this->admin)
            ->test(SystemMaintenance::class)
            ->call('confirmClearRedisQueue')
            ->set('clearConfirmation', 'SALAH')
            ->call('clearRedisQueue')
            ->assertSet('showConfirmModal', true)
            ->assertSet('clearConfirmation', 'SALAH');

        $this->assertSame([], $service->calls);
    }

    public function test_clear_queue_succeeds_after_exact_confirmation(): void
    {
        $service = $this->bindFakeService([
            'deleted_jobs' => 7,
            'queues' => [
                ['connection' => 'default', 'queue' => 'default', 'pending' => 4, 'delayed' => 1],
            ],
        ]);

        Livewire::actingAs($this->admin)
            ->test(SystemMaintenance::class)
            ->call('confirmClearRedisQueue')
            ->set('clearConfirmation', 'HAPUS ANTREAN')
            ->call('clearRedisQueue')
            ->assertSet('showConfirmModal', false)
            ->assertSet('maintenanceSummary.title', 'Redis Queue Dibersihkan');

        $this->assertSame(['clearPendingQueues'], $service->calls);
    }

    public function test_restart_and_cache_actions_are_forwarded_to_service(): void
    {
        $service = $this->bindFakeService();

        Livewire::actingAs($this->admin)
            ->test(SystemMaintenance::class)
            ->call('restartWorkers')
            ->call('restartScheduler')
            ->call('clearMaintenanceCache')
            ->assertSet('maintenanceSummary.title', 'Cache Dibersihkan');

        $this->assertSame(
            ['restartWorkers', 'requestSchedulerRestart', 'clearApplicationCache'],
            $service->calls
        );
    }

    private function bindFakeService(array $clearResult = ['deleted_jobs' => 0, 'queues' => []]): object
    {
        $fake = new class ($clearResult) extends SystemMaintenanceService {
            public array $calls = [];

            public function __construct(private array $clearResult)
            {
            }

            public function queueSnapshot(): array
            {
                return [
                    [
                        'connection' => 'default',
                        'queue' => 'default',
                        'pending' => 0,
                        'delayed' => 0,
                        'reserved' => 0,
                        'total' => 0,
                    ],
                ];
            }

            public function requiredConfirmationPhrase(): string
            {
                return 'HAPUS ANTREAN';
            }

            public function clearPendingQueues(): array
            {
                $this->calls[] = 'clearPendingQueues';

                return $this->clearResult;
            }

            public function restartWorkers(): int
            {
                $this->calls[] = 'restartWorkers';

                return 0;
            }

            public function requestSchedulerRestart(): void
            {
                $this->calls[] = 'requestSchedulerRestart';
            }

            public function clearApplicationCache(): int
            {
                $this->calls[] = 'clearApplicationCache';

                return 0;
            }
        };

        app()->instance(SystemMaintenanceService::class, $fake);

        return $fake;
    }
}
