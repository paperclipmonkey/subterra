<x-mail::message>
# Booking Update

Hello {{ $booking->applicant->name }},

Unfortunately, your booking for **{{ $booking->permit->name }}** on **{{ $booking->date->format('l, j F Y') }}** has not been approved.

@if($booking->rejection_reason)
<x-mail::panel>
**Reason:** {{ $booking->rejection_reason }}
</x-mail::panel>
@endif

You may wish to apply for a different date.

<x-mail::button :url="url('/bookings')" color="primary">
View My Bookings
</x-mail::button>

Thank you,<br>
{{ config('app.name') }}
</x-mail::message>
