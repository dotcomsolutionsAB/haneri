@component('mail::message')
# Welcome to {{ $siteName }}, {{ $user->name }}!

We're excited to have you on board.

@component('mail::panel')
**Your account has been created successfully.**  
You can sign in anytime to explore our products and manage your orders.
@endcomponent

@component('mail::button', ['url' => $loginUrl])
Go to Login
@endcomponent

If you didn’t create this account, please ignore this email or contact us at {{ $supportEmail }}.

Thanks & regards,  
**Team {{ $siteName }}**  
{{ config('app.url') }}
@endcomponent
