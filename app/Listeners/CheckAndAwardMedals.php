<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MedalAwarded;
use App\Events\TripParticipantTagged;
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

        $medals = Medal::all();
        foreach ($medals as $medal) {
            if (!$user->medals->contains($medal)) {
                if ($this->passesMedalCriteria($user, $medal)) {
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

    protected function passesMedalCriteria($user, $medal): bool
    {
        switch ($medal->name) {
            case 'First Trip':
                // Awarded for completing at least 1 trip
                return $user->trips()->count() >= 1;

            case 'Explorer':
                // Awarded for visiting 5 different caves
                return $user->trips()
                    ->with('entrance')
                    ->get()
                    ->pluck('entrance_cave_id')
                    ->unique()
                    ->count() >= 5;

            case 'Veteran':
                // Awarded for participating in 20 trips
                return $user->trips()->count() >= 20;

            case 'Night Owl':
                // Awarded for a trip that started after 8pm
                return $user->trips()
                    ->whereTime('start_time', '>=', '20:00:00')
                    ->exists();

            case 'Through Trip':
                // Awarded for a trip where entrance and exit caves are different
                return $user->trips()
                    ->whereColumn('entrance_cave_id', '!=', 'exit_cave_id')
                    ->exists();

            case 'Ham pasta aficionado':
                // Awarded for doing Hunters' Hole and Hunters' Lodge Inn Sink
                $caveNames = $user->trips()
                    ->with('entrance')
                    ->get()
                    ->pluck('entrance.name')
                    ->unique();
                return $caveNames->contains('Hunters\' Hole') && $caveNames->contains('Hunters\' Lodge Inn Sink');

            case 'Hard Caver':
                // Awarded for trips in Yorkshire, Mendip and Wales (by region tag)
                $regions = $user->trips()
                    ->with('entrance.tags')
                    ->get()
                    ->flatMap(function ($trip) {
                        return optional($trip->entrance)?->tags->where('category', 'region')->pluck('tag') ?? collect();
                    })
                    ->unique();
                return $regions->contains('Yorkshire') && $regions->contains('Mendip') && $regions->contains('Wales');

            case 'History Buff':
                // Awarded for doing 5 mines
                $mineTrips = $user->trips()
                    ->with('entrance')
                    ->get()
                    ->filter(function ($trip) {
                        return optional($trip->entrance)?->tags
                            ->where('category', 'type')
                            ->pluck('tag')
                            ->contains('Mine');
                    });
                return $mineTrips->count() >= 5;

            case 'Sport Climber':
                // Awarded for caving in Portland (by region tag)
                return $user->trips()
                    ->with('entrance.tags')
                    ->get()
                    ->flatMap(function ($trip) {
                        return optional($trip->entrance)?->tags->where('category', 'region')->pluck('tag') ?? collect();
                    })
                    ->contains('Portland');

            case 'Cream Tea':
                // Awarded for caving in Devon (by region tag)
                return $user->trips()
                    ->with('entrance.tags')
                    ->get()
                    ->flatMap(function ($trip) {
                        return optional($trip->entrance)?->tags->where('category', 'region')->pluck('tag') ?? collect();
                    })
                    ->contains('Devon');

            case 'Highland Cow':
                // Awarded for caving in Scotland (by region tag)
                return $user->trips()
                    ->with('entrance.tags')
                    ->get()
                    ->flatMap(function ($trip) {
                        return optional($trip->entrance)?->tags->where('category', 'region')->pluck('tag') ?? collect();
                    })
                    ->contains('Scotland');

            case 'Sheep dog':
                // Awarded for going on 5 trips to leader systems
                $leaderTrips = $user->trips()
                    ->with('entrance')
                    ->get()
                    ->filter(function ($trip) {
                        return optional($trip->entrance)->tags->where('tag', 'Leader')->pluck('tag')->isNotEmpty();
                    });
                return $leaderTrips->count() >= 5;

            case 'Mucky Pup':
                // Awarded for going to 3 muddy caves
                $muddyTrips = $user->trips()
                    ->with('entrance.system.tags')
                    ->get()
                    ->filter(function ($trip) {
                        return optional($trip->entrance->system)->tags->where('tag', 'Muddy')->isNotEmpty();
                    });
                return $muddyTrips->count() >= 3;

            case 'Faff Now Cave Later':
                // For 5 trips to SWCC caves
                $swccTrips = $user->trips()
                    ->with('entrance')
                    ->get()
                    ->filter(function ($trip) {
                        $swccCaveNames = ['Ogof Ffynnon Ddu 1', 'Ogof Ffynnon Ddu 2', 'Cwm Dwr',];
                        return in_array(optional($trip->entrance)->name, $swccCaveNames);
                    });
                return $swccTrips->count() >= 5;

            case 'String Dangler':
                // For 10 trips to SRT caves
                $srtTrips = $user->trips()
                    ->with('entrance')
                    ->get()
                    ->filter(function ($trip) {
                        return optional($trip->entrance)->tags->where('tag', 'SRT')->isNotEmpty();
                    });
                return $srtTrips->count() >= 10;
            default:
                return false;
        }
    }
}
