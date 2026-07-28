<?php

use App\Models\AiPromptTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('ai_prompt_templates')
            ->where('source_type', 'report')
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return;
        }

        AiPromptTemplate::query()->create([
            'name' => 'Laporan AI Media Intelligence',
            'source_type' => 'report',
            'system_prompt' => AiPromptTemplate::reportAiSystemPrompt(),
            'user_prompt_template' => AiPromptTemplate::reportAiUserPromptTemplate(),
            'output_schema' => AiPromptTemplate::reportAiOutputSchema(),
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    public function down(): void
    {
        DB::table('ai_prompt_templates')
            ->where('source_type', 'report')
            ->where('name', 'Laporan AI Media Intelligence')
            ->delete();
    }
};
