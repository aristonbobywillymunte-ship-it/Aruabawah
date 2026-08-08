<?php

namespace Tests\Feature;

use App\Models\AiAnalysisResult;
use App\Models\Article;
use App\Models\Project;
use App\Models\SocialMediaItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Livewire\Livewire;

class MediaDashboardQualityGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Since Livewire uses views, we might need a basic setup if any
    }

    public function test_portal_noise_is_hidden_from_dashboard()
    {
        $project = Project::factory()->create(['name' => 'Test Project']);
        $user = User::factory()->create(['role' => 'admin']);

        // Article 1: Noise = true
        $articleNoise = Article::factory()->create(['title' => 'Noise Portal']);
        $articleNoise->projects()->attach($project->id);
        AiAnalysisResult::create([
            'article_id' => $articleNoise->id,
            'is_noise' => true,
            'noise_reason' => 'spam',
            'analysis_status' => 'success',
        ]);

        // Article 2: Noise = false
        $articleClean = Article::factory()->create(['title' => 'Clean Portal']);
        $articleClean->projects()->attach($project->id);
        AiAnalysisResult::create([
            'article_id' => $articleClean->id,
            'is_noise' => false,
            'analysis_status' => 'success',
        ]);

        // Article 3: Noise = null (Legacy/backward compat)
        $articleLegacy = Article::factory()->create(['title' => 'Legacy Portal']);
        $articleLegacy->projects()->attach($project->id);
        AiAnalysisResult::create([
            'article_id' => $articleLegacy->id,
            'is_noise' => null,
            'analysis_status' => 'success',
        ]);

        // Article 4: No AI Result
        $articleNoAi = Article::factory()->create(['title' => 'No AI Portal']);
        $articleNoAi->projects()->attach($project->id);

        Livewire::actingAs($user)
            ->test(\App\Livewire\MediaDashboard::class, ['projectId' => base64_encode($project->id)])
            ->assertDontSee('Noise Portal')
            ->assertSee('Clean Portal')
            ->assertSee('Legacy Portal')
            ->assertSee('No AI Portal');
    }

    public function test_social_noise_is_hidden_from_dashboard()
    {
        $project = Project::factory()->create(['name' => 'Test Project']);
        $user = User::factory()->create(['role' => 'admin']);

        // Social 1: Noise = true
        $socialNoise = SocialMediaItem::factory()->create(['content' => 'Noise Social', 'comments_checked' => true]);
        $socialNoise->projects()->attach($project->id);
        AiAnalysisResult::create([
            'social_media_item_id' => $socialNoise->id,
            'is_noise' => true,
            'analysis_status' => 'success',
        ]);

        // Social 2: Noise = false
        $socialClean = SocialMediaItem::factory()->create(['content' => 'Clean Social', 'comments_checked' => true]);
        $socialClean->projects()->attach($project->id);
        AiAnalysisResult::create([
            'social_media_item_id' => $socialClean->id,
            'is_noise' => false,
            'analysis_status' => 'success',
        ]);

        // Social 3: Noise = null
        $socialLegacy = SocialMediaItem::factory()->create(['content' => 'Legacy Social', 'comments_checked' => true]);
        $socialLegacy->projects()->attach($project->id);
        AiAnalysisResult::create([
            'social_media_item_id' => $socialLegacy->id,
            'is_noise' => null,
            'analysis_status' => 'success',
        ]);

        // Social 4: No AI Result
        $socialNoAi = SocialMediaItem::factory()->create(['content' => 'No AI Social', 'comments_checked' => true]);
        $socialNoAi->projects()->attach($project->id);

        Livewire::actingAs($user)
            ->test(\App\Livewire\MediaDashboard::class, ['projectId' => base64_encode($project->id)])
            ->assertDontSee('Noise Social')
            ->assertSee('Clean Social')
            ->assertSee('Legacy Social')
            ->assertSee('No AI Social');
    }
}
