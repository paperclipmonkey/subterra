<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CurrentUserOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json(['error' => 'User is not authenticated to perform that action'], 401);
        }
        // platform_admin only: is_admin is true for ANY staff role (duty/access
        // officer, data admin), none of which should edit or delete other accounts.
        if ($request->user()->hasRole('platform_admin')) {
            return $next($request);
        }
        $targetUser = $request->route('user') ?? $request->route('user_without_scopes');

        if ($targetUser instanceof \App\Models\User && $targetUser->id === $request->user()->id) {
            return $next($request);
        }

        return response()->json(['error' => 'User is not authorised to perform that action'], 403);
    }
}
