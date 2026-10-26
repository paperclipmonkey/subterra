<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingMessageMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $message,
        public readonly User $sender,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->sender->email, $this->sender->name)],
            subject: 'Message regarding your booking: '.$this->booking->permit->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.booking_message',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
