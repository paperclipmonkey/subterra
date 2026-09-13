<x-mail::message>
# Suggestion Approved!

Hi {{ $suggestedEdit->user?->first_name ?: $suggestedEdit->user?->name ?? 'there' }},

Thank you for your contribution to Subterra!

@if($itemName)
Your suggested change to the {{ $type }} **{{ $itemName }}** has been reviewed and approved by an administrator. It is now live on the platform.
@else
Your suggested change to a {{ $type }} has been reviewed and approved by an administrator. It is now live on the platform.
@endif

@if($suggestedEdit->admin_comment)
<x-mail::panel>
**Admin Comment:** {{ $suggestedEdit->admin_comment }}
</x-mail::panel>
@endif

@if(isset($itemUrl) && $itemUrl)
<x-mail::button :url="$itemUrl" color="primary">
View Your Contribution
</x-mail::button>
@endif

We appreciate your help in keeping our data accurate and up-to-date.

Happy Caving!<br>
The Subterra Team
</x-mail::message>
