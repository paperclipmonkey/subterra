<!DOCTYPE html>
<html>
<head>
    <title>New User Signup</title>
</head>
<body>
    <h1>New User Requires Approval</h1>

    <p>A new user has signed up and requires your approval:</p>

    <ul>
        <li><strong>Name:</strong> {{ $userName }}</li>
        <li><strong>Email:</strong> {{ $userEmail }}</li>
    </ul>

    <p>Please review their details and approve or deny their access:</p>

    <p><a href="{{ $approvalUrl }}">Review User</a></p>

    <p>Thanks,</p>
    <p>{{ config('app.name') }}</p>
</body>
</html>
