<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MedalAwarded;
use App\Events\TripParticipantTagged;
use App\Events\UserContributed;
use App\Models\Collection;
use App\Models\Medal;
use App\Services\MedalProgressService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Queue\InteractsWithQueue;

class CheckAndAwardMedals implements ShouldQueue
{
    use InteractsWithQueue;

    private MedalProgressService $medalProgress;

    public function __construct(?MedalProgressService $medalProgress = null)
    {
        $this->medalProgress = $medalProgress ?? new MedalProgressService();
    }

    public function handle(TripParticipantTagged|UserContributed $event): void
    {
        $user = $event->user;

        // Preload all trips and collections with their relations once to avoid repeated queries per medal
        $trips = $user->trips()->with('entrance.tags', 'entrance.system.tags', 'media')->get();
        $collections = Collection::with('caves')->get();

        $medals = Medal::all();
        foreach ($medals as $medal) {
            if ($user->medals->contains($medal)) {
                continue;
            }

            if (!$this->medalProgress->passes($medal, $user, $trips, $collections)) {
                continue;
            }

            try {
                $user->medals()->attach($medal->id, ['awarded_at' => Carbon::now()]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent worker awarded this medal between our "not yet
                // earned" check and the attach — it owns the MedalAwarded
                // event, so skip it here and carry on with the other medals.
                continue;
            }

            // Fire immediately per medal so a failure later in the loop can't
            // swallow notifications for medals already attached.
            event(new MedalAwarded($user, $medal));
        }
    }
}
