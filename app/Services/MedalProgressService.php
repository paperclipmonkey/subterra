<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Collection;
use App\Models\Medal;
use App\Models\SuggestedEdit;
use App\Models\User;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Single source of truth for medal criteria. Each medal's requirement is
 * expressed as progress (current/target) so the same logic both awards medals
 * (current >= target) and shows users how close they are to unearned ones.
 */
class MedalProgressService
{
    /**
     * Every medal with the user's earned state and progress toward it.
     *
     * @return SupportCollection<int, array<string, mixed>>
     */
    public function progressForUser(User $user): SupportCollection
    {
        $trips = $user->trips()->with('entrance.tags', 'entrance.system.tags', 'media')->get();
        $collections = Collection::with('caves')->get();
        $earned = $user->medals()->get()->keyBy('id');

        return Medal::orderBy('id')->get()->map(function (Medal $medal) use ($user, $trips, $collections, $earned) {
            $earnedMedal = $earned->get($medal->id);
            $progress = $this->progress($medal, $user, $trips, $collections);

            return [
                'id' => $medal->id,
                'name' => $medal->name,
                'description' => $medal->description,
                'image_url' => $medal->imageUrl(),
                'earned' => $earnedMedal !== null,
                'awarded_at' => $earnedMedal?->pivot->awarded_at,
                'progress' => $progress === null ? null : [
                    'current' => min($progress['current'], $progress['target']),
                    'target' => $progress['target'],
                ],
            ];
        });
    }

    /**
     * Whether the user's activity satisfies the medal's criteria.
     */
    public function passes(Medal $medal, User $user, SupportCollection $trips, SupportCollection $collections): bool
    {
        $progress = $this->progress($medal, $user, $trips, $collections);

        return $progress !== null && $progress['current'] >= $progress['target'];
    }

    /**
     * Progress toward a medal as ['current' => int, 'target' => int]. The medal
     * is earned when current >= target. Null for medals with no known criteria.
     *
     * @return array{current: int, target: int}|null
     */
    public function progress(Medal $medal, User $user, SupportCollection $trips, SupportCollection $collections): ?array
    {
        switch ($medal->name) {
            case 'First Trip':
                // Awarded for completing at least 1 trip
                return ['current' => $trips->count(), 'target' => 1];

            case 'Explorer':
                // Awarded for visiting 5 different caves
                return ['current' => $trips->pluck('entrance_cave_id')->filter()->unique()->count(), 'target' => 5];

            case 'Veteran':
                // Awarded for participating in 20 trips
                return ['current' => $trips->count(), 'target' => 20];

            case 'Night Owl':
                // Awarded for a trip that started after 8pm
                return $this->boolProgress($trips->contains(function ($trip) {
                    return $trip->start_time && $trip->start_time->hour >= 20;
                }));

            case 'Through Trip':
                // Awarded for a trip where entrance and exit caves are different
                return $this->boolProgress($trips->contains(function ($trip) {
                    return $trip->entrance_cave_id && $trip->exit_cave_id && $trip->entrance_cave_id !== $trip->exit_cave_id;
                }));

            case 'Ham pasta aficionado':
                // Awarded for doing Hunters' Hole and Hunters' Lodge Inn Sink
                $caveNames = $trips->pluck('entrance.name')->unique();

                return [
                    'current' => collect(['Hunters\' Hole', 'Hunters\' Lodge Inn Sink'])
                        ->filter(fn ($name) => $caveNames->contains($name))->count(),
                    'target' => 2,
                ];

            case 'Hard Caver':
                // Awarded for trips in the Northern region, Mendip and Wales (by region tag)
                $regions = $this->regionTags($trips);

                return [
                    'current' => collect(['Northern', 'Mendip', 'Wales'])
                        ->filter(fn ($region) => $regions->contains($region))->count(),
                    'target' => 3,
                ];

            case 'History Buff':
                // Awarded for doing 5 mines
                $mineTrips = $trips->filter(function ($trip) {
                    return optional($trip->entrance)?->tags
                        ->where('category', 'type')
                        ->pluck('tag')
                        ->contains('Mine');
                });

                return ['current' => $mineTrips->count(), 'target' => 5];

            case 'Sport Climber':
                // Awarded for caving in Portland (by region tag)
                return $this->boolProgress($this->regionTags($trips)->contains('Portland'));

            case 'Cream Tea':
                // Awarded for caving in Devon (by region tag)
                return $this->boolProgress($this->regionTags($trips)->contains('Devon'));

            case 'Highland Cow':
                // Awarded for caving in Scotland (by region tag)
                return $this->boolProgress($this->regionTags($trips)->contains('Scotland'));

            case 'Sheep dog':
                // Awarded for going on 5 trips to leader systems
                $leaderTrips = $trips->filter(function ($trip) {
                    return optional($trip->entrance)->tags->where('tag', 'Warden')->isNotEmpty();
                });

                return ['current' => $leaderTrips->count(), 'target' => 5];

            case 'Mucky Pup':
                // Awarded for going to 3 muddy caves
                $muddyTrips = $trips->filter(function ($trip) {
                    return optional($trip->entrance->system)->tags->where('tag', 'Muddy')->isNotEmpty();
                });

                return ['current' => $muddyTrips->count(), 'target' => 3];

            case 'Faff Now Cave Later':
                // For 5 trips to SWCC caves
                $swccTrips = $trips->filter(function ($trip) {
                    $swccCaveNames = ['Ogof Ffynnon Ddu 1', 'Ogof Ffynnon Ddu 2', 'Cwm Dwr'];

                    return in_array(optional($trip->entrance)->name, $swccCaveNames);
                });

                return ['current' => $swccTrips->count(), 'target' => 5];

            case 'String Dangler':
                // For 10 trips to SRT caves
                $srtTrips = $trips->filter(function ($trip) {
                    return optional($trip->entrance)->tags->where('tag', 'SRT')->isNotEmpty();
                });

                return ['current' => $srtTrips->count(), 'target' => 10];

            case 'Copper Miner':
                // Awarded for caving at the Great Orme. Some registry caves
                // carry the location only in their name rather than a region
                // tag (e.g. "Penmorfa, Llandudno, Wales"), so match on names
                // too — the Great Orme is Llandudno's headland.
                return $this->boolProgress(
                    $this->regionTags($trips)->contains('Great Orme')
                    || $trips->contains(function ($trip) {
                        $names = strtolower(implode(' ', array_filter([
                            $trip->entrance?->name,
                            $trip->entrance?->system?->name,
                        ])));

                        return str_contains($names, 'great orme') || str_contains($names, 'llandudno');
                    })
                );

            case 'Dragon\'s Lair':
                // Awarded for 5 trips to Welsh caves
                $welshTrips = $trips->filter(function ($trip) {
                    return optional($trip->entrance)?->tags->where('category', 'region')->where('tag', 'Wales')->isNotEmpty();
                });

                return ['current' => $welshTrips->count(), 'target' => 5];

            case 'Completionist':
                // Awarded for completing any cave collection; progress tracks
                // the collection the user is closest to finishing.
                $visitedCaveIds = $trips->pluck('entrance_cave_id')->filter()->unique();

                $best = $collections
                    ->filter(fn ($collection) => $collection->caves->isNotEmpty())
                    ->map(function ($collection) use ($visitedCaveIds) {
                        $caveIds = $collection->caves->pluck('id');

                        return [
                            'current' => $caveIds->intersect($visitedCaveIds)->count(),
                            'target' => $caveIds->count(),
                        ];
                    })
                    ->sortByDesc(fn ($progress) => $progress['current'] / $progress['target'])
                    ->first();

                return $best ?? ['current' => 0, 'target' => 1];

            case 'Slate Heart':
                // Awarded for caving in North Wales
                return $this->boolProgress($this->regionTags($trips)->contains('North Wales'));

            case 'Gower Power':
                // Awarded for caving in Gower
                return $this->boolProgress($this->regionTags($trips)->contains('Gower'));

            case 'Free Miner':
                // Awarded for caving in the Forest of Dean
                return $this->boolProgress($this->regionTags($trips)->contains('Forest of Dean'));

            case 'Archivist':
                // Awarded for submitting a suggested edit to the cave data
                return [
                    'current' => SuggestedEdit::where('user_id', $user->id)->count(),
                    'target' => 1,
                ];

            case 'Cave Photographer':
                // Awarded for adding photos to 3 trips
                return [
                    'current' => $trips->filter(fn ($trip) => $trip->media->isNotEmpty())->count(),
                    'target' => 3,
                ];

            case 'Wordsmith':
                // Awarded for 5 detailed trip reports (a proper write-up, not one line)
                $detailedTrips = $trips->filter(function ($trip) {
                    return mb_strlen(trim((string) $trip->description)) >= 280;
                });

                return ['current' => $detailedTrips->count(), 'target' => 5];

            default:
                return null;
        }
    }

    /**
     * @return array{current: int, target: int}
     */
    private function boolProgress(bool $achieved): array
    {
        return ['current' => $achieved ? 1 : 0, 'target' => 1];
    }

    /**
     * Unique region tags across the entrances of all the user's trips.
     */
    private function regionTags(SupportCollection $trips): SupportCollection
    {
        return $trips->flatMap(function ($trip) {
            return optional($trip->entrance)?->tags->where('category', 'region')->pluck('tag') ?? collect();
        })->unique();
    }
}
