@extends('emails.layout')

@section('documentTitle', 'Order #' . $order->id . ' - ' . $siteName)

@section('title', 'Thank you for your order')

@section('subtitle')
Order #{{ $order->id }} • {{ \Carbon\Carbon::parse($order->created_at)->format('d M Y, h:i A') }}
@endsection

@section('content')
<h2 style="color:#00473e; margin-top:0;">Hello {{ $user->name }},</h2>
<p>Your order has been placed successfully. We’ll notify you when it ships.</p>

<div style="background-color:#dff2f0; border-left:4px solid #00473e; padding:15px; margin:20px 0;">
    <p style="margin:0 0 6px 0;"><strong>Status:</strong> {{ ucfirst($order->status) }}</p>
    <p style="margin:0 0 6px 0;"><strong>Payment:</strong> {{ ucfirst($order->payment_status) }}</p>
    <p style="margin:0;"><strong>Shipping Address:</strong> {{ $order->shipping_address }}</p>
</div>

<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:10px 0 20px 0;">
    <thead>
    <tr>
        <th align="left" style="padding:12px; border-bottom:2px solid #00473e; color:#00473e;">Product</th>
        <th align="left" style="padding:12px; border-bottom:2px solid #00473e; color:#00473e;">Variant</th>
        <th align="center" style="padding:12px; border-bottom:2px solid #00473e; color:#00473e;">Qty</th>
        <th align="right" style="padding:12px; border-bottom:2px solid #00473e; color:#00473e;">Price</th>
        <th align="right" style="padding:12px; border-bottom:2px solid #00473e; color:#00473e;">Total</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($items as $it)
        <tr>
            <td style="padding:10px; border-bottom:1px solid #e6e6e6;">{{ $it['name'] }}</td>
            <td style="padding:10px; border-bottom:1px solid #e6e6e6;">{{ $it['variant'] ?? '-' }}</td>
            <td style="padding:10px; border-bottom:1px solid #e6e6e6;" align="center">{{ $it['qty'] }}</td>
            <td style="padding:10px; border-bottom:1px solid #e6e6e6;" align="right">₹ {{ number_format((float)$it['price'], 2) }}</td>
            <td style="padding:10px; border-bottom:1px solid #e6e6e6;" align="right">₹ {{ number_format((float)$it['total'], 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;">
    <tr>
        <td align="right" style="padding:6px 0;"><strong>Grand Total:</strong></td>
        <td align="right" style="padding:6px 0; width:140px;">₹ {{ number_format((float)$order->total_amount, 2) }}</td>
    </tr>
</table>

<p style="margin:22px 0;">
    <a href="{{ $orderUrl }}"
       style="display:inline-block; background-color:#00473e; color:#ffffff; padding:12px 22px; text-decoration:none; border-radius:6px;">
        View Order
    </a>
</p>

<p>If you have any questions, reach us at
    <a href="mailto:{{ $supportEmail }}" style="color:#00473e;">{{ $supportEmail }}</a>.
    For technical issues, contact
    <a href="mailto:{{ $techSupportEmail }}" style="color:#00473e;">{{ $techSupportEmail }}</a>.
</p>
@endsection
