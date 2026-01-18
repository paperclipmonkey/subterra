<x-mail::message>
# Safety Callout Started

**{{ $callout->user->name }}** has started a safety callout.

**Location:** {{ $callout->cave ? $callout->cave->name : 'Unknown' }}
**Rescue Activation:** {{ $callout->callout_time->format('H:i, D jS M') }}

## Plan
{{ $callout->trip_plan }}

## Participants
@foreach($callout->participants as $participant)
- {{ $participant->name }} ({{ $participant->phone ?? 'No Phone' }})
@endforeach

If you are safe, click below or reply via SMS.

<x-mail::button :url="config('app.url') . '/callout/active'" color="error">
   View Callout / I AM SAFE
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
