<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Verify Your Email</title>@include('emails.responsive-styles')</head>
<body style="font-family:Arial,sans-serif;color:#102a43;padding:24px"><main style="max-width:600px;margin:auto">
    <h1>Verify Your Email</h1>
    <p>Hello {{ $consumer->full_name }},</p>
    <p>An administrator updated the email address for your ViaFlow account. Confirm this address to receive your updated login details.</p>
    <p><a href="{{ $verificationUrl }}" style="color:#0754c8;font-weight:bold">Verify Email</a></p>
    <p>This link expires in 24 hours. If you did not expect this change, contact the office.</p>
</main></body></html>
