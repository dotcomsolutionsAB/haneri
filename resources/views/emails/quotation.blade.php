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
                <h1 style="margin: 0;">Your Quotation</h1>
                <p style="margin-top: 5px;">Quotation #{{ $quotation->quotation_no }} - {{ $quotation->q_user }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 30px; background-color: #fff;">
                <p>Dear {{ $user->name }},</p>
                <p>Thank you for requesting a quotation. Please find the details below:</p>

                <table width="100%" cellpadding="5" cellspacing="0" border="1" style="border-collapse: collapse;">
                    <tr>
                        <th>Quotation No</th>
                        <th>Total Amount</th>
                    </tr>
                    <tr>
                        <td>{{ $quotation->quotation_no }}</td>
                        <td>{{ $quotation->total_amount }}</td>
                    </tr>
                </table>

                <p>You can view the detailed quotation and download it by clicking the link below:</p>
                <p>
                    <a href="{{ asset('storage/' . $quotation->invoice_quotation) }}" target="_blank" style="text-decoration: none; color: #00473e; font-weight: bold;">View Quotation PDF</a>
                </p>

                <p>If you have any questions, feel free to contact us.</p>

                <p>Best regards,<br>{{ env('APP_NAME') }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
