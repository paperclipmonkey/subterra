<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MedalAwarded;
use App\Mail\MedalAwardedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendMedalAwardedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(MedalAwarded $event): void
    {
        Mail::to($event->user->email)->send(new MedalAwardedMail($event->user, $event->medal));
    }
}