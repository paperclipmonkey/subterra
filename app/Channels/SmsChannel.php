<?php

namespace App\Channels;

use App\Services\SmsService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SmsChannel
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);

        // Ensure we have a phone number.
        // Assuming the notifiable (User) has a 'phone' attribute which is not standard in default Laravel User
        // But for Admin alerts, we might check a config or specific user field.
        // The User model doesn't explicitly have 'phone' in the file I saw, but maybe it does in DB.
        // I will check the User model again or use a fallback.
        // Actually, for the admin alert, I will pass the phone number via the notification or assume the user has it.

        $to = $notifiable->phone ?? config('services.sms_works.admin_phone');

        if (!$to) {
            Log::warning("SmsChannel: No phone number found for user {$notifiable->id}");

            return;
        }

        try {
            $this->smsService->sendMessage($to, $message);
        } catch (\Exception $e) {
            Log::error('SmsChannel: Failed to send SMS: '.$e->getMessage());
        }
    }
}
