<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Channels\ClickSendChannel;
use App\Models\Callout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CalloutImminentNotification extends Notification implements ShouldQueue
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
        $url = url('/admin/callout'); // Link to callout admin view

        return (new MailMessage())
                    ->subject('ALERT: Incoming Callout Due Soon - '.($this->callout->cave?->name ?? 'Unknown Location'))
                    ->greeting('Heads Up')
                    ->line('A callout is due in approximately 15 minutes.')
                    ->line('**Cave:** '.($this->callout->cave?->name ?? 'Unknown Location'))
                    ->line('**Due Time:** '.$this->callout->callout_time->timezone(config('app.display_timezone'))->format('H:i'))
                    ->line('Please stand by and ensure you are ready to respond if it becomes overdue.')
                    ->action('View Callout', $url);
    }

    /**
     * Get the ClickSend SMS representation of the notification.
     */
    public function toClickSend(object $notifiable): string
    {
        return 'ALERT: Callout at '.($this->callout->cave?->name ?? 'Unknown Location')." due in 15 mins ({$this->callout->callout_time->timezone(config('app.display_timezone'))->format('H:i')}). Please stand by. Subterra.";
    }
}
