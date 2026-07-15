<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apify_settings', function (Blueprint $table) {
            $table->id();
            $table->text('api_token')->nullable();
            $table->string('connection_status')->default('belum_dicek');
            $table->string('last_test_status')->nullable();
            $table->string('last_test_dataset_id')->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamp('last_test_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apify_settings');
    }
};
