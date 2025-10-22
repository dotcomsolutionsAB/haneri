<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome to {{ $siteName }}</title>
</head>
<body style="font-family: Arial, sans-serif; margin: 0; padding: 0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #dff2f0; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 10px; overflow: hidden;">
                    <tr>
                        <td style="background-color: #00473e; color: #ffffff; text-align: center; padding: 20px 0;">
                            <h1 style="margin: 0; font-size: 22px;">Password Reset Request</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px; color: #333333;">
                            <h2 style="color: #00473e; margin-top: 0;">Hello {{ $user->name ?? 'User' }},</h2>
                            <p>Your password has been reset. Please use the temporary password below to log in.</p>

                            <div style="background-color: #dff2f0; border-left: 4px solid #00473e; padding: 15px; margin: 20px 0;">
                                <p style="margin:0 0 8px 0; color:#00473e; font-weight:bold;">Temporary Password</p>
                                <div style="font-size:20px; font-weight:bold; margin:10px 0 0 0; letter-spacing:1px;">
                                    {{ $newPassword }}
                                </div>
                            </div>

                            <p style="margin: 20px 0 10px;">
                                <a href="{{ $loginUrl }}" style="display:inline-block; background-color:#00473e; color:#ffffff; padding:12px 24px; text-decoration:none; border-radius:6px;">
                                    Go to Login
                                </a>
                            </p>

                            <p style="margin-top: 15px;"><strong>Important:</strong> For your security, please log in and <strong>change this password immediately</strong>.</p>

                            <p style="margin-top: 25px;">If you didn’t request this change, please contact our support team:</p>
                            <ul style="list-style:none; padding-left:0; color:#00473e; margin:0 0 20px 0;">
                                <li>📩 <strong>Customer Support:</strong> <a href="mailto:{{ $supportEmail }}" style="color:#00473e;">{{ $supportEmail }}</a></li>
                                <li>🛠 <strong>Technical Support:</strong> <a href="mailto:{{ $techSupportEmail }}" style="color:#00473e;">{{ $techSupportEmail }}</a></li>
                            </ul>

                            <p style="margin-top: 30px;">Regards,<br>
                            <strong>Team {{ $siteName }}</strong><br>
                            <a href="{{ config('app.url') }}" style="color:#00473e;">{{ config('app.url') }}</a></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#00473e; color:#ffffff; text-align:center; padding:15px; font-size:12px;">
                            &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
