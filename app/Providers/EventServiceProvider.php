<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\ClubAccessRequested::class => [
            \App\Listeners\SendClubAccessRequestEmail::class,
            \App\Listeners\SendClubApplicationSlackAlert::class,
        ],
        \App\Events\ClubAccessResponded::class => [
            \App\Listeners\SendClubAccessRespondedEmail::class,
            \App\Listeners\SendClubApprovalSlackAlert::class,
        ],
        \App\Events\TripParticipantTagged::class => [
            \App\Listeners\SendTripTaggedEmail::class,
            \App\Listeners\CheckAndAwardMedals::class,
        ],
        \App\Events\CalloutCreated::class => [
            \App\Listeners\SendCalloutCreatedSlackAlert::class,
        ],
        \App\Events\CalloutCancelled::class => [
            \App\Listeners\SendCalloutCancelledSlackAlert::class,
        ],
        \App\Events\TripCreated::class => [
            \App\Listeners\SendTripCreatedSlackAlert::class,
            \App\Listeners\SendTripStartedDONotification::class,
        ],
    ];

    public function boot()
    {
        parent::boot();
    }
}
