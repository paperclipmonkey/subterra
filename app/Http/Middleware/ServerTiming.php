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
    private static float $queryMs = 0.0;

    private static int $queries = 0;

    // Under Octane the event dispatcher outlives the request, so listen once per
    // dispatcher rather than adding a listener on every request.
    private static ?object $listeningOn = null;

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $connection = DB::connection();
        $connection->getPdo();
        $connectMs = (microtime(true) - $start) * 1000;

        $dispatcher = $connection->getEventDispatcher();
        if (self::$listeningOn !== $dispatcher) {
            $connection->listen(static function (QueryExecuted $query): void {
                self::$queryMs += $query->time;
                ++self::$queries;
            });
            self::$listeningOn = $dispatcher;
        }
        self::$queryMs = 0.0;
        self::$queries = 0;

        $response = $next($request);

        $appMs = (microtime(true) - (float) $request->server('REQUEST_TIME_FLOAT', $start)) * 1000;
        $response->headers->set('Server-Timing', \sprintf(
            'connect;dur=%.1f, db;dur=%.1f;desc="%d queries", app;dur=%.1f',
            $connectMs,
            self::$queryMs,
            self::$queries,
            $appMs,
        ));

        return $response;
    }
}
