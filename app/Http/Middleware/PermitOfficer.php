<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Booking management is open to anyone assigned as an officer of a permit, not
 * just holders of the access_officer role — officers are picked from any user,
 * and the role would also let them edit every permit.
 */
class PermitOfficer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'User is not authenticated to perform that action'], 401);
        }

        if (!$user->hasRole(['access_officer', 'platform_admin']) && !$user->managedPermits()->exists()) {
            return response()->json(['error' => 'User is not authorised to perform that action'], 403);
        }

        return $next($request);
    }
}
