<?php

namespace Tests\Feature;

use App\Models\NewsSource;
use App\Models\NewsSourceSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminNewsSourcesTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->regularUser = User::factory()->create([
            'role' => 'user',
        ]);
    }

    public function test_non_admins_cannot_access_news_sources_page()
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.news-sources'))
            ->assertStatus(403);
    }

    public function test_admins_can_access_news_sources_page()
    {
        $this->actingAs($this->adminUser)
            ->get(route('admin.news-sources'))
            ->assertStatus(200)
            ->assertSee('News Sources');
    }

    public function test_it_lists_and_filters_news_sources_correctly()
    {
        $detik = NewsSource::create([
            'name' => '000 Detik',
            'domain' => 'test-detik.com',
            'crawling_type' => 'html',
            'is_active' => true,
        ]);

        $kompas = NewsSource::create([
            'name' => '000 Kompas',
            'domain' => 'test-kompas.com',
            'crawling_type' => 'html',
            'is_active' => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\NewsSources::class)
            ->assertSee('000 Detik')
            ->assertSee('000 Kompas')
            ->set('search', 'detik')
            ->assertSee('000 Detik')
            ->assertDontSee('000 Kompas');
    }

    public function test_it_can_toggle_news_source_active_status()
    {
        $source = NewsSource::create([
            'name' => '000 Unique Tribun Kaltim',
            'domain' => 'unique-tribun.com',
            'crawling_type' => 'html',
            'is_active' => true,
        ]);

        $this->assertTrue($source->fresh()->is_active);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\NewsSources::class)
            ->call('toggleStatus', $source->id)
            ->assertHasNoErrors();

        $this->assertFalse($source->fresh()->is_active);
    }

    public function test_it_can_create_a_new_news_source_with_validation()
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\NewsSources::class)
            ->call('create')
            ->assertSet('showFormModal', true)
            ->set('name', '')
            ->set('domain', '')
            ->call('save')
            ->assertHasErrors(['name', 'domain'])
            ->set('name', 'Busam Test ID')
            ->set('domain', 'busam-test.id')
            ->set('crawling_type', 'html')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('news_sources', [
            'name' => 'Busam Test ID',
            'domain' => 'busam-test.id',
        ]);
    }

    public function test_it_can_edit_an_existing_news_source()
    {
        $source = NewsSource::create([
            'name' => 'Old Name',
            'domain' => 'olddomain-test.com',
            'crawling_type' => 'html',
            'is_active' => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\NewsSources::class)
            ->call('edit', $source->id)
            ->assertSet('name', 'Old Name')
            ->assertSet('domain', 'olddomain-test.com')
            ->set('name', 'Updated Name')
            ->set('domain', 'updateddomain-test.com')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('news_sources', [
            'id' => $source->id,
            'name' => 'Updated Name',
            'domain' => 'updateddomain-test.com',
        ]);
    }

    public function test_it_can_delete_a_news_source_after_confirmation()
    {
        $source = NewsSource::create([
            'name' => 'Delete Me',
            'domain' => 'delete-test.com',
            'crawling_type' => 'html',
            'is_active' => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\NewsSources::class)
            ->call('requestDelete', $source->id)
            ->assertSet('confirmingDelete', true)
            ->call('deleteConfirmed')
            ->assertHasNoErrors()
            ->assertSet('confirmingDelete', false);

        $this->assertSoftDeleted('news_sources', [
            'id' => $source->id,
        ]);
    }

    public function test_it_can_manage_ai_suggestions()
    {
        $suggestion = NewsSourceSuggestion::create([
            'source_name' => 'AI Target',
            'domain' => 'aitarget-test.com',
            'base_url' => 'https://aitarget-test.com',
            'confidence' => 0.9,
            'status' => 'draft_ai',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\NewsSources::class)
            ->assertSee('AI Target')
            ->call('deleteSuggestion', $suggestion->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('news_source_suggestions', [
            'id' => $suggestion->id,
        ]);
    }

    public function test_reject_suggestion_saves_rejected_status_not_failed()
    {
        $suggestion = NewsSourceSuggestion::create([
            'source_name' => 'Rejected Target',
            'domain' => 'rejectedtarget.com',
            'base_url' => 'https://rejectedtarget.com',
            'confidence' => 0.7,
            'status' => 'draft_ai',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\NewsSources::class)
            ->call('rejectSuggestion', $suggestion->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('news_source_suggestions', [
            'id' => $suggestion->id,
            'status' => 'rejected',
        ]);
    }

    public function test_it_handles_failed_test_status_with_error_flash()
    {
        $suggestion = NewsSourceSuggestion::create([
            'source_name' => 'Failed Target',
            'domain' => 'failedtarget.com',
            'base_url' => 'https://failedtarget.com',
            'confidence' => 0.9,
            'status' => 'draft_ai',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\NewsSources::class)
            ->call('testSuggestion', $suggestion->id)
            ->assertSet('flashType', 'failed');
    }

    public function test_approve_suggestion_on_soft_deleted_source_shows_error_not_crash()
    {
        // Buat source lalu soft-delete
        $source = NewsSource::create([
            'name' => 'Soft Deleted Source',
            'domain' => 'softdel-approve-test.local',
            'crawling_type' => 'html',
            'is_active' => true,
        ]);

        $suggestion = NewsSourceSuggestion::create([
            'news_source_id' => $source->id,
            'source_name' => 'Approve on Deleted Source',
            'domain' => 'softdel-approve-test.local',
            'base_url' => 'https://softdel-approve-test.local',
            'confidence' => 0.9,
            'status' => 'verified',
            'test_result_json' => ['mode' => 'discovery', 'status' => 'verified'],
        ]);

        // Soft-delete source
        $source->delete();
        // Verifikasi soft-deleted: hilang dari default query
        $this->assertNull(NewsSource::find($source->id)); // pastikan hilang dari default query

        // Coba approve suggestion yang source-nya soft-deleted
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\NewsSources::class)
            ->call('approveSuggestion', $suggestion->id)
            ->assertHasNoErrors(); // tidak boleh throw exception / crash

        // Status suggestion TIDAK berubah menjadi approved (flash error, bail out)
        $this->assertDatabaseHas('news_source_suggestions', [
            'id' => $suggestion->id,
            'status' => 'verified', // tetap verified, bukan approved
        ]);

        // Source tetap soft-deleted, tidak di-restore otomatis
        $this->assertSoftDeleted('news_sources', ['id' => $source->id]);

        // Cleanup
        $suggestion->forceDelete();
        NewsSource::withTrashed()->find($source->id)->forceDelete();
    }

    public function test_delete_suggestion_only_deletes_suggestion_not_source()
    {
        $source = NewsSource::create([
            'name' => 'Safe Source',
            'domain' => 'safe-delete-sugg-test.local',
            'crawling_type' => 'html',
            'is_active' => true,
        ]);

        $suggestion = NewsSourceSuggestion::create([
            'news_source_id' => $source->id,
            'source_name' => 'Safe Suggestion',
            'domain' => 'safe-delete-sugg-test.local',
            'confidence' => 0.8,
            'status' => 'draft_ai',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\NewsSources::class)
            ->call('deleteSuggestion', $suggestion->id)
            ->assertHasNoErrors();

        // Suggestion terhapus
        $this->assertDatabaseMissing('news_source_suggestions', ['id' => $suggestion->id]);

        // Source TIDAK terhapus
        $this->assertDatabaseHas('news_sources', ['id' => $source->id]);

        // Cleanup
        $source->forceDelete();
    }

    public function test_modal_view_contains_wire_confirm_and_loading_on_modal_buttons()
    {
        // Buka modal dengan suggestion aktif agar blok @if($showTestModal) di-render
        $suggestion = NewsSourceSuggestion::create([
            'source_name' => 'Modal Render Test',
            'domain' => 'modal-render-test.local',
            'confidence' => 0.8,
            'status' => 'verified',
            'test_result_json' => ['mode' => 'discovery', 'status' => 'verified'],
        ]);

        $component = Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\NewsSources::class)
            ->set('showTestModal', true)
            ->set('selectedSuggestionId', $suggestion->id)
            ->set('testStatus', 'verified');

        $html = $component->html();

        $suggestion->forceDelete();

        // wire:confirm harus ada — cek langsung di blade source (tidak ter-encode HTML)
        $bladeSource = file_get_contents(resource_path('views/livewire/admin/news-sources.blade.php'));

        $this->assertStringContainsString(
            'Tolak saran ini? Status akan menjadi DITOLAK dan tidak dipakai pipeline.',
            $bladeSource,
            'wire:confirm Tolak Saran harus ada di blade modal'
        );
        $this->assertStringContainsString(
            'Hapus saran ini secara permanen?',
            $bladeSource,
            'wire:confirm Hapus harus ada di blade modal'
        );
        $this->assertStringContainsString(
            'Setujui dan terapkan konfigurasi ini ke News Source resmi?',
            $bladeSource,
            'wire:confirm Approve harus ada di blade modal'
        );

        // Teks loading harus ada di blade source
        $this->assertStringContainsString(
            'Mengaktifkan...',
            $bladeSource,
            'Teks loading Mengaktifkan harus ada di Approve button'
        );
        $this->assertStringContainsString(
            'Menguji...',
            $bladeSource,
            'Teks loading Menguji harus ada di Uji Discovery button'
        );
        $this->assertStringContainsString(
            'Menguji URL...',
            $bladeSource,
            'Teks loading Menguji URL harus ada di Uji URL Manual button'
        );
        // wire:loading.attr="disabled" harus ada
        $this->assertStringContainsString(
            'wire:loading.attr="disabled"',
            $bladeSource,
            'wire:loading.attr=disabled harus ada di modal buttons'
        );
    }
}
