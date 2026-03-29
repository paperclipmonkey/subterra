<?php

namespace App\Mail;

use App\Models\Medal;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MedalAwardedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public $user;
    public $medal;

    public function __construct(User $user, Medal $medal)
    {
        $this->user = $user;
        $this->medal = $medal;
    }

    public function build()
    {
        return $this->subject('You have been awarded a new medal!')
            ->markdown('emails.medal_awarded')
            ->with([
                'user' => $this->user,
                'medal' => $this->medal,
            ]);
    }
}
