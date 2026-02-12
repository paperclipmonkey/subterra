<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Mail\NewUserSignupNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendNewUserSignupEmailToAdmins implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     *
     * @param  NewUserSignedUp  $event
     * @return void
     */
    public function handle(UserCreated $event): void
    {
        if (!$event->user->is_active) {
            return;
        }

        $admins = User::whereHas('roles', function($query) {
            $query->where('slug', 'platform_admin');
        })->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new NewUserSignupNotification($event->user));
        }
    }
}
