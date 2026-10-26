<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Callout;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CalloutOpenedDONotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Callout $callout)
    {
    }

    public function envelope(): Envelope
    {
        $time = $this->callout->callout_time->timezone(config('app.display_timezone'))->format('H:i');

        return new Envelope(
            subject: "Callout opened: {$this->callout->cave_name}, due back {$time}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.callouts.do_notification',
        );
    }
}
