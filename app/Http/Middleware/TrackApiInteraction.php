<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiInteraction;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackApiInteraction
{
    /**
     * Handle an incoming request and track the interaction.
     */
    public function handle(Request $request, Closure $next, string $modelClass): Response
    {
        $response = $next($request);

        // Only track successful GET requests that retrieve a specific resource
        if ($response->getStatusCode() === 200 && $request->isMethod('GET')) {
            // Get the model from the route binding
            $routeParameters = $request->route()->parameters();
            
            // Find the first model instance in route parameters
            foreach ($routeParameters as $parameter) {
                if (is_object($parameter) && is_a($parameter, $modelClass)) {
                    ApiInteraction::create([
                        'trackable_type' => get_class($parameter),
                        'trackable_id' => $parameter->id,
                        'created_at' => now(),
                    ]);
                    break;
                }
            }
        }

        return $response;
    }
}
