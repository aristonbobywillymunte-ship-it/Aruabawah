<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->string('analysis_status')->default('success')->after('raw_response');
            $table->text('validation_errors')->nullable()->after('analysis_status');
        });
    }

    public function down(): void
    {
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->dropColumn([
                'analysis_status',
                'validation_errors',
            ]);
        });
    }
};
