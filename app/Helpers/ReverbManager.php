<?php

namespace App\Helpers;

class ReverbManager
{
    /**
     * Check if the Reverb server is running in the background.
     */
    public static function isRunning(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        $output = [];
        $resultCode = 0;
        // Search for the specific artisan command running in process list
        exec('pgrep -f "artisan reverb:start"', $output, $resultCode);

        return ($resultCode === 0 && !empty($output));
    }

    /**
     * Start the Reverb server in the background.
     */
    public static function start(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        if (self::isRunning()) {
            return true;
        }

        // Run reverb:start in the background using nohup to keep it running, wrapping path in quotes to handle spaces
        $artisan = base_path('artisan');
        exec("nohup php \"{$artisan}\" reverb:start > /dev/null 2>&1 &");
        
        // Sleep a short moment to allow it to initialize
        usleep(500000); // 500ms
        
        return self::isRunning();
    }

    /**
     * Stop the Reverb server.
     */
    public static function stop(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        // Forcefully kill any process matching the command
        exec('pkill -f "artisan reverb:start"');
        
        // Sleep a short moment to allow it to terminate
        usleep(500000); // 500ms
        
        return !self::isRunning();
    }
}
