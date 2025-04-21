<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Club;
use App\Models\User;

class ClubAccessRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $club;
    public $user;
    public $admin;

    public function __construct(Club $club, User $user, User $admin)
    {
        $this->club = $club;
        $this->user = $user;
        $this->admin = $admin;
    }

    public function build()
    {
        return $this->subject('New Club Access Request')
            ->view('emails.club_access_request')
            ->with([
                'club' => $this->club,
                'user' => $this->user,
                'admin' => $this->admin,
            ]);
    }
}
