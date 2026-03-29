<x-mail::message>
# Callout Cancelled

The safety callout for **{{ $callout->user->name }}** has been cancelled.

<x-mail::panel>
The team has reported they are **SAFE**. No further action is required.
</x-mail::panel>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
