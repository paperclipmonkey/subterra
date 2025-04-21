{{-- resources/views/emails/club_access_request.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Club Access Request</title>
</head>
<body>
    <h2>Club Access Request</h2>
    <p>Hello {{ $admin->name }},</p>
    <p><strong>{{ $user->name }}</strong> ({{ $user->email }}) has requested to join the club <strong>{{ $club->name }}</strong>.</p>
    <p>Please review their request:</p>
    <ul>
        <li><strong>User Name:</strong> {{ $user->name }}</li>
        <li><strong>User Email:</strong> {{ $user->email }}</li>
        <li><strong>Requested Club:</strong> {{ $club->name }}</li>
    </ul>
    <p>
        <a href="{{ url('/admin/clubs/' . $club->slug) }}" style="background:#2563eb;color:#fff;padding:10px 18px;text-decoration:none;border-radius:4px;">Review Membership Requests</a>
    </p>
    <p>Thank you,<br>The Subterra Team</p>
</body>
</html>
