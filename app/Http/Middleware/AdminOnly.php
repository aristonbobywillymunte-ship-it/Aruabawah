<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    /**
     * Hanya izinkan user dengan role 'admin' masuk.
     * User biasa → redirect ke dashboard dengan pesan error.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Akses ditolak. Hanya admin yang diizinkan.'], 403);
            }

            abort(403, 'Akses ditolak. Hanya administrator yang dapat mengakses halaman ini.');
        }

        return $next($request);
    }
}
