<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('apify_dispatch_states', function (Blueprint $table) {
            $table->id();
            $table->string('dispatch_key')->unique();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('actor_id')->index();
            $table->string('platform')->index();
            $table->string('keyword')->index();
            $table->string('normalized_keyword')->index();
            $table->timestamp('window_start')->nullable()->index();
            $table->timestamp('window_end')->nullable()->index();
            
            $table->enum('status', ['queued', 'processing', 'success', 'failed', 'retry_wait', 'cancelled'])
                  ->default('queued')
                  ->index();
                  
            $table->string('run_id')->nullable()->index();
            $table->integer('attempts')->default(0);
            
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            
            $table->timestamps();
            
            // Relasi ke projects jika constraint diperlukan
            // $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apify_dispatch_states');
    }
};
