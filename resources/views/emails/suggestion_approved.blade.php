<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4CAF50; color: white; padding: 10px 20px; border-radius: 5px 5px 0 0; }
        .content { border: 1px solid #ddd; padding: 20px; border-radius: 0 0 5px 5px; }
        .footer { margin-top: 20px; font-size: 0.8em; color: #888; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Suggestion Approved!</h1>
        </div>
        <div class="content">
            <p>Hi {{ $suggestedEdit->user->first_name ?: $suggestedEdit->user->name }},</p>
            
            <p>Thank you for your contribution to Subterra!</p>
            
            <p>Your suggested change for <strong>{{ $type }}</strong> has been reviewed and approved by an administrator. It is now live on the platform.</p>
            
            @if($suggestedEdit->admin_comment)
                <p><strong>Admin Comment:</strong> {{ $suggestedEdit->admin_comment }}</p>
            @endif

            @if(isset($itemUrl) && $itemUrl)
                <div style="text-align: center; margin: 20px 0;">
                    <a href="{{ $itemUrl }}" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">View Your Contribution</a>
                </div>
            @endif
            
            <p>We appreciate your help in keeping our data accurate and up-to-date.</p>
            
            <p>Happy Caving!<br>The Subterra Team</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Subterra.world. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
