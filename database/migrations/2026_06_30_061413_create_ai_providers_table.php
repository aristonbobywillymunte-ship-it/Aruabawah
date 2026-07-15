<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider_type'); // OpenAI, Gemini, Anthropic, Groq, OpenRouter, Ollama, Custom API
            $table->string('base_url')->nullable();
            $table->string('api_key')->nullable();
            $table->string('model_name');
            $table->decimal('temperature', 3, 2)->default(0.7);
            $table->integer('max_tokens')->default(2048);
            $table->text('custom_headers')->nullable(); // JSON string
            $table->text('custom_body_template')->nullable(); // JSON/Text
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable(); // success, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
