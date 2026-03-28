<x-mail::message>
# New User Requires Approval

A new user has signed up and requires your approval:

- **Name:** {{ $userName }}
- **Email:** {{ $userEmail }}

Please review their details and approve or deny their access:

<x-mail::button :url="$approvalUrl" color="primary">
Review User
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
