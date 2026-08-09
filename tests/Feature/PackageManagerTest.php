<?php

namespace Tests\Feature;

use App\Livewire\Admin\PackageManager;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PackageManagerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_it_can_create_a_package_with_project_and_keyword_limits()
    {
        Livewire::test(PackageManager::class)
            ->set('name', 'Pro Package')
            ->set('price', '150000')
            ->set('news_interval_minutes', 5)
            ->set('social_interval_minutes', 10)
            ->set('max_projects', 5)
            ->set('max_keywords_per_project', 25)
            ->call('savePackage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('packages', [
            'name' => 'Pro Package',
            'max_projects' => 5,
            'max_keywords_per_project' => 25,
        ]);
    }

    /** @test */
    public function test_it_can_edit_an_existing_package_to_update_limits()
    {
        $package = Package::create([
            'name' => 'Starter',
            'price' => 50000,
            'news_interval_minutes' => 5,
            'social_interval_minutes' => 10,
            'max_projects' => 5,
            'max_keywords_per_project' => 25,
            'is_active' => true,
        ]);

        Livewire::test(PackageManager::class)
            ->call('editPackage', $package->id)
            ->set('max_projects', 10)
            ->set('max_keywords_per_project', 30)
            ->call('savePackage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'max_projects' => 10,
            'max_keywords_per_project' => 30,
        ]);
    }

    /** @test */
    public function test_it_saves_null_when_limits_are_empty_or_cleared()
    {
        $package = Package::create([
            'name' => 'Enterprise',
            'price' => 500000,
            'news_interval_minutes' => 5,
            'social_interval_minutes' => 10,
            'max_projects' => 5,
            'max_keywords_per_project' => 25,
            'is_active' => true,
        ]);

        Livewire::test(PackageManager::class)
            ->call('editPackage', $package->id)
            ->set('max_projects', '')
            ->set('max_keywords_per_project', '')
            ->call('savePackage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'max_projects' => null,
            'max_keywords_per_project' => null,
        ]);
    }

    /** @test */
    public function test_it_validates_that_limits_must_be_positive_integers()
    {
        Livewire::test(PackageManager::class)
            ->set('name', 'Invalid Package')
            ->set('price', '10000')
            ->set('news_interval_minutes', 5)
            ->set('social_interval_minutes', 10)
            ->set('max_projects', 0)
            ->set('max_keywords_per_project', -5)
            ->call('savePackage')
            ->assertHasErrors(['max_projects', 'max_keywords_per_project']);
    }
}
