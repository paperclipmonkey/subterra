<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiIsAdmin
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json(['error' => 'User is not authenticated to perform that action'], 401);
        }

        if (empty($roles)) {
            $roles = ['platform_admin'];
        }

        if (!$request->user()->hasRole($roles)) {
            return response()->json(['error' => 'User is not authorised to perform that action'], 403);
        }

        return $next($request);
    }
}
