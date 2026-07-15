<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\SocialMediaItem;
use App\Services\SocialProjectScrapePriorityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SocialProjectScrapePriorityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_prioritize_puts_never_scanned_projects_first_then_oldest_social_update()
    {
        $neverScanned = Project::create([
            'name' => 'Belum Pernah Dicari',
            'topics' => ['alpha'],
            'is_active' => true,
            'created_at' => now()->subDays(3),
        ]);

        $olderUpdate = Project::create([
            'name' => 'Update Lama',
            'topics' => ['beta'],
            'is_active' => true,
            'created_at' => now()->subDays(2),
        ]);

        $newerUpdate = Project::create([
            'name' => 'Update Baru',
            'topics' => ['gamma'],
            'is_active' => true,
            'created_at' => now()->subDay(),
        ]);

        DB::table('apify_dispatch_states')->insert([
            [
                'dispatch_key' => 'older-update-facebook',
                'project_id' => $olderUpdate->id,
                'actor_id' => 1,
                'platform' => 'Facebook',
                'keyword' => 'beta',
                'normalized_keyword' => 'beta',
                'status' => 'success',
                'queued_at' => now()->subHours(6),
                'started_at' => now()->subHours(6),
                'completed_at' => now()->subHours(6),
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(6),
            ],
            [
                'dispatch_key' => 'newer-update-facebook',
                'project_id' => $newerUpdate->id,
                'actor_id' => 1,
                'platform' => 'Facebook',
                'keyword' => 'gamma',
                'normalized_keyword' => 'gamma',
                'status' => 'success',
                'queued_at' => now()->subMinutes(30),
                'started_at' => now()->subMinutes(30),
                'completed_at' => now()->subMinutes(30),
                'created_at' => now()->subMinutes(30),
                'updated_at' => now()->subMinutes(30),
            ],
        ]);

        $olderSocialItem = SocialMediaItem::create([
            'platform' => 'Facebook',
            'post_url' => 'https://example.com/older',
            'author_name' => 'Older',
            'content' => 'Konten lama yang valid untuk pengujian prioritas.',
            'created_at' => now()->subHours(5),
            'updated_at' => now()->subHours(5),
        ]);

        $newerSocialItem = SocialMediaItem::create([
            'platform' => 'Facebook',
            'post_url' => 'https://example.com/newer',
            'author_name' => 'Newer',
            'content' => 'Konten baru yang valid untuk pengujian prioritas.',
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);

        DB::table('project_social_media_items')->insert([
            [
                'project_id' => $olderUpdate->id,
                'social_media_item_id' => $olderSocialItem->id,
                'created_at' => now()->subHours(5),
                'updated_at' => now()->subHours(5),
            ],
            [
                'project_id' => $newerUpdate->id,
                'social_media_item_id' => $newerSocialItem->id,
                'created_at' => now()->subMinutes(20),
                'updated_at' => now()->subMinutes(20),
            ],
        ]);

        $ordered = app(SocialProjectScrapePriorityService::class)
            ->prioritize(new Collection([$newerUpdate, $olderUpdate, $neverScanned]))
            ->pluck('id')
            ->all();

        $this->assertSame([
            $neverScanned->id,
            $olderUpdate->id,
            $newerUpdate->id,
        ], $ordered);
    }
}
