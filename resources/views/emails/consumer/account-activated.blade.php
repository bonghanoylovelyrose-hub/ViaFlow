<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Consumer Account Approved</title>
@include('emails.responsive-styles')
</head>
<body style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-family: Arial, sans-serif; color: #1f2933; line-height: 1.6; margin: 0; padding: 24px; background: #f5f7fa;">
    <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;width:100%;max-width:600px; margin: 0 auto; background: #ffffff; border: 1px solid #dbe6ee; border-radius: 12px; padding: 32px;">
        <h1 style="margin-top: 0; font-size: 24px;">Your connection request has been approved</h1>

        <p>Hello {{ $consumer->full_name }},</p>
        <p>Your Via-Vida Waterworks consumer account is now active.@if ($installationDate) Your installation is scheduled on <strong>{{ $installationDate }}</strong>.@endif</p>
        <p><strong>Application Reference:</strong> {{ $consumer->application_reference }}</p>

        @if ($installationDate)
        @php
            $rescheduleUrl = $rescheduleUrl ?? \Illuminate\Support\Facades\URL::signedRoute('installation.reschedule.connection', ['consumer' => $consumer->id]);
        @endphp
        <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;background-color: #f0f7f9; border: 1px solid #cce4ec; border-radius: 8px; padding: 16px; margin: 20px 0;">
            <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 8px; font-size: 15px; color: #102a43;">
                <strong>Scheduled Installation Date:</strong> {{ $installationDate }}
            </p>
            <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 12px; font-size: 13px; color: #52606d;">
                If you are unavailable on this date, you can submit a reschedule request for administrator review.
            </p>
            <a href="{{ $rescheduleUrl }}" target="_blank" rel="noopener" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;display: inline-block; padding: 10px 22px; font-family: Arial, sans-serif; font-size: 14px; font-weight: 700; color: #0f6e8c; background-color: #ffffff; border: 1px solid #0f6e8c; text-decoration: none; border-radius: 6px; line-height: 1.2;">Request for Reschedule</a>
        </div>
        @endif

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin-bottom: 8px;"><strong>Your account details</strong></p>
        <table style="width: 100%; max-width: 600px; table-layout: fixed; border-collapse: collapse; margin-bottom: 16px;">
            <tr>
                <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;padding:8px 0;border-bottom:1px solid #dbe6ee;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;"><span class="email-detail-label" style="display:block;">Account Number</span><span class="email-detail-value" style="display:block;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-all;"><strong>{{ $consumer->account_number }}</strong></span></td>
            </tr>
            <tr>
                <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;padding:8px 0;border-bottom:1px solid #dbe6ee;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;"><span class="email-detail-label" style="display:block;">Meter Number (Username)</span><span class="email-detail-value" style="display:block;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-all;"><strong>{{ $consumer->meter_number }}</strong></span></td>
            </tr>
            @if (isset($temporaryPassword))
                <tr><td style="padding:8px 0;border-bottom:1px solid #dbe6ee;"><span class="email-detail-label" style="display:block;">Temporary Password</span><strong id="temporary-password">{{ $temporaryPassword }}</strong></td></tr>
            @endif
            <tr>
                <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;padding:8px 0;border-bottom:1px solid #dbe6ee;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;"><span class="email-detail-label" style="display:block;">Registered Email Address</span><span class="email-detail-value" style="display:block;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-all;"><strong>{{ $consumer->email ?? $consumer->user?->email }}</strong></span></td>
            </tr>
        </table>

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-size: 13px; color: #52606d; margin: 0 0 18px;">
            <em>Use this registered email address and your meter number when requesting a secure reset link through Forgot Password.</em>
        </p>

        <p>Use your <strong>meter number</strong> as your username, then choose a private password using the single-use setup link below.</p>
        <p><a href="{{ $passwordSetupUrl ?? route('password.forgot') }}" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;color: #0f6e8c; font-weight: bold;">Set / Reset Password Securely</a></p>
        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-size: 13px;">The setup link expires in {{ config('auth.passwords.users.expire', 60) }} minutes.</p>

        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 24px auto; text-align: center;">
            <tr>
                <td align="center" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;word-wrap:break-word;overflow-wrap:break-word;border-radius: 8px; background-color: #0f6e8c;">
                    <a href="{{ $loginUrl ?? route('login') }}" target="_blank" rel="noopener" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;display: inline-block; padding: 14px 36px; font-family: Arial, sans-serif; font-size: 16px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 8px; line-height: 1.2;">Login Now</a>
                </td>
            </tr>
        </table>

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-size: 13px; color: #6a7d86; word-break: break-all; margin: 0 0 20px;">
            If the button above does not work, you can sign in directly at:<br>
            <a href="{{ $loginUrl ?? route('login') }}" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;color: #0f6e8c;">{{ $loginUrl ?? route('login') }}</a>
        </p>

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin-bottom: 0;">Thank you.</p>
    </div>
</body>
</html>
