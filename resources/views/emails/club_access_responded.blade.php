<x-mail::message>
@if ($status === 'approved')
# Membership Confirmed

Hello {{ $user->name }},

Good news! **{{ $club->name }}** has confirmed you as one of their members.

You now have access to the club's member features! 🚀

<x-mail::panel>
- 🗺️ **Cave Surveys** - Plan your next trip with detailed maps.
- 📍 **Cave Locations & Access** - Find exactly where to go and how to get in.
- 🚨 **Callouts** - Leave a callout so others know where you are and when you'll be back.
</x-mail::panel>

<x-mail::button :url="url('/club/' . $club->slug)" color="success">
View Club Page
</x-mail::button>
@else
# Membership Not Confirmed

Hello {{ $user->name }},

We're sorry — **{{ $club->name }}** wasn't able to confirm your membership.

@if (isset($reason) && $reason === 'incorrect_name')
<x-mail::panel>
**Reason: Name not recognised**

We require members to use their full legal first and last name, so club administrators can recognise you on their membership list and so you're correctly identified in emergency safety callouts and trip logs.
</x-mail::panel>

Please update your name in your profile, then ask the club to confirm your membership again.
@else
If you believe this is a mistake, contact a club administrator — they can confirm your membership at any time.
@endif
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
