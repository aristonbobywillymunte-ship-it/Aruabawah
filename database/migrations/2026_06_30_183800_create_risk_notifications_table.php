<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_analysis_result_id')->constrained('ai_analysis_results')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, sending, sent, failed
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_notifications');
    }
};
