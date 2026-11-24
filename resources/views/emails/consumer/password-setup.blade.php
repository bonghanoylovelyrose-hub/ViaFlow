<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Secure Password Setup</title>@include('emails.responsive-styles')
</head>
<body style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-family:Arial,sans-serif;background:#f4f7fa;color:#243746;padding:28px;line-height:1.65;">
    <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;width:100%;max-width:600px;margin:auto;padding:32px;background:#fff;border:1px solid #dbe5ed;border-radius:12px;">
        <h1 style="font-size:24px;color:#0f6e8c;">Set Your Password Securely</h1>
        <p>Dear {{ $consumer->full_name }},</p>
        <p>A password reset was requested for account {{ $consumer->account_number }}. Your current password remains unchanged until you save a new one.</p>
        <p>
            Registered Email Address: <strong>{{ $registeredEmail }}</strong><br>
            Meter Number (Username): <strong>{{ $consumer->meter_number }}</strong><br>
            Temporary Password: <strong id="temporary-password" style="font-family:monospace;">{{ $temporaryPassword }}</strong>
        </p>
        <p><a href="{{ $loginUrl }}" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;display:inline-block;background:#0f6e8c;color:#fff;padding:12px 22px;text-decoration:none;border-radius:6px;">Login</a></p>
        <p>Sign in using your meter number and temporary password. You can change your password in your profile whenever you choose. You can also <a href="{{ $passwordSetupUrl }}">Set / Reset Password Securely</a>.</p>
        <p>The temporary password works until you change your password or receive a replacement. The secure reset link expires in {{ config('auth.passwords.users.expire', 60) }} minutes and can be used once.</p>
        <p>If you did not request a password change, ignore this email.</p>
        <p>Customer Support: {{ config('support.office') }}
            @if (config('support.email'))<br>Email: {{ config('support.email') }}@endif
            @if (config('support.phone'))<br>Phone: {{ config('support.phone') }}@endif
        </p>
    </div>
</body>
</html>
