<x-mail::message>
# Trip Tag Notification

Hi {{ \App\Support\MailMarkdown::escape($user->name) }},

@if(isset($isNewUser) && $isNewUser)
Welcome to Subterra! You have been tagged in a trip report by **{{ \App\Support\MailMarkdown::escape($creator->name) }}**.

Subterra is an open-source platform for the caving community to share trip reports, log cave data, and coordinate activities.

Since you've been tagged, a profile has been initialized for you. You can claim it and view the full trip details by logging directly in. Simply click the link below, enter your email address to receive a magic sign-in link, and you'll be taken straight to the report.
@else
You have been tagged in a trip on [Subterra](https://subterra.world) by **{{ \App\Support\MailMarkdown::escape($creator->name) }}**.
@endif

<x-mail::panel>
**Trip:** {{ \App\Support\MailMarkdown::escape($trip->name) }}<br>
**Description:** {{ \App\Support\MailMarkdown::escape(\Illuminate\Support\Str::limit($trip->description, 200)) }}<br>
**Start Time:** {{ $trip->start_time }}<br>
**End Time:** {{ $trip->end_time }}
</x-mail::panel>

<x-mail::button :url="url('/trips/' . $trip->id)" color="primary">
View Trip details on Subterra
</x-mail::button>

@if(!isset($isNewUser) || !$isNewUser)
Log in to view more details.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
