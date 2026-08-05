<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The master switch for the callout feature (`features.callouts`).
 *
 * Hiding the UI is not enough on its own: a bookmarked URL, a cached client or
 * a direct API call would still reach the endpoints. This closes that off so
 * "callouts are disabled" means the same thing everywhere.
 *
 * Deliberately global and role-blind — per-user access is a separate question
 * handled by User::canUseCallout().
 */
class CalloutsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('features.callouts')) {
            return response()->json(['error' => 'Callouts are not currently available.'], 403);
        }

        return $next($request);
    }
}
