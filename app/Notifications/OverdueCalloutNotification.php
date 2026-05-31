<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Channels\ClickSendChannel;
use App\Models\Callout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OverdueCalloutNotification extends Notification implements ShouldQueue
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
        $url = url('/admin/incidents/'.$this->callout->incident->id);

        return (new MailMessage())
                    ->subject('URGENT: Overdue Callout - '.($this->callout->cave?->name ?? 'Unknown Location'))
                    ->greeting('Urgent Attention Required')
                    ->line('A callout has exceeded its expected return time.')
                    ->line('**Cave:** '.($this->callout->cave?->name ?? 'Unknown Location'))
                    ->line('**Callout Time:** '.$this->callout->callout_time->timezone(config('app.display_timezone'))->format('d/m/Y H:i'))
                    ->line('**Overdue By:** '.$this->callout->callout_time->diffForHumans())
                    ->action('View Incident', $url)
                    ->line('This requires immediate attention. Please follow the standard operating procedure.');
    }

    /**
     * Get the ClickSend SMS representation of the notification.
     */
    public function toClickSend(object $notifiable): string
    {
        // SMS length limit is usually 160 chars.
        $caveName = $this->callout->cave?->name ?? 'Unknown Location';

        return "URGENT: Callout Overdue! Cave: {$caveName}. Due: {$this->callout->callout_time->timezone(config('app.display_timezone'))->format('H:i')}. Check Dashboard immediately.";
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'callout_id' => $this->callout->id,
            'message' => 'Callout Overdue: '.($this->callout->cave?->name ?? 'Unknown Location'),
        ];
    }
}
