<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FilteringJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $item;

    public function __construct(array $item)
    {
        $this->item = $item;
    }

    public function handle(): void
    {
        Log::info('[Pipeline] FilteringJob evaluating target: ' . $this->item['url']);

        // Dummy filter criteria check
        $excludedKeywords = ['saham'];
        $isExcluded = false;

        foreach ($excludedKeywords as $kw) {
            if (str_contains(strtolower($this->item['title']), $kw)) {
                $isExcluded = true;
                break;
            }
        }

        if ($isExcluded) {
            Log::info('[Pipeline] Item filtered out (contains excluded keyword): ' . $this->item['title']);
            return;
        }

        Log::info('[Pipeline] Item passed filter: ' . $this->item['title'] . '. Dispatching ScrapingJob.');
        
        // Dispatch ScrapingJob
        ScrapingJob::dispatch($this->item)->onQueue('news');
    }
}
