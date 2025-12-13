<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order #{{ $order->id }} Status Update - {{ $siteName }}</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #dff2f0; margin: 0; padding: 0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#dff2f0; padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:10px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#00473e; color:#ffffff; text-align:center; padding:20px 0;">
                            <h1 style="margin:0; font-size:22px;">Your Order Status has been Updated</h1>
                            <div style="margin-top:6px; font-size:14px;">Order #{{ $order->id }} • {{ \Carbon\Carbon::parse($order->updated_at)->format('d M Y, h:i A') }}</div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px; color:#333333;">
                            <h2 style="color:#00473e; margin-top:0;">Hello {{ $user->name }},</h2>
                            <p>Your order status has been updated. Below are the new details:</p>

                            <div style="background-color:#dff2f0; border-left:4px solid #00473e; padding:15px; margin:20px 0;">
                                <p style="margin:0 0 6px 0;"><strong>Status:</strong> {{ ucfirst($order->status) }}</p>
                                <p style="margin:0 0 6px 0;"><strong>Payment:</strong> {{ ucfirst($order->payment_status) }}</p>
                                <p style="margin:0 0 6px 0;"><strong>Delivery Status:</strong> {{ ucfirst($order->delivery_status) }}</p>
                            </div>

                            {{-- Invoice Link --}}
                            @if ($invoice)
                                <p style="margin-top: 20px;">
                                    <a href="{{ $invoice['url'] }}" target="_blank" style="display:inline-block; background-color:#00473e; color:#ffffff; padding:12px 22px; text-decoration:none; border-radius:6px;">
                                        View Invoice
                                    </a>
                                </p>
                            @endif

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
