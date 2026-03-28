<x-mail::message>
# Medal Awarded

Hi {{ $user->name }},

Congratulations! You have earned a new medal:

<x-mail::panel>
@if($medal->image_path)
<img src="{{ Storage::disk('medals')->url(str_replace('.svg', '.png', $medal->image_path)) }}" alt="{{ $medal->name }}" style="height:64px;vertical-align:middle;margin-right:12px;border-radius:8px;background:#fff;box-shadow:0 2px 8px #eee;" />
@endif
**{{ $medal->name }}**
</x-mail::panel>

@if($medal->description)
{{ $medal->description }}
@endif

<x-mail::button :url="config('app.url') . '/profile/' . $user->id" color="primary">
View Your Medals
</x-mail::button>

Keep up the great work!

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
