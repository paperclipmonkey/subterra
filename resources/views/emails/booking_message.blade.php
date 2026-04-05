<x-mail::message>
# Message from your access officer

You have received a message regarding your booking for **{{ $booking->permit->name }}**.

---

{{ $message }}

---

**Booking reference:** {{ $booking->short_id }}
**Date:** {{ \Carbon\Carbon::parse($booking->date)->format('j F Y') }}
**Permit:** {{ $booking->permit->name }}

<x-mail::button :url="config('app.url') . '/bookings'">
View My Bookings
</x-mail::button>

Thanks,<br>
{{ $senderName }}<br>
{{ config('app.name') }}
</x-mail::message>
