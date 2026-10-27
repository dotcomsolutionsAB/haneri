@extends('emails.layout')

@section('documentTitle', 'New contact form — ' . $siteName)

@section('title', 'New contact form submission')

@section('subtitle', $siteName)

@section('content')
<p style="margin: 0 0 20px 0;">Someone submitted the website contact form. Details below.</p>

<div style="background-color: #f5f9f8; border-left: 4px solid #00473e; padding: 18px; margin: 20px 0;">
    <p style="margin: 6px 0;"><strong>Submission ID:</strong> #{{ $contact->id }}</p>
    <p style="margin: 6px 0;"><strong>Name:</strong> {{ $contact->name }}</p>
    <p style="margin: 6px 0;"><strong>Email:</strong> <a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a></p>
    @if(!empty($contact->mobile))
        <p style="margin: 6px 0;"><strong>Mobile:</strong> {{ $contact->mobile }}</p>
    @endif
</div>

@if(!empty($contact->comments))
    <h3 style="color: #00473e; margin: 24px 0 10px 0;">Message</h3>
    <div style="background-color: #fafafa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; white-space: pre-wrap;">{{ $contact->comments }}</div>
@else
    <p style="color: #666; font-style: italic; margin-top: 16px;">No additional comments were provided.</p>
@endif

<p style="margin-top: 28px; font-size: 13px; color: #666;">
    Reply to this email to respond directly to the visitor (Reply-To is set to their address).
</p>
@endsection
