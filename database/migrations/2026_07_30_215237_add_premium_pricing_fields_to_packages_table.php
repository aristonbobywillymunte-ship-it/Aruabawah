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
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->default(0.00)->after('description');
            $table->text('social_media_features')->nullable()->after('price'); // JSON or list of text
            $table->text('news_portal_features')->nullable()->after('social_media_features'); // JSON or list of text
            $table->text('advantages')->nullable()->after('news_portal_features'); // JSON or list of text (keuntungan)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['price', 'social_media_features', 'news_portal_features', 'advantages']);
        });
    }
};
