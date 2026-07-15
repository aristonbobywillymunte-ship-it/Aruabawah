<?php

namespace App\Console\Commands;

use App\Models\NewsSource;
use App\Services\NewsSourceIconResolver;
use Illuminate\Console\Command;

class BackfillNewsSourceIcons extends Command
{
    protected $signature = 'news-sources:backfill-icons
                            {--apply : Persist icon_url updates instead of dry-run}
                            {--limit=25 : Maximum number of sources to inspect}';

    protected $description = 'Resolve and backfill icon_url for news sources from the source page or domain.';

    public function handle(NewsSourceIconResolver $resolver): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(1, (int) $this->option('limit'));

        $sources = NewsSource::query()
            ->orderBy('name')
            ->limit($limit)
            ->get();

        if ($sources->isEmpty()) {
            $this->info('Tidak ada news source yang ditemukan.');
            return self::SUCCESS;
        }

        $this->info('Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN'));
        $updated = 0;

        foreach ($sources as $source) {
            $resolved = $resolver->resolve($source->base_url ?: $source->domain, $source->domain, $source->name);

            $this->line(sprintf(
                'id=%d | name=%s | domain=%s | current=%s | resolved=%s',
                $source->id,
                $source->name,
                $source->domain,
                $source->icon_url ?: 'null',
                $resolved ?: 'null'
            ));

            if (! $apply || ! $resolved || $resolved === $source->icon_url) {
                continue;
            }

            $source->update([
                'icon_url' => $resolved,
            ]);

            $updated++;
        }

        if ($apply) {
            $this->info("Selesai. icon_url diperbarui untuk {$updated} source.");
        } else {
            $this->info('Dry-run selesai.');
        }

        return self::SUCCESS;
    }
}
