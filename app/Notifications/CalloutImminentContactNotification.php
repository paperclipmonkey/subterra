<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Callout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CalloutImminentContactNotification extends Notification implements ShouldQueue
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
        return ['mail', SmsChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/'); // Link to safe check-in or app home?

        return (new MailMessage)
                    ->subject('ACTION REQUIRED: Callout Due Soon - ' . $this->callout->cave->name)
                    ->greeting('Hello')
                    ->line('Your registered callout is due in approximately 15 minutes.')
                    ->line('**Cave:** ' . $this->callout->cave->name)
                    ->line('**Due Time:** ' . $this->callout->callout_time->format('H:i'))
                    ->line('If you are safe out of the cave, please check in IMMEDIATELY to prevent a rescue callout from being initiated.')
                    ->action('Open App to Check In', $url)
                    ->line('If you are overdue, a rescue will be initiated shortly.');
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(object $notifiable): string
    {
        return "WARNING: Your callout at {$this->callout->cave->name} is due in 15 mins. If safe, check in NOW to avoid rescue initiation. Subterra.";
    }
}
