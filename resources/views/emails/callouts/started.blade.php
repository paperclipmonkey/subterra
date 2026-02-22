<x-mail::message>
# Safety Callout Started

**{{ $callout->user->name }}** has started a safety callout.

**Location:** {{ $callout->cave ? $callout->cave->name : 'Unknown' }}
@if($callout->cave)
<x-mail::button :url="config('app.url') . '/caves/' . $callout->cave->slug" color="primary">
   View Cave Details & Survey
</x-mail::button>
@endif

<br>
**Rescue Activation Time:** {{ $callout->callout_time->format('H:i, D jS M') }}

## Plan
{{ $callout->trip_plan }}

## Participants
@foreach($callout->participants as $participant)
- {{ $participant->name }} ({{ $participant->phone ?? 'No Phone' }})
@endforeach

<br>
### Your Role
You have been tagged as a participant in this callout. The system is currently monitoring your expected return time. **If this callout is not cancelled by the Rescue Activation Time, The Duty Officer on call will initiate rescue.**

If you are safe on the surface, **any participant** can cancel this active callout by clicking the button below.

<x-mail::button :url="config('app.url') . '/callout/active'" color="error">
   View Callout / I AM SAFE
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
