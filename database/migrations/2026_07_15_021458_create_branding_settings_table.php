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
        Schema::create('branding_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->default('ARUSBAWAH');
            $table->string('app_logo_path')->nullable();
            $table->timestamps();
        });

        // Insert initial/default branding
        \Illuminate\Support\Facades\DB::table('branding_settings')->insert([
            'app_name' => 'ARUSBAWAH',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branding_settings');
    }
};
