@extends('emails.layout')

@section('documentTitle', 'Order #' . $order->id . ' Status Update - ' . $siteName)

@section('title', 'Your Order Status has been Updated')

@section('subtitle')
Order #{{ $order->id }} • {{ \Carbon\Carbon::parse($order->updated_at)->format('d M Y, h:i A') }}
@endsection

@section('content')
<h2 style="color:#00473e; margin-top:0;">Hello {{ $user->name }},</h2>
<p>Your order status has been updated. Below are the new details:</p>

<div style="background-color:#dff2f0; border-left:4px solid #00473e; padding:15px; margin:20px 0;">
    <p style="margin:0 0 6px 0;"><strong>Status:</strong> {{ ucfirst($order->status) }}</p>
    <p style="margin:0 0 6px 0;"><strong>Payment:</strong> {{ ucfirst($order->payment_status) }}</p>
    <p style="margin:0 0 6px 0;"><strong>Delivery Status:</strong> {{ ucfirst($order->delivery_status) }}</p>
</div>

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
@endsection
