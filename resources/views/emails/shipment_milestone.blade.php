@extends('emails.layout')

@section('documentTitle', $headline . ' - Order #' . $order->id)

@section('title', $headline)

@section('subtitle')
Order #{{ $order->id }} • {{ $shipment->courier ?: 'Courier' }}
@endsection

@section('content')
<h2 style="color:#00473e; margin-top:0;">Hello {{ $user->name }},</h2>
<p>{{ $intro }}</p>

<div style="background-color:#dff2f0; border-left:4px solid #00473e; padding:15px; margin:20px 0;">
    <p style="margin:0 0 6px 0;"><strong>Order:</strong> #{{ $order->id }}</p>
    <p style="margin:0 0 6px 0;"><strong>Courier:</strong> {{ $shipment->courier ?: 'Courier' }}</p>
    <p style="margin:0 0 6px 0;"><strong>Status:</strong> {{ $carrierStatus }}</p>
    @if ($location)
        <p style="margin:0 0 6px 0;"><strong>Location:</strong> {{ $location }}</p>
    @endif
    @if ($scannedAt)
        <p style="margin:0;"><strong>Update time:</strong> {{ $scannedAt }}</p>
    @endif
</div>

<p style="margin:0 0 8px 0;"><strong>Tracking</strong></p>
@foreach ($awbs as $row)
    <p style="margin:0 0 8px 0;">
        <a href="{{ $row['url'] }}" style="color:#00473e;">{{ $row['awb'] }}</a>
    </p>
@endforeach

<p>If you have any questions, reach us at
    <a href="mailto:{{ $supportEmail }}" style="color:#00473e;">{{ $supportEmail }}</a>.
</p>
@endsection
