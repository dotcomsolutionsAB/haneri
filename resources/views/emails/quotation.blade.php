<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Your Quotation - {{ $quotation->quotation_no }} - {{ $siteName }}</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #dff2f0; margin: 0; padding: 0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#dff2f0; padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:10px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#00473e; color:#ffffff; text-align:center; padding:20px 0;">
                            <h1 style="margin:0; font-size:22px;">Your Quotation has been Generated</h1>
                            <div style="margin-top:6px; font-size:14px;">Quotation #{{ $quotation->quotation_no }} • {{ \Carbon\Carbon::parse($quotation->created_at)->format('d M Y, h:i A') }}</div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px; color:#333333;">
                            <h2 style="color:#00473e; margin-top:0;">Hello {{ $user->name }},</h2>
                            <p>Thank you for requesting a quotation. Your quotation has been successfully generated. Below are the details of your quotation:</p>

                            <div style="background-color:#dff2f0; border-left:4px solid #00473e; padding:15px; margin:20px 0;">
                                <p style="margin:0 0 6px 0;"><strong>Quotation No:</strong> {{ $quotation->quotation_no }}</p>
                                <p style="margin:0 0 6px 0;"><strong>Total Amount:</strong> ₹{{ number_format($quotation->total_amount, 2) }}</p>
                                <p style="margin:0 0 6px 0;"><strong>Customer Name:</strong> {{ $quotation->q_user }}</p>
                                <p style="margin:0 0 6px 0;"><strong>Email:</strong> {{ $quotation->q_email }}</p>
                                <p style="margin:0 0 6px 0;"><strong>Phone:</strong> {{ $quotation->q_mobile }}</p>
                            </div>

                            <p>You can view and download the full quotation from your profile:</p>
                            <p>
                                <a href="https://haneri.com/profile#quotation" target="_blank" style="display:inline-block; background-color:#00473e; color:#ffffff; padding:12px 22px; text-decoration:none; border-radius:6px;">
                                    View Your Quotation
                                </a>
                            </p>

                            <p>If you have any questions, reach us at
                                <a href="mailto:{{ $supportEmail }}" style="color:#00473e;">{{ $supportEmail }}</a>.
                                For technical issues, contact
                                <a href="mailto:{{ $techSupportEmail }}" style="color:#00473e;">{{ $techSupportEmail }}</a>.
                            </p>

                            <p style="margin-top:30px;">Best Regards,<br>
                                <strong>Team {{ $siteName }}</strong><br>
                                <a href="{{ $frontendUrl }}" style="color:#00473e;">{{ $frontendUrl }}</a>
                            </p>
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
