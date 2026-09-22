<x-mail::message>
# You've been added to Subterra

Hi{{ \App\Support\MailMarkdown::escape($user->name ? ' '.$user->name : '') }},

**{{ \App\Support\MailMarkdown::escape($creator->name) }}** added you as a caving contact on [Subterra]({{ url('/') }}), a
platform cavers use to log trips and plan safely. That means a record now exists
for you, even though you haven't signed up yourself. We're required to tell you
that, and we'd want to anyway.

## What we hold

<x-mail::panel>
**Name:** {{ \App\Support\MailMarkdown::escape($user->name ?? 'Not provided') }}<br>
**Email:** {{ \App\Support\MailMarkdown::escape($user->email) }}<br>
**Added by:** {{ \App\Support\MailMarkdown::escape($creator->name) }}<br>
**Added on:** {{ $user->created_at?->format('j F Y') }}
</x-mail::panel>

That's everything. We collected it from {{ \App\Support\MailMarkdown::escape($creator->name) }}, not from you. It lets
them record you as a participant on their trip reports, and lets cave rescue reach
the right people if a trip is overdue.

## What you can do

**If you're happy with this,** you don't need to do anything. You can sign in any
time with this email address to claim the profile, set your own privacy options,
and see what you've been tagged in.

**If you didn't expect this,** or you'd rather not be on Subterra at all, use the
link below. It goes straight to our moderators, who will remove your record. You
don't need an account and you don't need to explain yourself.

<x-mail::button :url="$objectUrl" color="error">
I don't want to be on Subterra
</x-mail::button>

@if($user->date_of_birth === null)
**If this account is for someone under 18,** please use the same link and say so —
we apply extra privacy protections to under-18 accounts and we'd rather get that
right from the start.
@endif

Our [privacy policy]({{ $privacyUrl }}) explains how we look after your data and
your rights over it, including your right to complain to the Information
Commissioner's Office.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
