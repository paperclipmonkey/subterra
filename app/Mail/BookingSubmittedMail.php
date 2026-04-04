<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public $booking;
    public $officer;

    public function __construct(Booking $booking, User $officer)
    {
        $this->booking = $booking;
        $this->officer = $officer;
    }

    public function build()
    {
        $status = $this->booking->status === 'approved' ? 'auto-approved' : 'pending review';

        return $this->subject("New Booking Request: {$this->booking->permit->name}")
            ->markdown('emails.booking_submitted')
            ->with([
                'booking' => $this->booking,
                'officer' => $this->officer,
                'status' => $status,
            ]);
    }
}
