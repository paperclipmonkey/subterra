<x-mail::message>
# Booking Approved

Hello {{ $booking->applicant->name }},

Your booking for **{{ $booking->permit->name }}** on **{{ $booking->date->format('l, j F Y') }}** has been approved.

@if($booking->permit->booking_info)
<x-mail::panel>
## Access Information

{!! nl2br(e($booking->permit->booking_info)) !!}
</x-mail::panel>
@endif

<x-mail::panel>
- **Booking Reference:** {{ $booking->short_id }}
- **Date:** {{ $booking->date->format('l, j F Y') }}
- **Participants:** {{ $booking->participants }}
</x-mail::panel>

<x-mail::button :url="url('/bookings')" color="primary">
View My Bookings
</x-mail::button>

Thank you,<br>
{{ config('app.name') }}
</x-mail::message>
