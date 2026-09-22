<x-mail::message>
# Confirm a Member

Hello {{ \App\Support\MailMarkdown::escape($admin->name) }},

**{{ \App\Support\MailMarkdown::escape($user->name) }}** ({{ \App\Support\MailMarkdown::escape($user->email) }}) has signed up to Subterra and says they are already a member of **{{ \App\Support\MailMarkdown::escape($club->name) }}**.

Please confirm whether that's right:

<x-mail::panel>
- **Name:** {{ \App\Support\MailMarkdown::escape($user->name) }}
- **Email:** {{ \App\Support\MailMarkdown::escape($user->email) }}
- **Club:** {{ \App\Support\MailMarkdown::escape($club->name) }}
</x-mail::panel>

{{-- One query parameter, deliberately. The previous two-parameter link
     ("?editClub=1&tab=pending") renders as &amp; in HTML mail and some clients
     and link rewriters pass that through literally, which turned `tab` into
     `amp;tab` and dropped the admin on the wrong tab. --}}
<x-mail::button :url="url('/club/' . $club->slug . '?confirm=members')" color="primary">
Confirm Membership
</x-mail::button>

Confirming a member unlocks the club's cave data and safety features for them.

Thank you,<br>
{{ config('app.name') }}
</x-mail::message>
