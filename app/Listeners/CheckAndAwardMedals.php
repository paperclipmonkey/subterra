<?php

namespace App\Listeners;

use App\Events\TripParticipantTagged;
use App\Models\Medal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use App\Mail\MedalAwardedMail;
use Carbon\Carbon;

class CheckAndAwardMedals implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TripParticipantTagged $event)
    {
        $user = $event->user;
        $awardedMedals = [];

        // Example: Medal logic (replace with your own rules)
        $medals = Medal::all();
        foreach ($medals as $medal) {
            if (!$user->medals->contains($medal)) {
                // TODO: Replace with real logic for each medal
                if ($this->passesMedalCriteria($user, $medal)) {
                    $user->medals()->attach($medal->id, ['awarded_at' => Carbon::now()]);
                    $awardedMedals[] = $medal;
                }
            }
        }

        // Email user for each new medal
        foreach ($awardedMedals as $medal) {
            Mail::to($user->email)->send(new MedalAwardedMail($user, $medal));
        }
    }

    protected function passesMedalCriteria($user, $medal)
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

            default:
                return false;
        }
    }
}
