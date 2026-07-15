<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('social_media_items')) {
            return;
        }

        DB::statement('ALTER TABLE social_media_items ALTER COLUMN posted_at DROP NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('social_media_items')) {
            return;
        }

        DB::statement('UPDATE social_media_items SET posted_at = created_at WHERE posted_at IS NULL');
        DB::statement('ALTER TABLE social_media_items ALTER COLUMN posted_at SET NOT NULL');
    }
};
