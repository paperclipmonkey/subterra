<x-mail::message>
# Medal Awarded

Hi {{ \App\Support\MailMarkdown::escape($user->name) }},

Congratulations! You have earned a new medal:

<x-mail::panel>
{{-- One HTML block, so Markdown never parses inside it: "**name**" after the
     <img> line was swallowed into the image's HTML block and sent literally.
     Blade's {{ }} HTML-escapes the name, which is all an HTML block needs. --}}
<div>
@if($medalImageUrl)
<img src="{{ $medalImageUrl }}" alt="{{ $medal->name }}" style="height:64px;vertical-align:middle;margin-right:12px;border-radius:8px;background:#fff;box-shadow:0 2px 8px #eee;" />
@endif
<strong>{{ $medal->name }}</strong>
</div>
</x-mail::panel>

@if($medal->description)
{{ \App\Support\MailMarkdown::escape($medal->description) }}
@endif

<x-mail::button :url="config('app.url') . '/profile/' . $user->id" color="primary">
View Your Medals
</x-mail::button>

Keep up the great work!

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
