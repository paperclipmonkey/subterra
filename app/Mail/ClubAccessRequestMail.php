<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Club;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClubAccessRequestMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

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
        return $this->subject('Confirm a member of '.$this->club->name)
            ->markdown('emails.club_access_request')
            ->with([
                'club' => $this->club,
                'user' => $this->user,
                'admin' => $this->admin,
            ]);
    }
}
