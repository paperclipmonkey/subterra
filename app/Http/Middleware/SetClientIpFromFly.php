<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Override the client IP with the value from the Fly-Client-IP header.
 *
 * Fly.io's edge proxy unconditionally sets this header to the real client IP
 * before forwarding the request to the application. Unlike X-Forwarded-For,
 * it cannot be injected or spoofed by the end-client, making it the
 * authoritative source of the client IP when running on Fly.io.
 *
 * This middleware should run early in the stack, before any rate-limiting
 * or IP-based access-control middleware.
 *
 * References: https://fly.io/docs/networking/request-headers/
 */
class SetClientIpFromFly
{
    public function handle(Request $request, Closure $next): Response
    {
        $flyClientIp = $request->header('Fly-Client-IP');

        if ($flyClientIp && filter_var($flyClientIp, FILTER_VALIDATE_IP)) {
            $request->server->set('REMOTE_ADDR', $flyClientIp);
        }

        return $next($request);
    }
}
