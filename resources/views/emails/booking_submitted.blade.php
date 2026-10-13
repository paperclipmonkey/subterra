<x-mail::message>
# New Booking Request

Hello {{ \App\Support\MailMarkdown::escape($officer->name) }},

**{{ \App\Support\MailMarkdown::escape($booking->applicant->name) }}** has submitted a booking request for **{{ \App\Support\MailMarkdown::escape($booking->permit->name) }}**.

<x-mail::panel>
- **Date:** {{ $booking->date->format('l, j F Y') }}
- **Participants:** {{ \App\Support\MailMarkdown::escape($booking->participants) }}
@if($booking->permit->caves->isNotEmpty())
- **Cave{{ $booking->permit->caves->count() > 1 ? 's' : '' }}:** {{ \App\Support\MailMarkdown::escape($booking->permit->caves->pluck('name')->join(', ')) }}
@endif
@if($booking->applicant->clubs->isNotEmpty())
- **Club{{ $booking->applicant->clubs->count() > 1 ? 's' : '' }}:** {{ \App\Support\MailMarkdown::escape($booking->applicant->clubs->pluck('name')->join(', ')) }}
@endif
- **Other bookings that day:** {{ $otherApproved }} approved, {{ $otherPending }} pending{{ $booking->permit->has_max_groups_per_day ? ' (limit '.$booking->permit->max_groups_per_day.' groups)' : '' }}
- **Status:** {{ ucfirst($status) }}
</x-mail::panel>

@if($booking->notes)
**Notes from applicant:**

<x-mail::panel>
{!! nl2br(e(\App\Support\MailMarkdown::escape($booking->notes))) !!}
</x-mail::panel>
@endif

@if($status === 'pending review')
<x-mail::button :url="$approveUrl" color="success">
Review &amp; Approve
</x-mail::button>

[Reject]({{ $rejectUrl }}) · [Message applicant]({{ $messageUrl }}) · [View booking]({{ $reviewUrl }})
@else
<x-mail::button :url="$reviewUrl" color="primary">
View Booking
</x-mail::button>
@endif

Thank you,<br>
{{ config('app.name') }}
</x-mail::message>
