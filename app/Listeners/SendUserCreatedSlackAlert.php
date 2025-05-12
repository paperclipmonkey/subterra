<?php

namespace App\Listeners;

use App\Events\UserCreated;
use Spatie\SlackAlerts\Facades\SlackAlert;

class SendUserCreatedSlackAlert
{
    public function handle(UserCreated $event)
    {
        $user = $event->user;
        SlackAlert::to('signups')->message("A new user has signed up: {$user->name} <https://subterra.world/admin/users|View User> with email: {$user->email}");
    }
}
