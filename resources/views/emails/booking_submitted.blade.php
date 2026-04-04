<x-mail::message>
# New Booking Request

Hello {{ $officer->name }},

**{{ $booking->applicant->name }}** has submitted a booking request for **{{ $booking->permit->name }}**.

<x-mail::panel>
- **Date:** {{ $booking->date->format('l, j F Y') }}
- **Participants:** {{ $booking->participants }}
- **Status:** {{ ucfirst($status) }}
@if($booking->notes)
- **Notes:** {{ $booking->notes }}
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
