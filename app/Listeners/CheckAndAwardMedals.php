<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MedalAwarded;
use App\Events\TripParticipantTagged;
use App\Models\Collection;
use App\Models\Medal;
use App\Services\MedalProgressService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CheckAndAwardMedals implements ShouldQueue
{
    use InteractsWithQueue;

    private MedalProgressService $medalProgress;

    public function __construct(?MedalProgressService $medalProgress = null)
    {
        $this->medalProgress = $medalProgress ?? new MedalProgressService();
    }

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
                if ($this->medalProgress->passes($medal, $user, $trips, $collections)) {
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
}
