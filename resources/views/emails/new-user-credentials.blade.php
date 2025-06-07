{{-- resources/views/emails/new-user-credentials.blade.php --}}
@component('mail::message')
# Welcome to Our Platform!

An account has been created for you by the administrator. Here are your login credentials:

**Email:** {{ $user->email }}  
**Password:** {{ $password }}

You'll receive an email verification link when you try to log in.
Please verify your email, then log in and change your password for security reasons.

@component('mail::button',  ['url' => config('app.url').'/auth/login'])
Login to Your Account
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent