<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New website order #{{ $order->id }}</title>
</head>
<body style="font-family: Arial, sans-serif; margin: 0; padding: 0;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:10px; overflow:hidden;">
                <tr>
                    <td style="background-color:#00473e; color:#ffffff; text-align:center; padding:20px 0;">
                        <h1 style="margin:0; font-size:22px;">New website order</h1>
                        <div style="margin-top:6px; font-size:14px;">Order #{{ $order->id }} • {{ \Carbon\Carbon::parse($order->created_at)->format('d M Y, h:i A') }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:30px; color:#333333;">
                        <p style="margin-top:0;">A customer paid for an order on {{ $siteName }}.</p>

                        <div style="background-color:#f5f9f8; border-left:4px solid #00473e; padding:15px; margin:20px 0;">
                            <p style="margin:0 0 6px 0;"><strong>Customer:</strong> {{ $user->name }}</p>
                            <p style="margin:0 0 6px 0;"><strong>Email:</strong> <a href="mailto:{{ $user->email }}" style="color:#00473e;">{{ $user->email }}</a></p>
                            @if(!empty($user->mobile))
                                <p style="margin:0 0 6px 0;"><strong>Mobile:</strong> {{ $user->mobile }}</p>
                            @endif
                            <p style="margin:0 0 6px 0;"><strong>Payment:</strong> {{ ucfirst($order->payment_status) }}</p>
                            <p style="margin:0;"><strong>Shipping address:</strong> {{ $order->shipping_address }}</p>
                        </div>

                        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:10px 0 20px 0;">
                            <thead>
                            <tr>
                                <th align="left" style="padding:12px; border-bottom:2px solid #00473e; color:#00473e;">Product</th>
                                <th align="left" style="padding:12px; border-bottom:2px solid #00473e; color:#00473e;">Variant</th>
                                <th align="center" style="padding:12px; border-bottom:2px solid #00473e; color:#00473e;">Qty</th>
                                <th align="right" style="padding:12px; border-bottom:2px solid #00473e; color:#00473e;">Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($items as $it)
                                <tr>
                                    <td style="padding:10px; border-bottom:1px solid #e6e6e6;">{{ $it['name'] }}</td>
                                    <td style="padding:10px; border-bottom:1px solid #e6e6e6;">{{ $it['variant'] ?? '-' }}</td>
                                    <td style="padding:10px; border-bottom:1px solid #e6e6e6;" align="center">{{ $it['qty'] }}</td>
                                    <td style="padding:10px; border-bottom:1px solid #e6e6e6;" align="right">₹ {{ number_format((float) $it['total'], 2) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                        <p style="margin:0 0 22px 0; text-align:right;"><strong>Grand total:</strong> ₹ {{ number_format((float) $order->total_amount, 2) }}</p>

                        <p style="margin:22px 0;">
                            <a href="{{ $orderUrl }}"
                               style="display:inline-block; background-color:#00473e; color:#ffffff; padding:12px 22px; text-decoration:none; border-radius:6px;">
                                Open order in admin
                            </a>
                        </p>

                        <p style="margin-top:28px; font-size:13px; color:#666;">Reply to this email to reach the customer.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
