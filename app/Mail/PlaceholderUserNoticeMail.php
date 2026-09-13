<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * The UK GDPR Article 14 notice: you are in our records, here is who put you
 * there, here is what we hold, and here is how to object.
 */
class PlaceholderUserNoticeMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public User $creator,
    ) {
    }

    public function build(): self
    {
        // Signed rather than authenticated: the recipient has never logged in and
        // may not want to. Long-lived on purpose — an objection route that has
        // quietly expired is not a route at all.
        $objectUrl = URL::temporarySignedRoute(
            'data-objection',
            now()->addDays(90),
            ['user' => $this->user->id]
        );

        return $this->subject('Someone added you to Subterra')
            ->markdown('emails.placeholder_user_notice')
            ->with([
                'user' => $this->user,
                'creator' => $this->creator,
                'objectUrl' => $objectUrl,
                'privacyUrl' => url('/pages/privacy-policy'),
            ]);
    }
}
