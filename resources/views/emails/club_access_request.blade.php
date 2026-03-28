<x-mail::message>
# Club Access Request

Hello {{ $admin->name }},

**{{ $user->name }}** ({{ $user->email }}) has requested to join the club **{{ $club->name }}**.

Please review their request:

<x-mail::panel>
- **User Name:** {{ $user->name }}
- **User Email:** {{ $user->email }}
- **Requested Club:** {{ $club->name }}
</x-mail::panel>

<x-mail::button :url="url('/club/' . $club->slug . '?editClub=1&tab=pending')" color="primary">
Review Membership Requests
</x-mail::button>

Thank you,<br>
{{ config('app.name') }}
</x-mail::message>
