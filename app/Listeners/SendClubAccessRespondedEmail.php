<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ClubAccessResponded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendClubAccessRespondedEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(ClubAccessResponded $event)
    {
        $club = $event->club;
        $user = $event->user;
        $status = $event->status;
        $reason = $event->reason;
        Mail::to($user->email)->send(new \App\Mail\ClubAccessRespondedMail($club, $user, $status, $reason));
    }
}
