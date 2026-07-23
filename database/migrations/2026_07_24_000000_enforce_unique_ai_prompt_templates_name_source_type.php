<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $duplicates = DB::table('ai_prompt_templates')
                ->select('name', 'source_type')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('name', 'source_type')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $duplicate) {
                $keepId = DB::table('ai_prompt_templates')
                    ->where('name', $duplicate->name)
                    ->where('source_type', $duplicate->source_type)
                    ->orderByDesc('is_default')
                    ->orderByDesc('is_active')
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->value('id');

                if (! $keepId) {
                    continue;
                }

                DB::table('ai_prompt_templates')
                    ->where('name', $duplicate->name)
                    ->where('source_type', $duplicate->source_type)
                    ->where('id', '!=', $keepId)
                    ->delete();
            }
        });

        Schema::table('ai_prompt_templates', function (Blueprint $table) {
            $table->unique(['name', 'source_type'], 'ai_prompt_templates_name_source_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ai_prompt_templates', function (Blueprint $table) {
            $table->dropUnique('ai_prompt_templates_name_source_type_unique');
        });
    }
};
