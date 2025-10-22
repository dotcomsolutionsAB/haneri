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
                            <h1 style="margin: 0; font-size: 24px;">Welcome to {{ $siteName }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px; color: #333333;">
                            <h2 style="color: #00473e;">Hello {{ $user->name }},</h2>
                            <p>Thank you for registering with us! Your account has been successfully created.</p>

                            <div style="background-color: #dff2f0; border-left: 4px solid #00473e; padding: 15px; margin: 20px 0;">
                                <h3 style="margin: 0 0 10px 0; color: #00473e;">Account Details:</h3>
                                <p style="margin: 5px 0;"><strong>Email:</strong> {{ $user->email }}</p>
                                <p style="margin-top: 10px;"><em>You can now log in and start exploring.</em></p>
                            </div>

                            <p style="margin-bottom: 30px;">
                                <a href="{{ $loginUrl }}" style="display: inline-block; background-color: #00473e; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 6px;">
                                    Go to Login
                                </a>
                            </p>

                            <p>If you didn’t create this account, please ignore this email or contact our support team:</p>
                            <ul style="list-style:none; padding-left:0; color:#00473e; margin:0 0 20px 0;">
                                <li>📩 <strong>Customer Support:</strong> 
                                    <a href="mailto:{{ $supportEmail }}" style="color:#00473e;">{{ $supportEmail }}</a>
                                </li>
                                <li>🛠 <strong>Technical Support:</strong> 
                                    <a href="mailto:{{ $techSupportEmail }}" style="color:#00473e;">{{ $techSupportEmail }}</a>
                                </li>
                            </ul>

                            <p style="margin-top: 30px;">Best Regards,<br>
                            <strong>Team {{ $siteName }}</strong><br>
                            <a href="{{ $frontendUrl }}" style="color: #00473e;">Haneri</a></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #00473e; color: #ffffff; text-align: center; padding: 15px; font-size: 12px;">
                            &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
