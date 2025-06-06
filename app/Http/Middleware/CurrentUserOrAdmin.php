<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CurrentUserOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if(! $request->user())
        {
            return response()->json(['error' => 'User is not authenticated to perform that action'], 401);
        }
        if($request->user()->is_admin)
        {
            return $next($request);
        }
        if($request->route('user') && $request->route('user')->id === $request->user()->id)
        {
            return $next($request);
        }
        return response()->json(['error' => 'User is not authorised to perform that action'], 403);
    }
}
