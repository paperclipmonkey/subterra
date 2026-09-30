<x-mail::message>
# Booking Update

Hello {{ \App\Support\MailMarkdown::escape($booking->applicant->name) }},

Unfortunately, your booking for **{{ \App\Support\MailMarkdown::escape($booking->permit->name) }}** on **{{ $booking->date->format('l, j F Y') }}** has not been approved.

@if($booking->rejection_reason)
<x-mail::panel>
**Reason:** {{ \App\Support\MailMarkdown::escape($booking->rejection_reason) }}
</x-mail::panel>
@endif

You may wish to apply for a different date.

<x-mail::button :url="url('/bookings')" color="primary">
View My Bookings
</x-mail::button>

Thank you,<br>
{{ config('app.name') }}
</x-mail::message>
