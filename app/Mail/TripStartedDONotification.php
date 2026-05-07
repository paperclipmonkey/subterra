<?php

namespace App\Mail;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TripStartedDONotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Trip $trip,
        public User $creator
    ) {
    }

    public function envelope(): Envelope
    {
        $caveName = $this->trip->entrance?->name ?? 'Unknown Location';

        return new Envelope(
            subject: "Trip started: {$this->trip->name} at {$caveName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.trips.do_notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
