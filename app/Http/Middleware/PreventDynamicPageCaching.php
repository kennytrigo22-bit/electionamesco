<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventDynamicPageCaching
{
    /**
     * Stops shared/CDN caches (e.g. a host's LiteSpeed/Varnish full-page cache) from
     * serving a stale page whose CSRF token no longer matches the live session —
     * the cause of recurring "419 Page Expired" / "405 Method Not Allowed" loops.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
