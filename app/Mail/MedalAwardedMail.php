<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\User;
use App\Models\Medal;

class MedalAwardedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

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
            ->view('emails.medal_awarded')
            ->with([
                'user' => $this->user,
                'medal' => $this->medal,
            ]);
    }
}
