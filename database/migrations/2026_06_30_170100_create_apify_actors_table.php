<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apify_actors', function (Blueprint $table) {
            $table->id();
            $table->string('platform');
            $table->string('actor_name');
            $table->string('actor_slug');
            $table->string('function_type');
            $table->string('default_keyword')->nullable();
            $table->unsignedInteger('default_limit')->default(20);
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status')->nullable();
            $table->text('last_run_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apify_actors');
    }
};
