<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_telegram_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('chat_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Unique constraint to prevent duplicate recipient chat ID per project
            $table->unique(['project_id', 'chat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_telegram_recipients');
    }
};
