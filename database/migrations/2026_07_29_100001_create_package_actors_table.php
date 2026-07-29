<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_actors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('apify_actor_id')->constrained('apify_actors')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->decimal('cost_per_run_usd', 8, 4)->nullable()->comment('Override biaya per run; null = pakai nilai global actor');
            $table->timestamps();

            $table->unique(['package_id', 'apify_actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_actors');
    }
};
