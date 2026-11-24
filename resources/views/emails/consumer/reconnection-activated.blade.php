<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Water Service Reconnection Schedule Confirmation</title>
@include('emails.responsive-styles')
</head>
<body style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-family: Arial, sans-serif; color: #1f2933; line-height: 1.6; margin: 0; padding: 24px; background: #f5f7fa;">
    <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;width:100%;max-width:600px; margin: 0 auto; background: #ffffff; border: 1px solid #dbe6ee; border-radius: 12px; padding: 32px;">
        <h1 style="margin-top: 0; font-size: 24px;">{{ $consumer->reconnection_pending ? 'Your water service reconnection has been scheduled' : 'Your water service has been reconnected' }}</h1>

        <p>Hello {{ $consumer->full_name }},</p>
        @if ($consumer->reconnection_pending)
            <p>Your Via-Vida Waterworks account is now <strong>{{ $accountStatus }}</strong>. Your reconnection has been scheduled. Please wait for confirmation that your water service has been restored.</p>
        @else
            <p>Your Via-Vida Waterworks account is <strong>{{ $accountStatus }}</strong> and your water service has been restored.</p>
        @endif

        <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;background-color: #f0f7f9; border: 1px solid #cce4ec; border-radius: 8px; padding: 16px; margin: 20px 0;">
            <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 8px; font-size: 15px; color: #102a43;">
                <strong>Scheduled Reconnection Date:</strong> {{ $scheduledDate }}
            </p>
            <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 8px; font-size: 15px; color: #102a43;">
                <strong>Scheduled Reconnection Time (Philippine Time):</strong> {{ $scheduledTime }}
            </p>
            @if ($consumer->reconnection_pending)
                @include('emails.consumer.reconnection-reschedule-action')
            @endif
            @if (filled($adminNotes))
                <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 8px; font-size: 13px; color: #52606d;">
                    <strong>Admin Notes:</strong> {{ $adminNotes }}
                </p>
            @endif
            @if ($consumer->reconnection_pending)
                <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 12px; font-size: 13px; color: #52606d;">
                    Please ensure that you or an authorized representative is available at the service address during the scheduled reconnection.
                </p>
            @endif
        </div>

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin-bottom: 8px;"><strong>Your account details</strong></p>
        <table style="width: 100%; max-width: 600px; table-layout: fixed; border-collapse: collapse; margin-bottom: 16px;">
            @foreach (['Account Number' => $consumer->account_number, 'Meter Number (Username)' => $consumer->meter_number, 'Registered Email Address' => $registeredEmail] as $label => $value)
                <tr>
                    <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;padding:8px 0;border-bottom:1px solid #dbe6ee;"><span class="email-detail-label" style="display:block;">{{ $label }}</span><span class="email-detail-value" style="display:block;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-all;"><strong>{{ $value }}</strong></span></td>
                </tr>
            @endforeach
            @if ($temporaryPassword !== null)
                <tr><td style="padding:8px 0;border-bottom:1px solid #dbe6ee;"><span class="email-detail-label" style="display:block;">Temporary Password</span><code style="font-family: Arial, sans-serif; font-weight: bold;">{{ $temporaryPassword }}</code></td></tr>
            @endif
        </table>

        @if ($temporaryPassword)
            <p>Sign in with your <strong>meter number</strong> and the temporary password above. Please choose a new password after signing in.</p>
        @else
            <p>Use your existing sign-in details to access your account.</p>
        @endif

        @if ($passwordSetupUrl)
            <p><a href="{{ $passwordSetupUrl }}" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;color: #0f6e8c; font-weight: bold;">Set / Reset Password Securely</a></p>
            <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-size: 13px;">The setup link expires in {{ config('auth.passwords.users.expire', 60) }} minutes.</p>
        @endif

        @if ($loginUrl)
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 24px auto; text-align: center;">
                <tr>
                    <td align="center" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;border-radius: 8px; background-color: #0f6e8c;">
                        <a href="{{ $loginUrl }}" target="_blank" rel="noopener" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;display: inline-block; padding: 14px 36px; font-family: Arial, sans-serif; font-size: 16px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 8px; line-height: 1.2;">Login Now</a>
                    </td>
                </tr>
            </table>
            <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-size: 13px; color: #6a7d86; word-break: break-all; margin: 0 0 20px;">
                If the button above does not work, sign in directly at:<br>
                <a href="{{ $loginUrl }}" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;color: #0f6e8c;">{{ $loginUrl }}</a>
            </p>
        @endif

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-size: 13px; color: #52606d; margin: 0 0 18px;">
            <strong>Customer Support</strong><br>{{ config('support.office') }}
            @if (config('support.email'))<br>Email: <a href="mailto:{{ config('support.email') }}" style="color: #0f6e8c;">{{ config('support.email') }}</a>@endif
            @if (config('support.phone'))<br>Phone: {{ config('support.phone') }}@endif
        </p>
        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin-bottom: 0;">Thank you.</p>
    </div>
</body>
</html>
