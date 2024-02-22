<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Invitation</title>
</head>
<body>
    <p>Hello,</p>
    <p>You have been invited to join a team. Please click the link below to join:</p>
    <a href="{{ $teamInviteLink }}">{{ $teamInviteLink }}</a>
    <p>Your role in the team will be: {{ $role }}</p>
    <p>Thank you!</p>
</body>
</html>