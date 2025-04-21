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
        // Assuming you have a way to identify admins, e.g., an 'is_admin' flag or a role
        $admins = User::where('is_admin', true)->get(); // Adjust this query as needed

        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new NewUserSignupNotification($event->user));
        }
    }
}
