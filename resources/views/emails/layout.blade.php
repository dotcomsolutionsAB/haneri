<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>@yield('documentTitle')</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #dff2f0; margin: 0; padding: 0;">
@php
    $mailSiteName = $siteName ?? config('app.name', 'Haneri');
    $mailFrontendUrl = $frontendUrl ?? env('APP_FRONTEND_URL', 'https://haneri.com');
@endphp
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#dff2f0; padding:40px 0;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:10px; overflow:hidden;">
                <tr>
                    <td style="background-color:#00473e; color:#ffffff; text-align:center; padding:20px 0;">
                        <h1 style="margin:0; font-size:22px;">@yield('title')</h1>
                        @hasSection('subtitle')
                            <div style="margin-top:6px; font-size:14px;">@yield('subtitle')</div>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:30px; color:#333333;">
                        @yield('content')

                        <p style="margin-top:30px;">Best Regards,<br>
                            <strong>Team {{ $mailSiteName }}</strong><br>
                            <a href="{{ $mailFrontendUrl }}" style="color:#00473e;"><b>HANERI ELECTRICALS LLP</b></a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color:#00473e; color:#ffffff; text-align:center; padding:15px; font-size:12px;">
                        &copy; {{ date('Y') }} {{ $mailSiteName }}. All rights reserved.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
