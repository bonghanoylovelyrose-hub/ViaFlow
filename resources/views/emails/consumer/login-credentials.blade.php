<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>ViaFlow Login Details</title>@include('emails.responsive-styles')</head>
<body style="font-family:Arial,sans-serif;color:#102a43;padding:24px"><main style="max-width:600px;margin:auto">
    <h1>Your ViaFlow Login Details</h1>
    <p>Hello {{ $consumer->full_name }},</p>
    <p><strong>Username / Meter Number:</strong> {{ $consumer->meter_number }}<br><strong>Temporary Password:</strong> {{ $temporaryPassword }}</p>
    <p>Sign in with these details. You can change your password in your account profile whenever you choose.</p>
    <p><a href="{{ route('login') }}" style="color:#0754c8;font-weight:bold">Sign In</a></p>
    <p>If you did not expect this email, contact the office.</p>
</main></body></html>
