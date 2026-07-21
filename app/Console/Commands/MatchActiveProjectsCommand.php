<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MatchActiveProjectsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:match-active-projects {--dry-run : Deprecated legacy option, kept for compatibility}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deprecated legacy matching command. Use project filter resync instead.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->warn('Command legacy matching sudah dinonaktifkan.');
        $this->line('Gunakan filter project + resync project sebagai alur utama.');

        return 0;
    }
}
