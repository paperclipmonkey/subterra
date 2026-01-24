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
        <p>You now have access to the club's member features:</p>
        <ul>
            <li><strong>Shared Trip Logs</strong> - See where your fellow members have been exploring.</li>
            <li><strong>Club Activity Heatmap</strong> - Track your club's collective progress over time.</li>
            <li><strong>Member Directory</strong> - Connect with other cavers in your club.</li>
        </ul>
        <div style="margin: 30px 0;">
            <a href="{{ url('/club/' . $club->slug) }}" style="background-color: #2196F3; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;">View Club Page</a>
        </div>
    @else
        <p>We’re sorry, but your request to join <strong>{{ $club->name }}</strong> has been <span style="color:red;"><strong>rejected</strong></span>.</p>
        <p>If you believe this is a mistake, you may contact a club administrator for more information.</p>
    @endif
    <p>Thank you for your interest in Subterra clubs!</p>
    <p>Best regards,<br>The Subterra Team</p>
</body>
</html>
