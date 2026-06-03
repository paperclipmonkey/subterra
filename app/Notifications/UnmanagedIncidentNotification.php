<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UnmanagedIncidentNotification extends Notification implements ShouldQueue
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
                    ->subject('CRITICAL: Unmanaged Incident - Overdue')
                    ->greeting('Immediate Action Required')
                    ->line('An incident has been open for 15 minutes without a managed response.')
                    ->line('**Incident ID:** '.$this->incident->id)
                    ->line('**Cave:** '.($this->incident->callout->cave?->name ?? 'Unknown'))
                    ->action('Take Control', $url)
                    ->line('Please assign yourself as controller immediately.');
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(object $notifiable): string
    {
        return "CRITICAL: Unmanaged Incident! ID: {$this->incident->id}. Cave: {$this->incident->callout->cave?->name}. 15 mins no response. Log in NOW.";
    }
}
