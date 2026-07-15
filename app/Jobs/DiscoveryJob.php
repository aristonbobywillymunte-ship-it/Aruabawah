<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DiscoveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $params;

    public function __construct(array $params = [])
    {
        $this->params = $params;
    }

    public function handle(): void
    {
        Log::info('[Pipeline] DiscoveryJob started', $this->params);
        Log::warning('[Pipeline] DiscoveryJob skipped because no real discovery source is configured.', $this->params);
    }
}
