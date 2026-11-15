<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CaveSystem;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * How long parties take over a route through a cave system, from the trips that
 * kept their route from its 3D survey: the whole trip, and how long into it they
 * reached each stop where they noted it.
 *
 * Only public trips count, and figures are given only once at least
 * MIN_TRIPS trips (or stops) are there to make them, so no one trip, and no one
 * person's pace, can be read off them.
 */
class TripRouteTimesController extends Controller
{
    public const MIN_TRIPS = 3;

    public function show(Request $request, CaveSystem $caveSystem): JsonResponse
    {
        $data = $request->validate([
            'link' => ['required', 'string', 'max:2000', 'starts_with:#from='],
        ]);
        $link = self::routeKey($data['link']);

        $trips = Trip::query()
            ->where('cave_system_id', $caveSystem->id)
            ->where('visibility', 'public')
            ->whereNotNull('route')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get(['id', 'start_time', 'end_time', 'route'])
            ->filter(fn (Trip $t) => self::routeKey((string) ($t->route['link'] ?? '')) === $link)
            ->values();

        $durations = $trips->map(fn (Trip $t) => $t->duration)->filter(fn (int $m) => $m > 0)->values()->all();

        // minutes from the start to each stop, by the stop's place in the route
        $atStop = [];
        foreach ($trips as $trip) {
            foreach (array_values($trip->route['stops'] ?? []) as $k => $stop) {
                if (empty($stop['time'])) {
                    continue;
                }
                $mins = (int) round($trip->start_time->diffInMinutes(\Illuminate\Support\Carbon::parse($stop['time'])));
                if ($mins >= 0) {
                    $atStop[$k][] = $mins;
                }
            }
        }
        $stops = [];
        foreach ($atStop as $k => $mins) {
            if (count($mins) >= self::MIN_TRIPS) {
                $stops[] = ['stop' => $k] + self::spread($mins);
            }
        }

        return response()->json([
            'link' => $link,
            'trips' => count($durations) >= self::MIN_TRIPS ? count($durations) : null,
            'duration' => count($durations) >= self::MIN_TRIPS ? self::spread($durations) : null,
            'stops' => $stops,
        ]);
    }

    /**
     * The route part of a cave-viewer link: from, to, via and another, in that
     * order, so the same route matches however its link was written (a step,
     * the view's settings or another order of the parts make no difference).
     */
    public static function routeKey(string $link): string
    {
        parse_str(str_replace('+', '%2B', ltrim($link, '#')), $q);
        $parts = [];
        foreach (['from', 'to', 'via'] as $k) {
            if (isset($q[$k]) && is_string($q[$k]) && $q[$k] !== '') {
                $parts[] = $k.'='.rawurlencode($q[$k]);
            }
        }
        if (($q['another'] ?? null) === '1') {
            $parts[] = 'another=1';
        }

        return '#'.implode('&', $parts);
    }

    /**
     * @param  list<int>  $mins
     * @return array{median: int, quick: int, slow: int}
     */
    private static function spread(array $mins): array
    {
        sort($mins);
        $at = fn (float $p) => $mins[(int) round($p * (count($mins) - 1))];

        // quick and slow: a quarter of parties take less, a quarter more
        return ['median' => $at(0.5), 'quick' => $at(0.25), 'slow' => $at(0.75)];
    }
}
