<?php

namespace App\Listeners;

use App\Events\ClubAccessRequested;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

class SendClubAccessRequestEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(ClubAccessRequested $event)
    {
        $club = $event->club;
        $user = $event->user;
        $admins = $club->approvedUsers()->wherePivot('is_admin', true)->get();
        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new \App\Mail\ClubAccessRequestMail($club, $user, $admin));
        }
    }
}