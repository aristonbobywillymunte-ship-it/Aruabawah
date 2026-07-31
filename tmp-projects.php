<?php
require '/Users/unity/Documents/proyek baru/vendor/autoload.php';
$app = require '/Users/unity/Documents/proyek baru/bootstrap/app.php';
$kernel = $app->make(IlluminateContractsConsoleKernel::class);
$kernel->bootstrap();
$user = AppModelsUser::find(4);
$projects = AppModelsProject::query()
  ->whereHas('users', fn($q) => $q->where('users.id', 4))
  ->get(['id','name']);
$out = [];
foreach ($projects as $project) {
  $counts = AppModelsSocialMediaItem::query()
    ->whereHas('projects', fn($q) => $q->where('projects.id', $project->id))
    ->selectRaw('platform, count(*) as c')
    ->groupBy('platform')
    ->pluck('c','platform')
    ->all();
  $out[] = ['id' => $project->id, 'name' => $project->name, 'counts' => $counts];
}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
?>