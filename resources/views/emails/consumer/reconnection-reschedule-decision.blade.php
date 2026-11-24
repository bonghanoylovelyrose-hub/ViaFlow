<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Reconnection Reschedule Decision</title>@include('emails.responsive-styles')
</head>
<body style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-family:Arial,sans-serif;background:#f4f7fa;color:#243746;padding:28px;line-height:1.65;">
    <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;width:100%;max-width:600px;margin:auto;background:#fff;border:1px solid #dbe5ed;border-radius:12px;padding:32px;">
        <h1 style="font-size:24px;color:#0f6e8c;">Reconnection Reschedule {{ ucfirst($reschedule->status) }}</h1>
        <p>Dear {{ $consumer->full_name }},</p>
        <p>Your reschedule request for account <strong>{{ $consumer->account_number }}</strong> has been <strong>{{ $reschedule->status }}</strong> by the administrator.</p>
        <div style="background-color:#f0f7f9;border:1px solid #cce4ec;border-radius:8px;padding:16px;margin:20px 0;">
            <p style="margin:0 0 8px;">Your newly confirmed reconnection schedule is <strong>{{ $reschedule->formattedNewSchedule() }}</strong> (Philippine time).</p>
            @include('emails.consumer.reconnection-reschedule-action')
            @if ($reschedule->admin_notes) <p style="margin:0;"><strong>Administrator's notes:</strong> {{ $reschedule->admin_notes }}</p> @endif
        </div>
        <p style="margin-bottom:8px;"><strong>Your account details</strong></p>
        <table style="width:100%;table-layout:fixed;border-collapse:collapse;margin-bottom:16px;">
            @foreach (['Account Number' => $consumer->account_number, 'Meter Number (Username)' => $consumer->meter_number, 'Registered Email Address' => $registeredEmail] as $label => $value)
                <tr><td style="padding:8px 0;border-bottom:1px solid #dbe5ed;overflow-wrap:anywhere;">{{ $label }} <strong style="display:block;">{{ $value }}</strong></td></tr>
            @endforeach
        </table>
        @if ($loginUrl)
            <p><a href="{{ $loginUrl }}">Sign in to your account</a></p>
        @endif
        <hr style="border:0;border-top:1px solid #dbe5ed;">
        <p><strong>Customer Support</strong><br>{{ config('support.office') }}
            @if (config('support.email'))<br>Email: {{ config('support.email') }}@endif
            @if (config('support.phone'))<br>Phone: {{ config('support.phone') }}@endif
        </p>
    </div>
</body>
</html>
