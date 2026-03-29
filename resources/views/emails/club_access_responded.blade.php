<x-mail::message>
# Club Access Request Update

Hello {{ $user->name }},

@if ($status === 'approved')
Good news! Your request to join **{{ $club->name }}** has been **approved**.

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
We’re sorry, but your request to join **{{ $club->name }}** has been **rejected**.

@if (isset($reason) && $reason === 'incorrect_name')
<x-mail::panel>
**Reason: Invalid Name**

We require members to use their full legal first and last name to ensure everyone is correctly identified and tagged in emergency safety callouts and trip logs.
</x-mail::panel>

Please update your name in your profile and feel free to re-apply to join the club.
@else
If you believe this is a mistake, you may contact a club administrator for more information.
@endif
@endif

Thank you for your interest in Subterra clubs!

Best regards,<br>
{{ config('app.name') }}
</x-mail::message>
