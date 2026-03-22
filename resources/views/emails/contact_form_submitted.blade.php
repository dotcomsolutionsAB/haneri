<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New contact form — {{ $siteName }}</title>
</head>
<body style="font-family: Arial, sans-serif; margin: 0; padding: 0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 10px; overflow: hidden;">
                    <tr>
                        <td style="background-color: #00473e; color: #ffffff; text-align: center; padding: 20px 0;">
                            <h1 style="margin: 0; font-size: 22px;">New contact form submission</h1>
                            <p style="margin: 8px 0 0 0; font-size: 14px; opacity: 0.95;">{{ $siteName }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px; color: #333333;">
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
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
