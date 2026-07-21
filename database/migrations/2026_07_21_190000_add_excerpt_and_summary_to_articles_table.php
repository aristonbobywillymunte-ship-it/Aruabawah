<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            if (! Schema::hasColumn('articles', 'excerpt')) {
                $table->text('excerpt')->nullable()->after('content');
            }

            if (! Schema::hasColumn('articles', 'summary')) {
                $table->text('summary')->nullable()->after('excerpt');
            }
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            if (Schema::hasColumn('articles', 'summary')) {
                $table->dropColumn('summary');
            }

            if (Schema::hasColumn('articles', 'excerpt')) {
                $table->dropColumn('excerpt');
            }
        });
    }
};
