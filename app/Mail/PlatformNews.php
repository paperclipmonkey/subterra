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
    use Queueable;
    use SerializesModels;

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
        $this->body = $this->replacePlaceholders($body, $user);
        $this->subjectLine = $this->replacePlaceholders($subjectLine, $user);
        $this->unsubscribeUrl = URL::signedRoute('newsletter.unsubscribe', ['user' => $user->id]);
    }

    protected function replacePlaceholders(string $content, User $user): string
    {
        $firstName = explode(' ', trim($user->name))[0];

        $placeholders = [
            '{{ name }}' => $user->name,
            '{{ fullname }}' => $user->name,
            '{{ firstname }}' => $firstName,
            '{{ id }}' => $user->id,
            '{{ email }}' => $user->email,
            '{{ club }}' => $user->clubs->first()?->name ?? 'Subterra',
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
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
