<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * A trip's route through its cave's 3D survey, as cave-viewer reports it to the
 * page it's embedded in: its link ('#from=…&to=…&via=…'), its length and climb,
 * its stops in turn (each with how far along it is and, where the party noted
 * it, when they got there) and the passages it goes through.
 *
 * Trips with photos are sent as multipart forms, where the route arrives as a
 * JSON string, so it's decoded before validation.
 */
trait ValidatesTripRoute
{
    protected function prepareForValidation(): void
    {
        $route = $this->input('route');
        if (is_string($route)) {
            $decoded = $route === '' ? null : json_decode($route, true);
            $this->merge(['route' => is_array($decoded) ? $decoded : ($route === '' ? null : $route)]);
        }
    }

    /** @return array<string, mixed> */
    protected function routeRules(): array
    {
        return [
            'route' => ['nullable', 'array'],
            'route.link' => ['required_with:route', 'string', 'max:2000', 'starts_with:#from='],
            'route.length' => ['nullable', 'numeric', 'min:0'],
            'route.up' => ['nullable', 'numeric', 'min:0'],
            'route.down' => ['nullable', 'numeric', 'min:0'],
            'route.another' => ['nullable', 'boolean'],
            'route.stops' => ['nullable', 'array', 'max:50'],
            'route.stops.*.name' => ['required', 'string', 'max:255'],
            'route.stops.*.at' => ['nullable', 'numeric', 'min:0'],
            'route.stops.*.time' => ['nullable', 'date'],
            'route.passages' => ['nullable', 'array', 'max:200'],
            'route.passages.*' => ['string', 'max:255'],
        ];
    }

    /**
     * Only the fields above are kept: the route is stored as given, so nothing
     * else rides along in it.
     *
     * @param  array<string, mixed>|null  $route
     * @return array<string, mixed>|null
     */
    public static function cleanRoute(?array $route): ?array
    {
        if ($route === null) {
            return null;
        }
        $keep = array_intersect_key($route, array_flip(['link', 'length', 'up', 'down', 'another', 'stops', 'passages']));
        if (isset($keep['stops'])) {
            $keep['stops'] = array_values(array_map(
                fn ($s) => array_intersect_key((array) $s, array_flip(['name', 'at', 'time'])),
                $keep['stops'],
            ));
        }

        return $keep;
    }
}
