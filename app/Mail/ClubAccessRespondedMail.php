<?php

namespace App\Mail;

use App\Models\Club;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClubAccessRespondedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public $club;
    public $user;
    public $status;
    public $reason;

    public function __construct(Club $club, User $user, string $status, ?string $reason = null)
    {
        $this->club = $club;
        $this->user = $user;
        $this->status = $status;
        $this->reason = $reason;
    }

    public function build()
    {
        $subject = $this->status === 'approved' ? 'Your Club Access Request Was Approved' : 'Your Club Access Request Was Rejected';

        return $this->subject($subject)
            ->view('emails.club_access_responded')
            ->with([
                'club' => $this->club,
                'user' => $this->user,
                'status' => $this->status,
                'reason' => $this->reason,
            ]);
    }
}
