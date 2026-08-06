<?php
$files = [
    'resources/views/livewire/admin/users-manager.blade.php',
    'resources/views/livewire/admin/telegram-settings.blade.php',
    'resources/views/livewire/admin/news-sources.blade.php',
    'resources/views/livewire/admin/ai-prompt-templates.blade.php',
    'resources/views/livewire/admin/apify-configuration.blade.php',
    'resources/views/livewire/admin/ai-providers.blade.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    // Matches exactly: \s*<p class="text-\[10px\] text-slate-400 mt-0\.5">.*?</p>
    $content = preg_replace('/\n\s*<p class="text-\[10px\] text-slate-400 mt-0\.5">.*?<\/p>/', '', $content);

    file_put_contents($file, $content);
}
