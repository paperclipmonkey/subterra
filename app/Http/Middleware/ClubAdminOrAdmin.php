<?php

namespace App\Http\Middleware;

use App\Models\Club;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClubAdminOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json(['error' => 'User is not authenticated to perform that action'], 401);
        }
        if ($request->user()->hasRole('platform_admin')) {
            return $next($request);
        }
        // Check if the user is a club admin
        $club = $request->route('club');
        if (!$club instanceof Club) {
            return response()->json(['error' => 'Club not found'], 404);
        }
        if ($club->users()->where('user_id', $request->user()->id)->wherePivot('is_admin', true)->exists()) {
            return $next($request);
        }

        return response()->json(['error' => 'User is not authorised to perform that action'], 403);
    }
}
