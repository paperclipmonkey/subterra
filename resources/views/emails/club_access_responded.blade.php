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
        <p>You now have access to the club's member features.</p>
    @else
        <p>We’re sorry, but your request to join <strong>{{ $club->name }}</strong> has been <span style="color:red;"><strong>rejected</strong></span>.</p>
        <p>If you believe this is a mistake, you may contact a club administrator for more information.</p>
    @endif
    <p>Thank you for your interest in Subterra clubs!</p>
    <p>Best regards,<br>The Subterra Team</p>
</body>
</html>
