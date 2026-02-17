<?php

namespace App\Listeners;

use App\Events\ClubAccessResponded;
use Illuminate\Support\Facades\Log;
use Spatie\SlackAlerts\Facades\SlackAlert;

class SendClubApprovalSlackAlert
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
    public function handle(ClubAccessResponded $event): void
    {
        if ($event->status !== 'approved') {
            return;
        }

        try {
            $club = $event->club;
            $user = $event->user;

            $msg = "✅ *CLUB MEMBERSHIP APPROVED*\nUser: *{$user->name}* ({$user->email})\nClub: *{$club->name}*";

            SlackAlert::to('approvals')->message($msg);
        } catch (\Exception $e) {
            Log::error('Failed to send Club Approval Slack alert: '.$e->getMessage());
        }
    }
}
