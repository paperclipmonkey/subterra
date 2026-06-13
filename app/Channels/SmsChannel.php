<?php

declare(strict_types=1);

namespace App\Channels;

use App\Contracts\SmsSender;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        if (!method_exists($notification, 'toSms')) {
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

        $sent = $this->smsSender->send($to, $message, $this->context($notifiable, $notification));

        if (!$sent) {
            $id = method_exists($notifiable, 'getKey') ? $notifiable->getKey() : 'unknown';
            Log::error('SmsChannel: SMS delivery failed for notifiable.', ['id' => $id]);

            throw new \RuntimeException('SMS delivery failed for notifiable '.$id);
        }
    }

    /**
     * Build delivery-tracking context from the recipient and the notification. A notification
     * may expose `smsContext($notifiable): array` to override; otherwise we introspect a
     * `callout` / `incident` property and label the message by the notification class.
     *
     * @return array<string, mixed>
     */
    private function context($notifiable, Notification $notification): array
    {
        $context = [
            'recipient_name' => $notifiable->name ?? null,
            'user_id' => $notifiable instanceof User ? $notifiable->getKey() : ($notifiable->user_id ?? null),
            'label' => Str::snake(class_basename($notification)),
        ];

        if (method_exists($notification, 'smsContext')) {
            return array_merge($context, $notification->smsContext($notifiable));
        }

        $callout = $notification->callout ?? null;
        $incident = $notification->incident ?? null;

        $context['callout_id'] = $callout->id ?? $incident->callout_id ?? null;
        $context['incident_id'] = $incident->id ?? null;

        return $context;
    }
}
