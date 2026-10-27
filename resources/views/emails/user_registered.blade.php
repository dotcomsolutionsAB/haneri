@extends('emails.layout')

@section('documentTitle', 'Welcome to ' . $siteName)

@section('title')
Welcome to {{ $siteName }}
@endsection

@section('content')
<h2 style="color:#00473e; margin-top:0;">Hello {{ $userName }},</h2>
<p>Thank you for registering with us! Your account has been successfully created.</p>

<div style="background-color:#dff2f0; border-left:4px solid #00473e; padding:15px; margin:20px 0;">
    <p style="margin:0 0 8px 0; color:#00473e; font-weight:bold;">Your Login Credentials</p>
    <p style="margin:0 0 6px 0;"><strong>Email:</strong> {{ $userEmail }}</p>
    <p style="margin:8px 0 0 0; color:#00473e; font-weight:bold;">Password</p>
    <div style="font-size:20px; font-weight:bold; margin:10px 0 0 0; letter-spacing:1px;">
        {{ $userPassword }}
    </div>
</div>

<p style="margin:20px 0 10px;">
    <a href="{{ $loginUrl }}" style="display:inline-block; background-color:#00473e; color:#ffffff; padding:12px 24px; text-decoration:none; border-radius:6px;">
        Go to Login
    </a>
</p>

<p style="margin-top:15px;"><strong>Please change your password after logging in.</strong></p>

<p style="margin-top:25px;">If you didn’t create this account, please ignore this email or contact our support team:</p>
<ul style="list-style:none; padding-left:0; color:#00473e; margin:0 0 20px 0;">
    <li>📩 <strong>Customer Support:</strong> <a href="mailto:{{ $supportEmail }}" style="color:#00473e;">{{ $supportEmail }}</a></li>
    <li>🛠 <strong>Technical Support:</strong> <a href="mailto:{{ $techSupportEmail }}" style="color:#00473e;">{{ $techSupportEmail }}</a></li>
</ul>
@endsection
