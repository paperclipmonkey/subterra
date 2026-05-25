<x-mail::message>
# Safety Callout Started

**{{ $callout->user->name }}** has started a safety callout.

**Location:** {{ $callout->cave_name }}
@if($callout->cave)
<x-mail::button :url="config('app.url') . '/caves/' . $callout->cave->slug" color="primary">
   View Cave Details & Survey
</x-mail::button>
@endif

<br>
**Rescue Activation Time:** {{ $callout->callout_time->timezone(config('app.display_timezone'))->format('H:i, D jS M') }}

## Plan
<x-mail::panel>
{{ $callout->trip_plan }}
</x-mail::panel>

## Participants
<x-mail::panel>
@foreach($callout->participants as $participant)
- @if($participant->user_id) <a href="{{ config('app.url') . '/profile/' . $participant->user_id }}">{{ $participant->name }}</a> @else {{ $participant->name }} @endif @if($participant->user_id && $participant->user->clubs->count() > 0) <small class="text-gray-500">({{ $participant->user->clubs->reject(fn($c) => $c->pivot->status !== 'approved')->pluck('slug')->join(', ') }})</small> @endif @if($participant->phone || ($participant->user_id && $participant->user->phone)) <small>(Phone Provided)</small> @endif
@endforeach
</x-mail::panel>

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
