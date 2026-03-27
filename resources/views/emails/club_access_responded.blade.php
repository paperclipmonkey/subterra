{{-- resources/views/emails/club_access_responded.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Club Access Request Update</title>
</head>
<body>
    <h2>Club Access Request Update</h2>
    <p>Hello {{ $user->name }},</p>
    @if ($status === 'approved')
        <p>Good news! Your request to join <strong>{{ $club->name }}</strong> has been <span style="color:green;"><strong>approved</strong></span>.</p>
        <p>You now have access to the club's member features! 🚀</p>
        <ul>
            <li>🗺️ <strong>Cave Surveys</strong> - Plan your next trip with detailed maps.</li>
            <li>📍 <strong>Cave Locations & Access</strong> - Find exactly where to go and how to get in.</li>
            <li>🚨 <strong>Callouts</strong> - Leave a callout so others know where you are and when you'll be back.</li>
        </ul>
        <div style="margin: 30px 0;">
            <a href="{{ url('/club/' . $club->slug) }}" style="background-color: #2196F3; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;">View Club Page</a>
        </div>
    @else
        <p>We’re sorry, but your request to join <strong>{{ $club->name }}</strong> has been <span style="color:red;"><strong>rejected</strong></span>.</p>
        @if (isset($reason) && $reason === 'incorrect_name')
            <p><strong>Reason:</strong> Invalid Name</p>
            <p>We require members to use their full legal first and last name to ensure everyone is correctly identified and tagged in emergency safety callouts and trip logs.</p>
            <p>Please update your name in your profile and feel free to re-apply to join the club.</p>
        @else
            <p>If you believe this is a mistake, you may contact a club administrator for more information.</p>
        @endif
    @endif
    <p>Thank you for your interest in Subterra clubs!</p>
    <p>Best regards,<br>The Subterra Team</p>
</body>
</html>
