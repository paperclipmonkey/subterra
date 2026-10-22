<x-mail::message>
# Callout Opened

A new safety callout has been opened while you are on call as Duty Officer.

<x-mail::panel>
**Opened by:** {{ $callout->user->name }}<br>
**Location:** {{ $callout->cave_name }}<br>
**Rescue Activation Time:** {{ $callout->callout_time->timezone(config('app.display_timezone'))->format('H:i, D jS M') }}
</x-mail::panel>

<x-mail::button :url="config('app.url') . '/admin/callout'" color="primary">
View Open Callouts
</x-mail::button>

You are receiving this because your on-call shift has notifications enabled.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
