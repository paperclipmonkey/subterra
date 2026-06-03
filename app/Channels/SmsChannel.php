<?php

declare(strict_types=1);

namespace App\Channels;

use App\Contracts\SmsSender;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Provider-agnostic SMS notification channel. Resolves the bound SmsSender (Twilio) so the
 * underlying provider can be swapped without changing notifications.
 *
 * A failed send is surfaced (logged + thrown) rather than swallowed: the live callers
 * (CheckOverdueCallouts::safeNotify) isolate each send in a try/catch, so throwing here is
 * logged per-recipient without aborting the rest.
 */
class SmsChannel
{
    public function __construct(private readonly SmsSender $smsSender)
    {
    }

    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            Log::warning('Notification does not have a toSms method.', ['notification' => $notification::class]);

            return;
        }

        $to = null;
        if (method_exists($notifiable, 'routeNotificationForSms')) {
            $to = $notifiable->routeNotificationForSms($notification);
        } elseif (isset($notifiable->phone)) {
            $to = $notifiable->phone;
        }

        if (empty($to)) {
            Log::warning('SmsChannel: no phone number for notifiable.', ['id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null]);

            return;
        }

        $message = $notification->toSms($notifiable);

        $sent = $this->smsSender->send($to, $message);

        if (! $sent) {
            $id = method_exists($notifiable, 'getKey') ? $notifiable->getKey() : 'unknown';
            Log::error('SmsChannel: SMS delivery failed for notifiable.', ['id' => $id]);

            throw new \RuntimeException('SMS delivery failed for notifiable '.$id);
        }
    }
}
