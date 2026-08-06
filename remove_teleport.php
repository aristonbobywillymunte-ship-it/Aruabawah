<?php
$files = [
    'resources/views/livewire/admin/system-health.blade.php',
    'resources/views/livewire/admin/pipeline-monitor.blade.php'
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Replace <template x-teleport="body"> <div wire:key="..."> @if(...) with @if(...)
    $content = preg_replace(
        '/<template\s+x-teleport="body">\s*<div\s+wire:key="[^"]+">\s*(@if\([^)]+\))/s',
        '$1',
        $content
    );
    
    // Replace @endif </div> </template> with @endif
    $content = preg_replace(
        '/(@endif)\s*<\/div>\s*<\/template>/s',
        '$1',
        $content
    );
    
    file_put_contents($file, $content);
}
