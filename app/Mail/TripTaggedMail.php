<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TripTaggedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public $trip;
    public $user;
    public $creator;

    public function __construct(Trip $trip, User $user, User $creator)
    {
        $this->trip = $trip;
        $this->user = $user;
        $this->creator = $creator;
    }

    public function build()
    {
        // Placeholder accounts (created when a member tags someone not yet on
        // Subterra) stay inactive until their first sign-in. tos_agreed_at is no
        // signal: magic-link sign-ups can leave it null, so established members
        // were being sent the welcome-to-Subterra copy.
        $isNewUser = !$this->user->is_active;

        // Trip descriptions are stored as HTML; the panel is Markdown, so tags
        // would otherwise show up literally (e.g. "<br />").
        $description = trim(preg_replace('/\s+/', ' ', strip_tags(
            preg_replace('/<br\s*\/?>|<\/p>/i', ' ', (string) $this->trip->description)
        )));

        return $this->subject('You have been tagged in a trip')
            ->markdown('emails.trip_tagged')
            ->with([
                'trip' => $this->trip,
                'user' => $this->user,
                'creator' => $this->creator,
                'isNewUser' => $isNewUser,
                'description' => html_entity_decode($description, ENT_QUOTES | ENT_HTML5),
                // Trips are routed by short_id; the numeric id 404s.
                'tripUrl' => url('/trips/'.$this->trip->short_id),
            ]);
    }
}
