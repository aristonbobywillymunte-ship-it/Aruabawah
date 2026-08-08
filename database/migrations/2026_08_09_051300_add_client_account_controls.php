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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('parent_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('client_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_create_projects')->default(true);
            $table->boolean('can_edit_projects')->default(false);
            $table->boolean('can_delete_projects')->default(false);
            $table->integer('max_projects')->nullable();
            $table->integer('max_keywords_per_project')->nullable();
            $table->timestamps();
        });

        Schema::create('client_package_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->integer('max_projects')->nullable();
            $table->integer('max_keywords_per_project')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['max_projects', 'max_keywords_per_project']);
        });

        Schema::dropIfExists('client_package_permissions');
        Schema::dropIfExists('client_settings');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['parent_user_id']);
            $table->dropColumn('parent_user_id');
        });
    }
};
