<x-mail::message>
# Login to Your Account

Hello!

You requested a login link for Subterra. Click the button below to securely log in to your account:

<x-mail::button :url="$magicLinkUrl" color="primary">
🔐 Login to Subterra
</x-mail::button>

<x-mail::panel>
**Security Notice:** This link will expire in 30 minutes for your security. If you didn't request this login link, you can safely ignore this email.
</x-mail::panel>

If the button doesn't work, you can copy and paste this link into your browser:
[{{ $magicLinkUrl }}]({{ $magicLinkUrl }})

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
