<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewUserSignupNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $newUser;

    /**
     * Create a new message instance.
     *
     * @param User $newUser The user who signed up.
     * @return void
     */
    public function __construct(User $newUser)
    {
        $this->newUser = $newUser;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New User Signup Requires Approval',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content(): Content
    {
        // Ensure you have a corresponding Blade view at resources/views/emails/new_user_signup.blade.php
        return new Content(
            view: 'emails.new_user_signup',
            with: [
                'userName' => $this->newUser->name,
                'userEmail' => $this->newUser->email,
                // Generate a URL to an admin page where the user can be approved
                // The 'user_without_scopes' binding might be useful here if approval bypasses scopes
                'approvalUrl' => config('app.url') . '/admin/users?search=' . $this->newUser->email, // Adjust route name as needed
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
