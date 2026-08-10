<?php

namespace Tests\Feature;

use App\Models\ApifySetting;
use App\Models\ScrapingSetting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use Tests\TestCase;

class GlobalScrapingSwitchesSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected ?string $databasePath = null;
    protected string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
        parent::setUp();
    }

    public function createApplication()
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'scraping-switches-');
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

    protected function scheduleCommandsFor(array $scrapingSettings = [], array $configOverrides = []): array
    {
        $defaults = [
            'id' => 1,
            'google_news_interval' => 15,
            'portal_crawling_interval' => 30,
            'limit_per_run' => 50,
            'timeout_seconds' => 30,
            'retry_limit' => 3,
            'retry_delay_minutes' => 10,
            'is_active' => true,
            'google_news_enabled' => true,
            'manual_portal_enabled' => true,
            'apify_enabled' => true,
            'enable_realtime' => false,
        ];

        ScrapingSetting::query()->updateOrCreate(['id' => 1], array_merge($defaults, $scrapingSettings));

        ApifySetting::query()->updateOrCreate(['id' => 1], [
            'api_token' => 'apify-test-token',
            'connection_status' => 'connected',
            'active_token_index' => 0,
        ]);

        Config::set('services.news.scheduler_enabled', $configOverrides['news'] ?? true);
        Config::set('services.apify.scheduler_enabled', $configOverrides['apify'] ?? true);

        // Rebuild the schedule in a fresh application instance so routes/console.php
        // re-evaluates the current DB state and config flags.
        putenv('NEWS_SCHEDULER_ENABLED=' . (($configOverrides['news'] ?? true) ? 'true' : 'false'));
        putenv('APIFY_SCHEDULER_ENABLED=' . (($configOverrides['apify'] ?? true) ? 'true' : 'false'));

        $app = $this->app;
        $schedule = new Schedule($app['events']);
        ScheduleFacade::swap($schedule);

        require $this->projectRoot . '/routes/console.php';

        return collect($schedule->events())
            ->map(fn ($event) => (string) ($event->command ?? ''))
            ->values()
            ->all();
    }

    public function test_master_off_disables_automatic_news_and_apify_scheduling(): void
    {
        $commands = $this->scheduleCommandsFor([
            'is_active' => false,
        ]);

        $this->assertFalse($this->containsCommand($commands, 'scraping:run-news'));
        $this->assertFalse($this->containsCommand($commands, 'scraping:run-apify'));
    }

    public function test_master_on_with_both_news_switches_on_uses_auto_mode(): void
    {
        $commands = $this->scheduleCommandsFor([
            'is_active' => true,
            'google_news_enabled' => true,
            'manual_portal_enabled' => true,
        ]);

        $this->assertTrue($this->containsCommand($commands, 'scraping:run-news --limit='));
        $this->assertTrue($this->containsCommand($commands, '--discovery-mode=auto'));
        $this->assertTrue($this->containsCommand($commands, 'scraping:run-apify --limit='));
    }

    public function test_google_news_only_uses_google_news_mode(): void
    {
        $commands = $this->scheduleCommandsFor([
            'google_news_enabled' => true,
            'manual_portal_enabled' => false,
        ]);

        $this->assertTrue($this->containsCommand($commands, '--discovery-mode=google_news_only'));
        $this->assertFalse($this->containsCommand($commands, '--discovery-mode=manual_only'));
        $this->assertFalse($this->containsCommand($commands, '--discovery-mode=auto --'));
    }

    public function test_manual_only_uses_manual_mode(): void
    {
        $commands = $this->scheduleCommandsFor([
            'google_news_enabled' => false,
            'manual_portal_enabled' => true,
        ]);

        $this->assertTrue($this->containsCommand($commands, '--discovery-mode=manual_only'));
        $this->assertFalse($this->containsCommand($commands, '--discovery-mode=google_news_only'));
    }

    public function test_news_is_not_scheduled_when_both_news_engine_switches_are_off(): void
    {
        $commands = $this->scheduleCommandsFor([
            'google_news_enabled' => false,
            'manual_portal_enabled' => false,
        ]);

        $this->assertFalse($this->containsCommand($commands, 'scraping:run-news'));
    }

    public function test_apify_is_not_scheduled_when_apify_switch_is_off(): void
    {
        $commands = $this->scheduleCommandsFor([
            'apify_enabled' => false,
        ]);

        $this->assertFalse($this->containsCommand($commands, 'scraping:run-apify'));
    }

    private function containsCommand(array $commands, string $needle): bool
    {
        foreach ($commands as $command) {
            if (str_contains($command, $needle)) {
                return true;
            }
        }

        return false;
    }
}
