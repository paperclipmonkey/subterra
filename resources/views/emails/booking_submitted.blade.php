<x-mail::message>
# New Booking Request

Hello {{ \App\Support\MailMarkdown::escape($officer->name) }},

**{{ \App\Support\MailMarkdown::escape($booking->applicant->name) }}** has submitted a booking request for **{{ \App\Support\MailMarkdown::escape($booking->permit->name) }}**.

<x-mail::panel>
- **Date:** {{ $booking->date->format('l, j F Y') }}
- **Participants:** {{ \App\Support\MailMarkdown::escape($booking->participants) }}
- **Status:** {{ ucfirst($status) }}
@if($booking->notes)
- **Notes:** {{ \App\Support\MailMarkdown::escape($booking->notes) }}
@endif
</x-mail::panel>

@if($status === 'pending review')
<x-mail::button :url="url('/admin/bookings')" color="primary">
Review Bookings
</x-mail::button>
@endif

Thank you,<br>
{{ config('app.name') }}
</x-mail::message>
