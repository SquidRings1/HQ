<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        // Filament/Livewire/Alpine.js require inline scripts, eval, and blob: workers.
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; "
            ."img-src 'self' data: blob:; "
            ."style-src 'self' 'unsafe-inline'; "
            ."script-src 'self' 'unsafe-inline' 'unsafe-eval' blob:; "
            ."worker-src 'self' blob:; "
            ."connect-src 'self'; "
            ."form-action 'self'; "
            ."frame-ancestors 'none'; "
            ."base-uri 'self'"
        );

        return $response;
    }
}
