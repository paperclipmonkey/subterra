<?php

namespace App\Notifications;

use App\Channels\ClickSendChannel;
use App\Models\Callout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CalloutOverdueContactNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Callout $callout;

    /**
     * Create a new notification instance.
     */
    public function __construct(Callout $callout)
    {
        $this->callout = $callout;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', ClickSendChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/'); // Link to safe check-in or app home?
        $caveName = $this->callout->cave?->name ?? 'Unknown Location';

        return (new MailMessage())
                    ->subject('URGENT: Callout OVERDUE - '.$caveName)
                    ->greeting('URGENT ACTION REQUIRED')
                    ->line('Your registered callout is now OVERDUE.')
                    ->line('**Cave:** '.$caveName)
                    ->line('**Due Time:** '.$this->callout->callout_time->format('H:i'))
                    ->line('Rescue procedures are being initiated. if you are safe out of the cave, please check in IMMEDIATELY to prevent a false alarm.')
                    ->action('Open App to Check In', $url)
                    ->line('Please reply "OUT SAFE" to the SMS if you cannot access the app.');
    }

    /**
     * Get the ClickSend SMS representation of the notification.
     */
    public function toClickSend(object $notifiable): string
    {
        $caveName = $this->callout->cave?->name ?? 'Unknown';

        return "URGENT: Callout OVERDUE! Cave: {$caveName}. Please reply 'OUT SAFE' immediately or rescue will be launched.";
    }
}
