<?php

namespace App\Channels;

use App\Services\ClickSendService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ClickSendChannel
{
    protected ClickSendService $clickSendService;

    public function __construct(ClickSendService $clickSendService)
    {
        $this->clickSendService = $clickSendService;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toClickSend')) {
            Log::warning('Notification does not have toClickSend method.');
            return;
        }

        $message = $notification->toClickSend($notifiable);
        $to = null;

        if (method_exists($notifiable, 'routeNotificationForClickSend')) {
            $to = $notifiable->routeNotificationForClickSend($notification);
        } elseif (isset($notifiable->phone)) {
            // Fallback to 'phone' attribute if available
            $to = $notifiable->phone;
        }

        if (empty($to)) {
             Log::warning('ClickSend Channel: No phone number found for notifiable.', ['id' => $notifiable->getKey()]);
             return;
        }

        $this->clickSendService->sendSms($to, $message);
    }
}
