<x-mail::message>
# Trip Started Notification

Hi there,

A new trip has been started while you are on call as Duty Officer.

<x-mail::panel>
**Trip:** {{ $trip->name }}<br>
**Cave:** {{ $trip->entrance?->name ?? 'Unknown Location' }}<br>
**Started by:** {{ $creator->name }}<br>
**Start Time:** {{ $trip->start_time->timezone(config('app.display_timezone'))->format('d M Y H:i') }}<br>
@if($trip->end_time)
**Expected Return:** {{ $trip->end_time->timezone(config('app.display_timezone'))->format('d M Y H:i') }}
@endif
</x-mail::panel>

<x-mail::button :url="url('/trips/' . $trip->id)" color="primary">
View Trip on Subterra
</x-mail::button>

You are receiving this because your on-call shift has trip notifications enabled.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
