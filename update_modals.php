<?php
$files = [
    'resources/views/livewire/admin/system-health.blade.php',
    'resources/views/livewire/admin/pipeline-monitor.blade.php'
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Pattern to find @if(...) followed by <template x-teleport="body">
    // and swap their order, while adding a wire:key div inside the template.
    
    // Because parsing this with regex is error prone, I will do it manually for each known modal.
}
