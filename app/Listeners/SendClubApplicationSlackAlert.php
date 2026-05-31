<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ClubAccessRequested;
use Illuminate\Support\Facades\Log;
use Spatie\SlackAlerts\Facades\SlackAlert;

class SendClubApplicationSlackAlert
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ClubAccessRequested $event): void
    {
        try {
            $club = $event->club;
            $user = $event->user;
            $url = url('/club/'.$club->slug).'?editClub=1&tab=pending';

            $msg = "🆕 *NEW CLUB APPLICATION*\nUser: *{$user->name}* ({$user->email})\nClub: *{$club->name}*\n\n<$url|Review Request>";

            SlackAlert::to('approvals')->message($msg);
        } catch (\Exception $e) {
            Log::error('Failed to send Club Application Slack alert: '.$e->getMessage());
        }
    }
}
