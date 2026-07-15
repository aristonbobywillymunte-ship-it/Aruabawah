<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reach_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->morphs('assessable');
            $table->string('method');
            $table->string('score_version', 20);
            $table->decimal('audience_capacity_score', 5, 2);
            $table->decimal('observed_consumption_score', 5, 2)->nullable();
            $table->decimal('interaction_score', 5, 2);
            $table->decimal('diffusion_score', 5, 2);
            $table->decimal('media_context_score', 5, 2);
            $table->decimal('potential_hybrid_score', 5, 2);
            $table->integer('potential_reach_score');
            $table->string('potential_reach_level');
            $table->decimal('local_relevance_score', 5, 2);
            $table->string('relevance_status');
            $table->decimal('adjusted_local_hybrid_score', 5, 2);
            $table->integer('adjusted_local_reach_score');
            $table->string('adjusted_local_reach_level');
            $table->integer('confidence_score');
            $table->string('confidence_level');
            $table->boolean('is_exact_reach')->default(false);
            $table->json('signals_json')->nullable();
            $table->text('explanation')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'assessable_type', 'assessable_id', 'method', 'score_version'], 'reach_assessments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reach_assessments');
    }
};
