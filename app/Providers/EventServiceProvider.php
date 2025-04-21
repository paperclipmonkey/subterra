<?php
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\ClubAccessRequested::class => [
            \App\Listeners\SendClubAccessRequestEmail::class,
        ],
        \App\Events\ClubAccessResponded::class => [
            \App\Listeners\SendClubAccessRespondedEmail::class,
        ],
    ];

    public function boot()
    {
        parent::boot();
    }
}