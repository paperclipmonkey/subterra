<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A route through a cave's 3D survey, as cave-viewer reports it to the page
 * it's framed in: its link ('#from=…&to=…&via=…'), its length and climb, its
 * stops in turn (each with how far along it is and, on a trip, when the party
 * got there, where they noted it) and the passages it goes through. Kept on a
 * trip (the way the party went) and on a route guide (the way it describes).
 */
final class ViewerRoute
{
    /**
     * Forms with photos are multipart, where the route comes as a JSON string:
     * the decoded route, null for '' (taken off), or the input as it was.
     */
    public static function decode(mixed $input): mixed
    {
        if (!is_string($input)) {
            return $input;
        }
        if ($input === '') {
            return null;
        }
        $decoded = json_decode($input, true);

        return is_array($decoded) ? $decoded : $input;
    }

    /** @return array<string, mixed> */
    public static function rules(string $key, bool $times = false): array
    {
        return [
            $key => ['nullable', 'array'],
            "{$key}.link" => ["required_with:{$key}", 'string', 'max:2000', 'starts_with:#from='],
            "{$key}.length" => ['nullable', 'numeric', 'min:0'],
            "{$key}.up" => ['nullable', 'numeric', 'min:0'],
            "{$key}.down" => ['nullable', 'numeric', 'min:0'],
            "{$key}.another" => ['nullable', 'boolean'],
            "{$key}.stops" => ['nullable', 'array', 'max:50'],
            "{$key}.stops.*.name" => ['required', 'string', 'max:255'],
            "{$key}.stops.*.at" => ['nullable', 'numeric', 'min:0'],
            ...($times ? ["{$key}.stops.*.time" => ['nullable', 'date']] : []),
            "{$key}.passages" => ['nullable', 'array', 'max:200'],
            "{$key}.passages.*" => ['string', 'max:255'],
        ];
    }

    /**
     * Only the fields above are kept: the route is stored as given, so nothing
     * else rides along in it.
     *
     * @param  array<string, mixed>|null  $route
     * @return array<string, mixed>|null
     */
    public static function clean(?array $route, bool $times = false): ?array
    {
        if ($route === null) {
            return null;
        }
        $keep = array_intersect_key($route, array_flip(['link', 'length', 'up', 'down', 'another', 'stops', 'passages']));
        if (isset($keep['stops'])) {
            $stopKeys = array_flip($times ? ['name', 'at', 'time'] : ['name', 'at']);
            $keep['stops'] = array_values(array_map(
                fn ($s) => array_intersect_key((array) $s, $stopKeys),
                $keep['stops'],
            ));
        }

        return $keep;
    }
}
