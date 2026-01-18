<x-mail::message>
# Callout Cancelled

The safety callout for **{{ $callout->user->name }}** has been cancelled.

The team has reported they are **SAFE**. No further action is required.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
