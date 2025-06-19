<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your New Password</title>
</head>
<body>
    <h2>Password Reset Request</h2>
    <p>Dear {{ $user->name ?? 'User' }},</p>
    <p>Your password has been reset. Here is your new password:</p>
    <div style="font-size:20px;font-weight:bold;margin:20px 0;">{{ $newPassword }}</div>
    <p><b>Please log in and change this password immediately for security.</b></p>
    <br>
    <p>Regards,<br>Your Team</p>
</body>
</html>
