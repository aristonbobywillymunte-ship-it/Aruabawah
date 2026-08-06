<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$settings = Illuminate\Support\Facades\DB::table('apify_settings')->first();
$token = env('APIFY_TOKEN');
if (!$token && $settings) {
    $token = match((int) $settings->active_token_index) {
        1 => decrypt($settings->api_token_backup_1, false),
        2 => decrypt($settings->api_token_backup_2, false),
        3 => decrypt($settings->api_token_backup_3, false),
        default => decrypt($settings->api_token, false),
    };
}
$url = 'https://api.apify.com/v2/actor-runs/bq2mxB2fGBdu2aLJw/dataset/items?token=' . $token;
$resp = Illuminate\Support\Facades\Http::get($url);
if ($resp->failed()) {
    echo 'Failed: ' . $resp->body() . PHP_EOL;
} else {
    echo 'Items Count: ' . count($resp->json()) . PHP_EOL;
    print_r(array_map(function($item) {
        return [
            'submittedVideoUrl' => $item['submittedVideoUrl'] ?? $item['videoWebUrl'] ?? $item['webVideoUrl'] ?? null,
            'text' => $item['text'] ?? null,
            'cid' => $item['cid'] ?? null,
        ];
    }, $resp->json()));
}
