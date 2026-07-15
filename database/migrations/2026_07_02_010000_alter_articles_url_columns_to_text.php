<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE articles ALTER COLUMN url TYPE TEXT');
        DB::statement('ALTER TABLE articles ALTER COLUMN canonical_url TYPE TEXT');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE articles ALTER COLUMN url TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE articles ALTER COLUMN canonical_url TYPE VARCHAR(255)');
    }
};
