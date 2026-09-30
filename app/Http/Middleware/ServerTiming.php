<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a Server-Timing header (shown in browser devtools) splitting a request's
 * time into DB connect, queries and total app time.
 */
class ServerTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        DB::connection()->getPdo();
        $connectMs = (microtime(true) - $start) * 1000;

        $queryMs = 0.0;
        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$queryMs, &$queries): void {
            $queryMs += $query->time;
            ++$queries;
        });

        $response = $next($request);

        $appMs = (microtime(true) - (\defined('LARAVEL_START') ? LARAVEL_START : $start)) * 1000;
        $response->headers->set('Server-Timing', \sprintf(
            'connect;dur=%.1f, db;dur=%.1f;desc="%d queries", app;dur=%.1f',
            $connectMs,
            $queryMs,
            $queries,
            $appMs,
        ));

        return $response;
    }
}
