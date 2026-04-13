<?php

namespace App\Mail;

use App\Models\Callout;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CalloutStarted extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Callout $callout)
    {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $caveName = $this->callout->cave ? $this->callout->cave->name : 'Unknown Location';
        $time = $this->callout->callout_time->timezone(config('app.display_timezone'))->format('H:i');

        return new Envelope(
            subject: "Safety callout: {$caveName} {$time}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.callouts.started',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
