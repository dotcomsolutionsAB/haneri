@extends('emails.layout')

@section('documentTitle', $siteName)

@section('title')
{{ $stage === 2 ? 'Your cart is still waiting' : 'You left items in your cart' }}
@endsection

@section('content')
<h2 style="color:#00473e; margin-top:0;">Hello {{ $user->name }},</h2>
@if ($stage === 2)
    <p>The items below are still in your cart. Use this one-time code at checkout before it expires.</p>
    <div style="background-color:#dff2f0; border-left:4px solid #00473e; padding:15px; margin:20px 0;">
        <p style="margin:0 0 6px 0;"><strong>Code:</strong> {{ $couponCode }}</p>
        <p style="margin:0 0 6px 0;"><strong>Discount:</strong> {{ $discountPercent }}% off</p>
        <p style="margin:0;"><strong>Expires:</strong> {{ $couponExpiresAt?->timezone(config('app.timezone'))->format('d M Y, h:i A') }}</p>
    </div>
@else
    <p>You added these items but did not finish the order. They are still saved in your cart.</p>
@endif

<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:10px 0 20px 0;">
    <thead>
    <tr>
        <th align="left" style="padding:12px; border-bottom:2px solid #00473e; color:#00473e;">Product</th>
        <th align="left" style="padding:12px; border-bottom:2px solid #00473e; color:#00473e;">Variant</th>
        <th align="center" style="padding:12px; border-bottom:2px solid #00473e; color:#00473e;">Qty</th>
        <th align="right" style="padding:12px; border-bottom:2px solid #00473e; color:#00473e;">Price</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($items as $item)
        <tr>
            <td style="padding:10px; border-bottom:1px solid #e6e6e6;">{{ $item['name'] }}</td>
            <td style="padding:10px; border-bottom:1px solid #e6e6e6;">{{ $item['variant'] ?? '-' }}</td>
            <td style="padding:10px; border-bottom:1px solid #e6e6e6;" align="center">{{ $item['qty'] }}</td>
            <td style="padding:10px; border-bottom:1px solid #e6e6e6;" align="right">₹ {{ number_format((float) $item['total'], 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<p style="margin:22px 0;">
    <a href="{{ $cartUrl }}"
       style="display:inline-block; background-color:#00473e; color:#ffffff; padding:12px 22px; text-decoration:none; border-radius:6px;">
        View cart
    </a>
</p>

<p>If you have any questions, reach us at
    <a href="mailto:{{ $supportEmail }}" style="color:#00473e;">{{ $supportEmail }}</a>.
</p>
@endsection
