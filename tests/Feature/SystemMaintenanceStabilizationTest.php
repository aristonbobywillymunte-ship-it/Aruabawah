<?php

namespace Tests\Feature;

use App\Livewire\Admin\SystemMaintenance;
use App\Models\User;
use App\Services\Admin\SystemMaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
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

    public function test_non_admin_cannot_call_clear_cancel_restart_or_cache_actions(): void
    {
        $this->bindFakeService();

        foreach (['cancelClearRedisQueue', 'clearRedisQueue', 'restartWorkers', 'restartScheduler', 'clearMaintenanceCache'] as $method) {
            auth()->login($this->member);

            $component = app(SystemMaintenance::class);

            try {
                $component->{$method}();
                $this->fail("Expected {$method} to abort for non-admin.");
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            } finally {
                auth()->logout();
            }
        }
    }

    public function test_clear_queue_requires_exact_confirmation_phrase(): void
    {
        $service = $this->bindFakeService([
            'clear' => [
                [
                    'queue_connection' => 'redis',
                    'redis_connection' => 'default',
                    'queue' => 'default',
                    'status' => 'cleared',
                    'pending_removed' => 1,
                    'delayed_removed' => 0,
                    'safe_error' => null,
                ],
            ],
        ]);

        Livewire::actingAs($this->admin)
            ->test(SystemMaintenance::class)
            ->call('confirmClearRedisQueue')
            ->set('clearConfirmation', 'SALAH')
            ->call('clearRedisQueue')
            ->assertSet('showConfirmModal', true)
            ->assertSet('clearConfirmation', 'SALAH');

        $this->assertSame([], $service->calls);
    }

    public function test_clear_queue_reports_partial_success_honestly(): void
    {
        $service = $this->bindFakeService([
            'clear' => [
                [
                    'queue_connection' => 'redis',
                    'redis_connection' => 'default',
                    'queue' => 'default',
                    'status' => 'cleared',
                    'pending_removed' => 2,
                    'delayed_removed' => 1,
                    'safe_error' => null,
                ],
                [
                    'queue_connection' => 'redis',
                    'redis_connection' => 'default',
                    'queue' => 'news',
                    'status' => 'failed',
                    'pending_removed' => 0,
                    'delayed_removed' => 0,
                    'safe_error' => 'Status antrean Redis tidak tersedia.',
                ],
            ],
        ]);
        $failedJobsBefore = DB::table('failed_jobs')->count();

        Livewire::actingAs($this->admin)
            ->test(SystemMaintenance::class)
            ->call('confirmClearRedisQueue')
            ->set('clearConfirmation', 'HAPUS ANTREAN')
            ->call('clearRedisQueue')
            ->assertSet('showConfirmModal', false)
            ->assertSet('maintenanceSummary.title', 'Redis Queue Dibersihkan Sebagian');

        $this->assertSame(['clearPendingQueues'], $service->calls);
        $this->assertSame($failedJobsBefore, DB::table('failed_jobs')->count());
    }

    public function test_restart_and_cache_actions_use_signal_wording_and_service_results(): void
    {
        $service = $this->bindFakeService([
            'worker_restart' => ['status' => 'ok', 'exit_code' => 0],
            'scheduler_restart' => ['status' => 'ok'],
            'cache_clear' => ['status' => 'ok', 'exit_code' => 0],
        ]);

        Livewire::actingAs($this->admin)
            ->test(SystemMaintenance::class)
            ->call('restartWorkers')
            ->assertSet('maintenanceSummary.title', 'Signal Restart Worker Dikirim')
            ->call('restartScheduler')
            ->assertSet('maintenanceSummary.title', 'Signal Restart Scheduler Dikirim')
            ->call('clearMaintenanceCache')
            ->assertSet('maintenanceSummary.title', 'Cache Dibersihkan');

        $this->assertSame(['restartWorkers', 'requestSchedulerRestart', 'clearApplicationCache'], $service->calls);
    }

    public function test_queue_snapshot_failure_does_not_break_page(): void
    {
        $service = $this->bindFakeService([
            'snapshot' => new \RuntimeException('Redis connection failure for queues:default'),
        ]);

        $component = Livewire::actingAs($this->admin)->test(SystemMaintenance::class);

        $component->assertSee('System Maintenance')
            ->assertSee('Tidak tersedia');

        $this->assertSame([], $service->calls);
    }

    public function test_scheduler_restart_signal_key_is_only_consumed_once_in_routes_console(): void
    {
        $content = file_get_contents($this->projectRoot . '/routes/console.php');
        $this->assertIsString($content);

        $this->assertSame(1, substr_count($content, "Cache::pull('scheduler_should_restart')"));
        $this->assertSame(0, substr_count($content, "Cache::forget('scheduler_should_restart')"));
        $this->assertStringNotContainsString("exit(0);\n})->everyMinute();", $content);
    }

    private function bindFakeService(array $overrides = []): object
    {
        $fake = new class ($overrides) extends SystemMaintenanceService {
            public array $calls = [];

            public function __construct(private array $overrides)
            {
            }

            public function queueSnapshot(): array
            {
                if (isset($this->overrides['snapshot']) && $this->overrides['snapshot'] instanceof \Throwable) {
                    throw $this->overrides['snapshot'];
                }

                return $this->overrides['snapshot'] ?? [[
                    'queue_connection' => 'redis',
                    'redis_connection' => 'default',
                    'queue' => 'default',
                    'status' => 'ok',
                    'pending' => 0,
                    'delayed' => 0,
                    'reserved' => 0,
                    'total' => 0,
                    'error' => null,
                ]];
            }

            public function requiredConfirmationPhrase(): string
            {
                return 'HAPUS ANTREAN';
            }

            public function clearPendingQueues(): array
            {
                $this->calls[] = 'clearPendingQueues';

                if (isset($this->overrides['clear'])) {
                    return [
                        'deleted_jobs' => 3,
                        'succeeded_queues' => 1,
                        'failed_queues' => 1,
                        'partial_failure' => true,
                        'queues' => $this->overrides['clear'],
                    ];
                }

                return [
                    'deleted_jobs' => 0,
                    'succeeded_queues' => 0,
                    'failed_queues' => 0,
                    'partial_failure' => false,
                    'queues' => [],
                ];
            }

            public function restartWorkers(): array
            {
                $this->calls[] = 'restartWorkers';

                return $this->overrides['worker_restart'] ?? ['status' => 'ok', 'exit_code' => 0];
            }

            public function requestSchedulerRestart(): array
            {
                $this->calls[] = 'requestSchedulerRestart';

                return $this->overrides['scheduler_restart'] ?? ['status' => 'ok'];
            }

            public function clearApplicationCache(): array
            {
                $this->calls[] = 'clearApplicationCache';

                return $this->overrides['cache_clear'] ?? ['status' => 'ok', 'exit_code' => 0];
            }
        };

        app()->instance(SystemMaintenanceService::class, $fake);

        return $fake;
    }
}
