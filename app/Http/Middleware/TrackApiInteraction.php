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
            $model = $this->findModelInRouteParameters($request, $modelClass)
                ?? $this->findModelFromResponse($response, $modelClass, $request);

            if ($model && isset($model->id)) {
                try {
                    ApiInteraction::create([
                        'trackable_type' => get_class($model),
                        'trackable_id' => $model->id,
                        'created_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    // Log the error but don't prevent the response from being returned
                    // Tracking failures should not impact user experience
                    \Log::error('Failed to track API interaction', [
                        'model' => get_class($model),
                        'id' => $model->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $response;
    }

    /**
     * Find the model instance in route parameters.
     */
    private function findModelInRouteParameters(Request $request, string $modelClass): ?object
    {
        $routeParameters = $request->route()->parameters();
        
        foreach ($routeParameters as $parameter) {
            if (is_object($parameter)) {
                $parameterClass = get_class($parameter);
                if ($parameterClass === $modelClass || is_subclass_of($parameter, $modelClass)) {
                    return $parameter;
                }
            }
        }
        
        return null;
    }

    /**
     * Find the model from the response content.
     */
    private function findModelFromResponse(Response $response, string $modelClass, Request $request): ?object
    {
        if (!$response->getContent()) {
            return null;
        }

        $content = json_decode($response->getContent(), true);
        $id = $content['data']['id'] ?? $content['id'] ?? null;
        if (!$id) {
            return null;
        }

        // For some models like Trip, the response might use short_id or other identifiers
        // Try to get the original route parameter value
        $routeParameters = $request->route()->parameters();
        foreach ($routeParameters as $param) {
            if (is_numeric($param) || is_string($param)) {
                // Try to find the model using the route parameter
                // This handles both regular IDs and custom route keys like short_id
                // Create a temporary instance only once to get the route key name
                static $routeKeyCache = [];
                if (!isset($routeKeyCache[$modelClass])) {
                    $instance = new $modelClass();
                    $routeKeyCache[$modelClass] = $instance->getRouteKeyName();
                }
                $routeKeyName = $routeKeyCache[$modelClass];
                
                $model = $modelClass::where(function ($query) use ($param, $routeKeyName) {
                    $query->where($routeKeyName, $param);
                    if ($routeKeyName !== 'id' && is_numeric($param)) {
                        $query->orWhere('id', $param);
                    }
                })->first();

                if ($model) {
                    return $model;
                }
            }
        }

        return null;
    }
}
