<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IncidentEscalatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Incident $incident;

    /**
     * Create a new notification instance.
     */
    public function __construct(Incident $incident)
    {
        $this->incident = $incident;
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
        $url = url('/admin/incidents/'.$this->incident->id);

        return (new MailMessage())
                    ->subject('URGENT: Unclaimed Incident - '.($this->incident->callout->cave->name ?? 'Unknown'))
                    ->greeting('Urgent Assistance Required')
                    ->line('An active incident has been open for 15 minutes without a Controller assigned.')
                    ->line('**Incident ID:** #'.$this->incident->id)
                    ->line('**Location:** '.($this->incident->callout->cave->name ?? 'Unknown'))
                    ->action('Take Control', $url)
                    ->line('All Duty Officers are being notified. Please log in immediately.');
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(object $notifiable): string
    {
        return "URGENT: Incident #{$this->incident->id} waiting for Controller >15m! Please log in to Subterra immediately.";
    }
}
