@extends('emails.layout')

@section('documentTitle', 'Your Quotation - ' . $quotation->quotation_no . ' - ' . $siteName)

@section('title', 'Your Quotation has been Generated')

@section('subtitle')
Quotation #{{ $quotation->quotation_no }} • {{ \Carbon\Carbon::parse($quotation->created_at)->format('d M Y, h:i A') }}
@endsection

@section('content')
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
    <a href="https://haneri.com/account/profile#quotation" target="_blank" style="display:inline-block; background-color:#00473e; color:#ffffff; padding:12px 22px; text-decoration:none; border-radius:6px;">
        View Your Quotation
    </a>
</p>

<p>If you have any questions, reach us at
    <a href="mailto:{{ $supportEmail }}" style="color:#00473e;">{{ $supportEmail }}</a>.
    For technical issues, contact
    <a href="mailto:{{ $techSupportEmail }}" style="color:#00473e;">{{ $techSupportEmail }}</a>.
</p>
@endsection
