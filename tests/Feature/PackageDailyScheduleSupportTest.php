<?php

namespace Tests\Feature;

use App\Console\Commands\RunApifyScraping;
use App\Console\Commands\RunNewsPortalScraping;
use App\Livewire\Admin\PackageManager;
use Carbon\Carbon;
use ReflectionClass;
use Tests\TestCase;

class PackageDailyScheduleSupportTest extends TestCase
{
    public function test_package_manager_normalizes_and_sorts_schedule_times(): void
    {
        $component = new PackageManager();
        $result = $this->invokeProtected($component, 'normalizeScheduleTimes', [
            ['18:00', '08:00', '13:00'],
            false,
            'news_run_times',
        ]);

        $this->assertSame(['08:00', '13:00', '18:00'], $result);
    }

    public function test_package_manager_resizes_time_slots_when_runs_change(): void
    {
        $component = new PackageManager();
        $result = $this->invokeProtected($component, 'resizeTimeSlots', [
            ['08:00', '13:00'],
            4,
        ]);

        $this->assertSame(['08:00', '13:00', '', ''], $result);
    }

    public function test_package_manager_caps_time_slots_at_twenty_four(): void
    {
        $component = new PackageManager();

        $result24 = $this->invokeProtected($component, 'resizeTimeSlots', [
            ['08:00', '13:00'],
            24,
        ]);

        $result25 = $this->invokeProtected($component, 'resizeTimeSlots', [
            ['08:00', '13:00'],
            25,
        ]);

        $result1000 = $this->invokeProtected($component, 'resizeTimeSlots', [
            ['08:00', '13:00'],
            1000,
        ]);

        $this->assertCount(24, $result24);
        $this->assertCount(24, $result25);
        $this->assertCount(24, $result1000);
    }

    public function test_package_manager_resizes_negative_and_blank_values_to_empty_slots(): void
    {
        $component = new PackageManager();

        $negative = $this->invokeProtected($component, 'resizeTimeSlots', [
            ['08:00', '13:00'],
            -5,
        ]);

        $blank = $this->invokeProtected($component, 'resizeTimeSlots', [
            ['08:00', '13:00'],
            null,
        ]);

        $this->assertSame([], $negative);
        $this->assertSame([], $blank);
    }

    public function test_package_manager_preserves_existing_values_when_count_is_clamped(): void
    {
        $component = new PackageManager();

        $result = $this->invokeProtected($component, 'resizeTimeSlots', [
            ['08:00', '13:00'],
            1000,
        ]);

        $this->assertSame('08:00', $result[0]);
        $this->assertSame('13:00', $result[1]);
        $this->assertSame('', $result[2]);
        $this->assertSame('', $result[23]);
    }

    public function test_package_manager_rejects_duplicate_times_per_field(): void
    {
        $component = new PackageManager();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->invokeProtected($component, 'normalizeScheduleTimes', [
            ['08:00', '08:00'],
            false,
            'social_run_times',
        ]);
    }

    public function test_scheduler_helper_detects_due_schedule_slot(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 9, 14, 0, 0, 'Asia/Makassar'));

        try {
            $command = new class extends RunNewsPortalScraping {
                public function __construct()
                {
                }
            };

            $result = $this->invokeProtected($command, 'isWithinDailyRunWindow', [
                Carbon::create(2026, 8, 9, 8, 0, 0, 'Asia/Makassar'),
                ['08:00', '13:00', '20:00'],
                Carbon::now(),
            ]);

            $this->assertTrue($result);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_scheduler_helper_detects_not_due_before_first_slot(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 9, 7, 30, 0, 'Asia/Makassar'));

        try {
            $command = new class extends RunApifyScraping {
                public function __construct()
                {
                }
            };

            $result = $this->invokeProtected($command, 'isWithinDailyRunWindow', [
                null,
                ['08:00', '13:00'],
                Carbon::now(),
            ]);

            $this->assertFalse($result);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function invokeProtected(object $object, string $method, array $arguments = [])
    {
        $reflection = new ReflectionClass($object);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($object, $arguments);
    }
}
