<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates access to Pip endpoints.
 *
 * A user may use Pip if they are a platform_admin OR have been granted the
 * `pip_access` role explicitly by an admin from the user admin panel.
 */
class PipAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'User is not authenticated to perform that action'], 401);
        }

        if (!$user->canUsePip()) {
            return response()->json(['error' => 'User is not authorised to use Pip.'], 403);
        }

        return $next($request);
    }
}
