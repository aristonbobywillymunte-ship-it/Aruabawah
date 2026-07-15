<?php

namespace App\Queue\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

class AiAnalysisRateThrottle
{
    public function __construct(private int $delaySeconds = 5)
    {
    }

    public function handle(object $job, Closure $next): mixed
    {
        $key = 'ai-analysis:last-dispatch-at';
        $lastDispatchAt = Cache::get($key);

        if (is_numeric($lastDispatchAt)) {
            $elapsed = now()->timestamp - (int) $lastDispatchAt;
            if ($elapsed < $this->delaySeconds) {
                $job->release(max(1, $this->delaySeconds - $elapsed));
                return null;
            }
        }

        Cache::put($key, now()->timestamp, now()->addDay());

        return $next($job);
    }
}
