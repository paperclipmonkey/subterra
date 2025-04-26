<?php

namespace App\Listeners;

use App\Events\ClubAccessResponded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class ApproveClubUserAutomatically implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(ClubAccessResponded $event)
    {
        $user = $event->user;
        if (!$user->is_approved) {
            $user->is_approved = true;
            $user->save();
        }
    }
}