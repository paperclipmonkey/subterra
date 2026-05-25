<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MedalAwarded;
use App\Events\TripParticipantTagged;
use App\Models\Collection;
use App\Models\Medal;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CheckAndAwardMedals implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TripParticipantTagged $event): void
    {
        $user = $event->user;
        $awardedMedals = [];

        // Preload all trips and collections with their relations once to avoid repeated queries per medal
        $trips = $user->trips()->with('entrance.tags', 'entrance.system.tags')->get();
        $collections = Collection::with('caves')->get();

        $medals = Medal::all();
        foreach ($medals as $medal) {
            if (!$user->medals->contains($medal)) {
                if ($this->passesMedalCriteria($user, $medal, $trips, $collections)) {
                    $user->medals()->attach($medal->id, ['awarded_at' => Carbon::now()]);
                    $awardedMedals[] = $medal;
                }
            }
        }

        // Fire an event for each awarded medal
        foreach ($awardedMedals as $medal) {
            event(new MedalAwarded($user, $medal));
        }
    }

    protected function passesMedalCriteria($user, $medal, $trips, $collections): bool
    {
        switch ($medal->name) {
            case 'First Trip':
                // Awarded for completing at least 1 trip
                return $trips->count() >= 1;

            case 'Explorer':
                // Awarded for visiting 5 different caves
                return $trips->pluck('entrance_cave_id')->unique()->count() >= 5;

            case 'Veteran':
                // Awarded for participating in 20 trips
                return $trips->count() >= 20;

            case 'Night Owl':
                // Awarded for a trip that started after 8pm
                return $trips->contains(function ($trip) {
                    return $trip->start_time && $trip->start_time->hour >= 20;
                });

            case 'Through Trip':
                // Awarded for a trip where entrance and exit caves are different
                return $trips->contains(function ($trip) {
                    return $trip->entrance_cave_id && $trip->exit_cave_id && $trip->entrance_cave_id !== $trip->exit_cave_id;
                });

            case 'Ham pasta aficionado':
                // Awarded for doing Hunters' Hole and Hunters' Lodge Inn Sink
                $caveNames = $trips->pluck('entrance.name')->unique();

                return $caveNames->contains('Hunters\' Hole') && $caveNames->contains('Hunters\' Lodge Inn Sink');

            case 'Hard Caver':
                // Awarded for trips in Yorkshire, Mendip and Wales (by region tag)
                $regions = $trips->flatMap(function ($trip) {
                    return optional($trip->entrance)?->tags->where('category', 'region')->pluck('tag') ?? collect();
                })->unique();

                return $regions->contains('Yorkshire') && $regions->contains('Mendip') && $regions->contains('Wales');

            case 'History Buff':
                // Awarded for doing 5 mines
                $mineTrips = $trips->filter(function ($trip) {
                    return optional($trip->entrance)?->tags
                        ->where('category', 'type')
                        ->pluck('tag')
                        ->contains('Mine');
                });

                return $mineTrips->count() >= 5;

            case 'Sport Climber':
                // Awarded for caving in Portland (by region tag)
                return $trips->flatMap(function ($trip) {
                    return optional($trip->entrance)?->tags->where('category', 'region')->pluck('tag') ?? collect();
                })->contains('Portland');

            case 'Cream Tea':
                // Awarded for caving in Devon (by region tag)
                return $trips->flatMap(function ($trip) {
                    return optional($trip->entrance)?->tags->where('category', 'region')->pluck('tag') ?? collect();
                })->contains('Devon');

            case 'Highland Cow':
                // Awarded for caving in Scotland (by region tag)
                return $trips->flatMap(function ($trip) {
                    return optional($trip->entrance)?->tags->where('category', 'region')->pluck('tag') ?? collect();
                })->contains('Scotland');

            case 'Sheep dog':
                // Awarded for going on 5 trips to leader systems
                $leaderTrips = $trips->filter(function ($trip) {
                    return optional($trip->entrance)->tags->where('tag', 'Warden')->isNotEmpty();
                });

                return $leaderTrips->count() >= 5;

            case 'Mucky Pup':
                // Awarded for going to 3 muddy caves
                $muddyTrips = $trips->filter(function ($trip) {
                    return optional($trip->entrance->system)->tags->where('tag', 'Muddy')->isNotEmpty();
                });

                return $muddyTrips->count() >= 3;

            case 'Faff Now Cave Later':
                // For 5 trips to SWCC caves
                $swccTrips = $trips->filter(function ($trip) {
                    $swccCaveNames = ['Ogof Ffynnon Ddu 1', 'Ogof Ffynnon Ddu 2', 'Cwm Dwr'];

                    return in_array(optional($trip->entrance)->name, $swccCaveNames);
                });

                return $swccTrips->count() >= 5;

            case 'String Dangler':
                // For 10 trips to SRT caves
                $srtTrips = $trips->filter(function ($trip) {
                    return optional($trip->entrance)->tags->where('tag', 'SRT')->isNotEmpty();
                });

                return $srtTrips->count() >= 10;

            case 'Copper Miner':
                // Awarded for caving at the Great Orme
                return $trips->flatMap(function ($trip) {
                    return optional($trip->entrance)?->tags->where('category', 'region')->pluck('tag') ?? collect();
                })->contains('Great Orme');

            case 'Dragon\'s Lair':
                // Awarded for 5 trips to Welsh caves
                $welshTrips = $trips->filter(function ($trip) {
                    return optional($trip->entrance)?->tags->where('category', 'region')->where('tag', 'Wales')->isNotEmpty();
                });

                return $welshTrips->count() >= 5;

            case 'Completionist':
                // Awarded for completing any cave collection
                $visitedCaveIds = $trips->pluck('entrance_cave_id')->unique();

                return $collections->contains(function ($collection) use ($visitedCaveIds) {
                    $collectionCaveIds = $collection->caves->pluck('id');
                    return $collectionCaveIds->isNotEmpty() && $collectionCaveIds->diff($visitedCaveIds)->isEmpty();
                });

            case 'Slate Heart':
                // Awarded for caving in North Wales
                return $trips->flatMap(function ($trip) {
                    return optional($trip->entrance)?->tags->where('category', 'region')->pluck('tag') ?? collect();
                })->contains('North Wales');

            case 'Gower Power':
                // Awarded for caving in Gower
                return $trips->flatMap(function ($trip) {
                    return optional($trip->entrance)?->tags->where('category', 'region')->pluck('tag') ?? collect();
                })->contains('Gower');

            case 'Free Miner':
                // Awarded for caving in the Forest of Dean
                return $trips->flatMap(function ($trip) {
                    return optional($trip->entrance)?->tags->where('category', 'region')->pluck('tag') ?? collect();
                })->contains('Forest of Dean');

            default:
                return false;
        }
    }
}
