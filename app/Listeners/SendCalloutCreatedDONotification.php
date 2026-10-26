<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CalloutCreated;
use App\Mail\CalloutOpenedDONotification;
use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCalloutCreatedDONotification
{
    public function handle(CalloutCreated $event): void
    {
        $shift = OnCallShift::with('user')
            ->where('notify_do', true)
            ->covering(now())
            ->first();

        /** @var User|null $officer */
        $officer = $shift?->user;

        if (!$officer?->email) {
            return;
        }

        try {
            Mail::to($officer->email)->send(new CalloutOpenedDONotification($event->callout));
        } catch (\Exception $e) {
            Log::error('Failed to send callout DO notification: '.$e->getMessage());
        }
    }
}
