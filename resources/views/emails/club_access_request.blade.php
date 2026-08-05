<x-mail::message>
# Confirm a Member

Hello {{ $admin->name }},

**{{ $user->name }}** ({{ $user->email }}) has signed up to Subterra and says they are already a member of **{{ $club->name }}**.

Please confirm whether that's right:

<x-mail::panel>
- **Name:** {{ $user->name }}
- **Email:** {{ $user->email }}
- **Club:** {{ $club->name }}
</x-mail::panel>

<x-mail::button :url="url('/club/' . $club->slug . '?editClub=1&tab=pending')" color="primary">
Confirm Membership
</x-mail::button>

Confirming a member unlocks the club's cave data and safety features for them.

Thank you,<br>
{{ config('app.name') }}
</x-mail::message>
