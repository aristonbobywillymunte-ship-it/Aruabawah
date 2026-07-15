<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analysis_dispatch_states', function (Blueprint $table) {
            $table->id();
            $table->string('analyzable_type');
            $table->unsignedBigInteger('analyzable_id');
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('prompt_template_id')->nullable()->constrained('ai_prompt_templates')->nullOnDelete();
            $table->string('provider_context_hash', 64);
            $table->string('dispatch_key', 191)->unique();
            $table->string('status')->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'analyzable_type', 'analyzable_id'], 'ai_dispatch_state_analyzable_idx');
            $table->index(['status', 'next_retry_at'], 'ai_dispatch_state_retry_idx');
            $table->index(['project_id', 'prompt_template_id'], 'ai_dispatch_state_project_template_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analysis_dispatch_states');
    }
};
