<?php

namespace App\Listeners;

use App\Events\UserCreated;
use Illuminate\Support\Facades\Log;
use Spatie\SlackAlerts\Facades\SlackAlert;

class SendUserCreatedSlackAlert
{
    public function handle(UserCreated $event)
    {
        if (!$event->user->is_active) {
            return;
        }

        $user = $event->user;
        try {
            SlackAlert::to('signups')->message("A new user has signed up: {$user->name} <https://subterra.world/admin/users|View User> with email: {$user->email}");
        } catch (\Exception $e) {
            Log::error('Failed to send Slack alert: '.$e->getMessage());
        }
    }
}
