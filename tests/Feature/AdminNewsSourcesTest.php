<?php

namespace Tests\Feature;

use App\Models\NewsSource;
use App\Models\NewsSourceSuggestion;
use App\Models\User;
use App\Models\AiPromptTemplate;
use App\Models\AiProvider;
use App\Livewire\Admin\NewsSources;
use App\Services\AiProviderClient;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class AdminNewsSourcesTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;
    private ?string $databasePath = null;
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->regularUser = User::factory()->create([
            'role' => 'user',
        ]);
    }

    public function createApplication()
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'news-sources-');
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
        $app->make(Kernel::class)->bootstrap();
        $app->make(Kernel::class)->call('migrate', ['--force' => true]);

        return $app;
    }

    protected function tearDown(): void
    {
        if ($this->databasePath && file_exists($this->databasePath)) {
            @unlink($this->databasePath);
        }

        parent::tearDown();
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
            ->call('requestSave')
            ->assertHasErrors(['name', 'domain'])
            ->set('name', 'Busam Test ID')
            ->set('domain', 'busam-test.id')
            ->set('search_url', 'https://busam-test.id/search?q={keyword}')
            ->set('search_result_selector', 'article a[href]')
            ->set('article_link_selector', 'article a[href]')
            ->set('article_content_selector', 'article .content')
            ->set('crawling_type', 'html')
            ->call('requestSave')
            ->call('saveConfirmed')
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
            ->set('search_url', 'https://updateddomain-test.com/search?q={keyword}')
            ->set('search_result_selector', 'article a[href]')
            ->set('article_link_selector', 'article a[href]')
            ->set('article_content_selector', 'article .content')
            ->call('requestSave')
            ->call('saveConfirmed')
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

    public function test_save_errors_use_generic_browser_message_without_leaking_secret_text(): void
    {
        Event::listen('eloquent.saving: ' . NewsSource::class, function () {
            throw new \RuntimeException('pgsql://user:password@internal-host:5432/media_intelligent?token=super-secret-token');
        });

        try {
            $admin = $this->adminUser;

            Livewire::actingAs($admin)
                ->test(NewsSources::class)
                ->call('create')
                ->set('name', 'Unsafe Save Portal')
                ->set('domain', 'unsafe-save.test')
                ->set('base_url', 'https://unsafe-save.test')
                ->set('search_url', 'https://unsafe-save.test/search?q={keyword}')
                ->set('search_result_selector', 'article a[href]')
                ->set('article_link_selector', 'article a[href]')
                ->set('article_content_selector', 'article .content')
                ->set('article_author_selector', '.author')
                ->set('article_date_selector', 'time')
                ->call('saveConfirmed')
                ->assertSet('flashType', 'error')
                ->assertSet('flashMessage', 'Gagal menyimpan sumber berita. Silakan coba lagi.')
                ->assertSet('showFormModal', true)
                ->assertSet('confirmingSave', false)
                ->assertDontSee('super-secret-token')
                ->assertDontSee('internal-host');
        } finally {
            Event::forget('eloquent.saving: ' . NewsSource::class);
        }

        $this->assertDatabaseMissing('news_sources', [
            'domain' => 'unsafe-save.test',
        ]);
    }

    public function test_save_error_logs_do_not_include_raw_exception_messages_or_secrets(): void
    {
        Log::spy();

        Event::listen('eloquent.saving: ' . NewsSource::class, function () {
            throw new \RuntimeException('redis://user:password@internal-host:6379?token=super-secret-token');
        });

        try {
            Livewire::actingAs($this->adminUser)
                ->test(NewsSources::class)
                ->call('create')
                ->set('name', 'Log Safety Portal')
                ->set('domain', 'log-safety.test')
                ->set('base_url', 'https://log-safety.test')
                ->set('search_url', 'https://log-safety.test/search?q={keyword}')
                ->set('search_result_selector', 'article a[href]')
                ->set('article_link_selector', 'article a[href]')
                ->set('article_content_selector', 'article .content')
                ->set('article_author_selector', '.author')
                ->set('article_date_selector', 'time')
                ->call('saveConfirmed')
                ->assertSet('flashType', 'error')
                ->assertSet('flashMessage', 'Gagal menyimpan sumber berita. Silakan coba lagi.');
        } finally {
            Event::forget('eloquent.saving: ' . NewsSource::class);
        }

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $payload = json_encode([$message, $context], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                $this->assertStringContainsString('[News Sources] save source failed.', $message);
                $this->assertArrayHasKey('exception', $context);
                $this->assertArrayHasKey('source_id', $context);
                $this->assertArrayHasKey('admin_user_id', $context);
                $this->assertArrayNotHasKey('message', $context);
                $this->assertStringNotContainsString('super-secret-token', $payload);
                $this->assertStringNotContainsString('password', $payload);
                $this->assertStringNotContainsString('internal-host', $payload);
                $this->assertStringNotContainsString('redis://user:password@internal-host:6379', $payload);

                return true;
            });
    }

    public function test_create_rolls_back_source_when_related_suggestion_write_fails(): void
    {
        Event::listen('eloquent.creating: ' . NewsSourceSuggestion::class, function () {
            throw new \RuntimeException('redis://user:password@internal-host:6379?token=super-secret-token');
        });

        try {
            Livewire::actingAs($this->adminUser)
                ->test(NewsSources::class)
                ->call('create')
                ->set('name', 'Atomic Save Portal')
                ->set('domain', 'atomic-save.test')
                ->set('base_url', 'https://atomic-save.test')
                ->set('search_url', 'https://atomic-save.test/search?q={keyword}')
                ->set('search_result_selector', 'article a[href]')
                ->set('article_link_selector', 'article a[href]')
                ->set('article_content_selector', 'article .content')
                ->set('article_author_selector', '.author')
                ->set('article_date_selector', 'time')
                ->call('requestSave')
                ->call('saveConfirmed')
                ->assertSet('flashType', 'error')
                ->assertSet('flashMessage', 'Gagal menyimpan sumber berita. Silakan coba lagi.')
                ->assertSet('showFormModal', true)
                ->assertSet('confirmingSave', false)
                ->assertDontSee('super-secret-token')
                ->assertDontSee('internal-host');
        } finally {
            Event::forget('eloquent.creating: ' . NewsSourceSuggestion::class);
        }

        $this->assertDatabaseMissing('news_sources', [
            'domain' => 'atomic-save.test',
        ]);

        $this->assertDatabaseMissing('news_source_suggestions', [
            'domain' => 'atomic-save.test',
        ]);
    }

    public function test_ai_generation_errors_use_generic_browser_message_without_leaking_provider_details(): void
    {
        AiProvider::create([
            'name' => 'OpenAI Test',
            'provider_type' => 'openai',
            'base_url' => 'https://provider.example/api',
            'api_key' => 'super-secret-token',
            'model_name' => 'gpt-test',
            'is_active' => true,
            'is_default' => true,
        ]);

        AiPromptTemplate::create([
            'name' => 'Saran Portal Manual',
            'source_type' => 'portal_suggestion',
            'system_prompt' => 'system prompt',
            'user_prompt_template' => 'user prompt {name} {domain} {html}',
            'output_schema' => '{"type":"object"}',
            'is_active' => true,
            'is_default' => true,
        ]);

        $aiClient = Mockery::mock(AiProviderClient::class);
        $aiClient->shouldReceive('sendRequest')
            ->once()
            ->andThrow(new \RuntimeException('redis://user:password@internal-host:6379?token=super-secret-token'));
        $this->app->instance(AiProviderClient::class, $aiClient);

        Livewire::actingAs($this->adminUser)
            ->test(NewsSources::class)
            ->call('openSuggestInput')
            ->set('manualHtmlInput', '<html><head><title>Portal Uji</title></head><body><a href="https://portal-uji.test/news">Link</a></body></html>')
            ->call('generateSuggestionForNew')
            ->assertSet('flashType', 'error')
            ->assertSet('flashMessage', 'Proses AI gagal. Silakan coba lagi.')
            ->assertSet('showSuggestInputModal', false)
            ->assertDontSee('super-secret-token')
            ->assertDontSee('internal-host');

        $this->assertDatabaseCount('news_source_suggestions', 0);
    }

    public function test_manual_url_test_errors_use_generic_browser_message_without_leaking_secret_text(): void
    {
        $suggestion = NewsSourceSuggestion::create([
            'source_name' => 'Manual Test Target',
            'domain' => 'manual-test-target.test',
            'base_url' => 'https://manual-test-target.test',
            'search_url' => 'https://manual-test-target.test/search?q={query}',
            'confidence' => 0.8,
            'status' => 'verified',
        ]);

        $tester = Mockery::mock('alias:App\Services\NewsSourceSuggestionTester');
        $tester->shouldReceive('testManualUrl')
            ->once()
            ->andThrow(new \RuntimeException('https://provider.example/api?token=super-secret-token'));

        Livewire::actingAs($this->adminUser)
            ->test(NewsSources::class)
            ->set('manualHtmlInput', '<html><body>test</body></html>')
            ->call('testManualUrl', $suggestion->id)
            ->assertSet('flashType', 'error')
            ->assertSet('flashMessage', 'Pengujian gagal dijalankan. Silakan coba lagi.')
            ->assertSet('testingSuggestionId', null)
            ->assertDontSee('super-secret-token')
            ->assertDontSee('provider.example');
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
            ->assertSet('testStatus', 'testing')
            ->assertSet('showTestModal', true);
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
