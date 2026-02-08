<x-mail::message>
{{-- We render the markdown content passed from the mailable --}}
@component('mail::panel')
{!! Illuminate\Mail\Markdown::parse($body) !!}
@endcomponent

<x-slot:subcopy>
You are receiving this email because you opted in to platform news on Subterra.
If you no longer wish to receive these emails, you can <a href="{{ $unsubscribeUrl }}">unsubscribe here</a>.
</x-slot:subcopy>
</x-mail::message>
