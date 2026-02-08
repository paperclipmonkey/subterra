<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class PlatformNews extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $body;
    public string $unsubscribeUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $subjectLine,
        string $body,
        public User $user
    ) {
        $this->body = $body;
        $this->unsubscribeUrl = URL::signedRoute('newsletter.unsubscribe', ['user' => $user->id]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.platform_news',
            with: [
                'body' => $this->body,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
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
