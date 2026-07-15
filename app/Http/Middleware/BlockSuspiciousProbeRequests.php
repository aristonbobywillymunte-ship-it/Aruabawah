<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockSuspiciousProbeRequests
{
    /**
     * Common probe targets that don't belong to this Laravel app.
     */
    private const EXACT_PATHS = [
        '.env',
        'phpinfo.php',
        'info.php',
        'setup.php',
        'config.php',
        'wp-login.php',
        'wp-config.php',
        'xmlrpc.php',
    ];

    private const PREFIXES = [
        '.git',
        'wp-',
        'wordpress',
        'vendor/phpunit',
        'cgi-bin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');
        $normalized = strtolower($path === '' ? '/' : $path);

        if ($this->shouldBlock($normalized)) {
            abort(404);
        }

        return $next($request);
    }

    private function shouldBlock(string $path): bool
    {
        if ($path === '/') {
            return false;
        }

        if (in_array($path, self::EXACT_PATHS, true)) {
            return true;
        }

        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return (bool) preg_match('/\.(php[0-9]*|phtml|asp|aspx|cgi|env|bak|old|sql)$/', $path);
    }
}
