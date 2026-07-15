<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncSQLiteToPostgres extends Command
{
    protected $signature = 'database:sync-sqlite-to-pgsql';

    protected $description = 'Copy required configuration and identity tables from SQLite into PostgreSQL';

    public function handle(): int
    {
        $source = DB::connection('sqlite');
        $target = DB::connection('pgsql');

        $importOrder = [
            'users',
            'projects',
            'apify_settings',
            'apify_actors',
            'ai_providers',
            'ai_prompt_templates',
            'telegram_settings',
            'scraping_settings',
            'news_sources',
            'project_user',
        ];

        $resultTables = [
            'articles',
            'project_articles',
            'social_media_items',
            'project_social_media_items',
            'candidate_links',
            'scraping_items',
            'ai_analysis_results',
            'risk_notifications',
        ];

        $targetTables = array_merge($importOrder, $resultTables);

        if (! $this->ensureTargetReady($targetTables)) {
            return self::FAILURE;
        }

        $this->info('Starting SQLite -> PostgreSQL data sync.');

        foreach ($importOrder as $table) {
            $this->importTable($source, $target, $table);
        }

        $this->resetSequences($target, $importOrder);

        $this->line('');
        $this->info('PostgreSQL counts after sync:');
        foreach ($targetTables as $table) {
            $this->line(sprintf(' - %s: %d', $table, $this->tableCount('pgsql', $table)));
        }

        $this->info('SQLite -> PostgreSQL data sync completed successfully.');

        return self::SUCCESS;
    }

    protected function ensureTargetReady(array $tables): bool
    {
        $nonEmpty = [];

        foreach ($tables as $table) {
            if (! Schema::connection('pgsql')->hasTable($table)) {
                continue;
            }

            $count = $this->tableCount('pgsql', $table);
            if ($count > 0) {
                $nonEmpty[$table] = $count;
            }
        }

        if ($nonEmpty !== []) {
            $this->error('PostgreSQL target is not empty. Refusing to import to avoid duplicates.');
            foreach ($nonEmpty as $table => $count) {
                $this->line(sprintf(' - %s: %d', $table, $count));
            }
            return false;
        }

        return true;
    }

    protected function importTable($source, $target, string $table): void
    {
        if (! Schema::connection('sqlite')->hasTable($table)) {
            $this->warn("Skipping missing source table: {$table}");
            return;
        }

        if (! Schema::connection('pgsql')->hasTable($table)) {
            $this->warn("Skipping missing target table: {$table}");
            return;
        }

        $rows = $source->table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $count = count($rows);

        if ($count === 0) {
            $this->line(" - {$table}: 0 row(s) copied");
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $target->table($table)->insert($chunk);
        }

        $this->line(" - {$table}: {$count} row(s) copied");
    }

    protected function resetSequences($target, array $tables): void
    {
        foreach ($tables as $table) {
            if (! Schema::connection('pgsql')->hasTable($table)) {
                continue;
            }

            if (! Schema::connection('pgsql')->hasColumn($table, 'id')) {
                continue;
            }

            $maxId = (int) ($target->table($table)->max('id') ?? 0);
            $sequence = sprintf("pg_get_serial_sequence('%s', 'id')", $table);
            $sql = $maxId > 0
                ? sprintf("SELECT setval(%s, %d, true)", $sequence, $maxId)
                : sprintf("SELECT setval(%s, 1, false)", $sequence);

            $target->statement($sql);
        }
    }

    protected function tableCount(string $connection, string $table): int
    {
        if (! Schema::connection($connection)->hasTable($table)) {
            return 0;
        }

        return (int) DB::connection($connection)->table($table)->count();
    }
}
