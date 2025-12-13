<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Your Quotation - {{ $quotation->quotation_no }}</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f7f7f7; padding: 20px;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 20px; background-color: #00473e; color: #fff;">
                <h1 style="margin: 0;">Your Quotation has been generated</h1>
                <p style="margin-top: 5px;">Quotation #{{ $quotation->quotation_no }} - {{ $quotation->q_user }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 30px; background-color: #fff;">
                <p>Dear {{ $user->name }},</p>
                <p>Your quotation has been successfully created. You can review your quotation details by visiting your profile.</p>

                <p>To check your quotation, please visit your profile at: <a href="https://haneri.com/profile" target="_blank">haneri.com/profile</a></p>

                <p>If you have any questions, feel free to contact us.</p>

                <p>Best regards,<br>{{ env('APP_NAME') }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
