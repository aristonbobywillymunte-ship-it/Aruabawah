<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom role ke tabel users
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('email'); // 'admin' | 'user'
        });

        // 2. Buat tabel pivot project_user untuk assignment akses
        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['project_id', 'user_id']); // Cegah duplikasi
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_user');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
