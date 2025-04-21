<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Club;
use App\Models\User;

class ClubAccessRespondedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $club;
    public $user;
    public $status;

    public function __construct(Club $club, User $user, string $status)
    {
        $this->club = $club;
        $this->user = $user;
        $this->status = $status;
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
            ]);
    }
}
