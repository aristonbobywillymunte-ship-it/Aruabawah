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
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->string('reader_basis')->nullable()->after('quality_confidence');
            $table->string('actual_metric_used')->nullable()->after('reader_basis');
            $table->bigInteger('actual_metric_value')->nullable()->after('actual_metric_used');
            $table->bigInteger('effective_readers')->nullable()->after('actual_metric_value');
            $table->text('reader_basis_reason')->nullable()->after('effective_readers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->dropColumn(['reader_basis', 'actual_metric_used', 'actual_metric_value', 'effective_readers', 'reader_basis_reason']);
        });
    }
};
