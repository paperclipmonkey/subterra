<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PlaceholderUserCreated;
use App\Mail\PlaceholderUserNoticeMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendPlaceholderUserNotice implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(PlaceholderUserCreated $event): void
    {
        $user = $event->user;

        // Idempotent: someone who has already been told is not told again, however
        // many times a member re-adds them.
        if ($user->placeholder_notice_sent_at !== null) {
            return;
        }

        if (empty($user->email)) {
            return;
        }

        Mail::to($user->email)->send(new PlaceholderUserNoticeMail($user, $event->creator));

        $user->forceFill(['placeholder_notice_sent_at' => now()])->save();
    }
}
