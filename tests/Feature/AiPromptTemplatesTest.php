<?php

namespace Tests\Feature;

use App\Livewire\Admin\AiPromptTemplates;
use App\Models\AiPromptTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiPromptTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->regularUser = User::factory()->create(['role' => 'user']);
    }

    public function test_halaman_hanya_bisa_diakses_admin(): void
    {
        $this->actingAs($this->regularUser)
            ->get('/admin/ai-prompt-templates')
            ->assertForbidden();
    }

    public function test_admin_bisa_akses_halaman(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/ai-prompt-templates')
            ->assertOk();
    }

    public function test_komponen_render_tanpa_error(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->assertStatus(200)
            ->assertSee('AI Prompt Templates');
    }

    public function test_admin_bisa_membuka_modal_tambah(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->call('create')
            ->assertSet('showFormModal', true)
            ->assertSet('isEditing', false);
    }

    public function test_admin_bisa_simpan_template_baru(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->set('name', 'Template QA Test')
            ->set('source_type', 'article')
            ->set('system_prompt', 'Kamu adalah QA tester.')
            ->set('user_prompt_template', 'Analisis: {content}')
            ->set('output_schema', '{"type":"object","properties":{"result":{"type":"string"}}}')
            ->call('save')
            ->assertSet('showFormModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ai_prompt_templates', [
            'name'        => 'Template QA Test',
            'source_type' => 'article',
        ]);
    }

    public function test_validasi_name_wajib_dengan_pesan_indonesia(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->set('name', '')
            ->set('source_type', 'article')
            ->set('system_prompt', 'Sistem prompt.')
            ->set('user_prompt_template', 'User template.')
            ->set('output_schema', '{"type":"object"}')
            ->call('save')
            ->assertHasErrors(['name'])
            ->assertSee('Nama Template wajib diisi.');
    }

    public function test_validasi_system_prompt_wajib_dengan_pesan_indonesia(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->set('name', 'Template Valid')
            ->set('source_type', 'article')
            ->set('system_prompt', '')
            ->set('user_prompt_template', 'User template.')
            ->set('output_schema', '{"type":"object"}')
            ->call('save')
            ->assertHasErrors(['system_prompt'])
            ->assertSee('Prompt Utama (System Prompt) wajib diisi.');
    }

    public function test_validasi_user_prompt_template_wajib_dengan_pesan_indonesia(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->set('name', 'Template Valid')
            ->set('source_type', 'article')
            ->set('system_prompt', 'Sistem prompt.')
            ->set('user_prompt_template', '')
            ->set('output_schema', '{"type":"object"}')
            ->call('save')
            ->assertHasErrors(['user_prompt_template'])
            ->assertSee('User Prompt Template wajib diisi.');
    }

    public function test_validasi_output_schema_wajib_dengan_pesan_indonesia(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->set('name', 'Template Valid')
            ->set('source_type', 'article')
            ->set('system_prompt', 'Sistem prompt.')
            ->set('user_prompt_template', 'User template.')
            ->set('output_schema', '')
            ->call('save')
            ->assertHasErrors(['output_schema'])
            ->assertSee('Output Schema (JSON Schema) wajib diisi.');
    }

    public function test_admin_bisa_edit_template(): void
    {
        $template = AiPromptTemplate::factory()->create([
            'name'        => 'Template Lama',
            'source_type' => 'social',
            'is_default'  => false,
        ]);

        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->call('edit', $template->id)
            ->assertSet('showFormModal', true)
            ->assertSet('isEditing', true)
            ->assertSet('name', 'Template Lama')
            ->assertSet('source_type', 'social');
    }

    public function test_admin_bisa_hapus_template_non_default(): void
    {
        $template = AiPromptTemplate::factory()->create([
            'is_default' => false,
        ]);

        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->call('requestDelete', $template->id)
            ->assertSet('confirmingDelete', true)
            ->call('deleteConfirmed')
            ->assertSet('confirmingDelete', false);

        $this->assertSoftDeleted('ai_prompt_templates', ['id' => $template->id]);
    }

    public function test_tidak_bisa_hapus_template_default(): void
    {
        $template = AiPromptTemplate::factory()->create([
            'is_default' => true,
        ]);

        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->call('requestDelete', $template->id)
            ->assertSet('confirmingDelete', false);

        $this->assertDatabaseHas('ai_prompt_templates', ['id' => $template->id]);
    }

    public function test_admin_bisa_toggle_status_template_non_default(): void
    {
        $template = AiPromptTemplate::factory()->create([
            'is_active'  => true,
            'is_default' => false,
        ]);

        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->call('toggleStatus', $template->id);

        $this->assertDatabaseHas('ai_prompt_templates', [
            'id'        => $template->id,
            'is_active' => false,
        ]);
    }

    public function test_tidak_bisa_nonaktifkan_template_default_aktif(): void
    {
        $template = AiPromptTemplate::factory()->create([
            'is_active'  => true,
            'is_default' => true,
        ]);

        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->call('toggleStatus', $template->id);

        $this->assertDatabaseHas('ai_prompt_templates', [
            'id'        => $template->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_bisa_set_default_template_aktif(): void
    {
        $template1 = AiPromptTemplate::factory()->create([
            'source_type' => 'report',
            'is_active'   => true,
            'is_default'  => true,
        ]);
        $template2 = AiPromptTemplate::factory()->create([
            'source_type' => 'report',
            'is_active'   => true,
            'is_default'  => false,
        ]);

        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->call('setDefault', $template2->id);

        $this->assertDatabaseHas('ai_prompt_templates', ['id' => $template2->id, 'is_default' => true]);
        $this->assertDatabaseHas('ai_prompt_templates', ['id' => $template1->id, 'is_default' => false]);
    }

    public function test_tidak_bisa_set_default_template_nonaktif(): void
    {
        $template = AiPromptTemplate::factory()->create([
            'is_active'  => false,
            'is_default' => false,
        ]);

        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->call('setDefault', $template->id);

        $this->assertDatabaseHas('ai_prompt_templates', ['id' => $template->id, 'is_default' => false]);
    }

    public function test_pencarian_bisa_filter_template(): void
    {
        AiPromptTemplate::factory()->create(['name' => 'Analisis Berita Utama', 'source_type' => 'article', 'is_default' => false]);
        AiPromptTemplate::factory()->create(['name' => 'Laporan Sosial Media', 'source_type' => 'social', 'is_default' => false]);

        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->set('search', 'Berita Utama')
            ->assertSee('Analisis Berita Utama')
            ->assertDontSee('Laporan Sosial Media');
    }

    public function test_modal_trash_bisa_dibuka_dan_ditutup(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->call('openTrashModal')
            ->assertSet('showTrashModal', true)
            ->call('closeTrashModal')
            ->assertSet('showTrashModal', false);
    }

    public function test_admin_bisa_pulihkan_template_dari_trash(): void
    {
        $template = AiPromptTemplate::factory()->create(['is_default' => false]);
        $template->delete();

        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->call('openTrashModal')
            ->call('confirmRestoreTemplate', $template->id)
            ->assertSet('confirmingRestoreTemplateId', $template->id)
            ->call('restoreTemplateConfirmed');

        $this->assertNotSoftDeleted('ai_prompt_templates', ['id' => $template->id]);
    }

    public function test_admin_bisa_hapus_permanen_template_dari_trash(): void
    {
        $template = AiPromptTemplate::factory()->create(['is_default' => false]);
        $template->delete();

        $this->actingAs($this->admin);
        Livewire::test(AiPromptTemplates::class)
            ->call('openTrashModal')
            ->call('confirmForceDeleteTemplate', $template->id)
            ->assertSet('confirmingForceDeleteTemplateId', $template->id)
            ->call('forceDeleteTemplateConfirmed');

        $this->assertDatabaseMissing('ai_prompt_templates', ['id' => $template->id]);
    }
}
