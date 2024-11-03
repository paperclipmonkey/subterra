<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiIsAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if(! $request->user()) 
        {
            return response()->json(['error' => 'No user logged in'], 401);
        }
        return $next($request);
    }
}
