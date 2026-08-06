<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$projects = \App\Models\Project::where('status', 'active')->get();
echo 'Total Active Projects: ' . $projects->count() . PHP_EOL;

$priorityService = app(\App\Services\ProjectScrapePriorityService::class);
$prioritized = $priorityService->prioritize($projects);
echo 'Total Prioritized Projects: ' . $prioritized->count() . PHP_EOL;

foreach ($prioritized as $p) {
    echo '- ' . $p->name . ' (ID: ' . $p->id . ')' . PHP_EOL;
    // Check if package disables portal
    if ($p->package_id && $p->package) {
        echo '  Use Portal: ' . ($p->package->use_portal ? 'Yes' : 'No') . PHP_EOL;
        echo '  News Interval: ' . $p->package->news_interval_minutes . PHP_EOL;
    } else {
        echo '  No Package' . PHP_EOL;
    }
}
