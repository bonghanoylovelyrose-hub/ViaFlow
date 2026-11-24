<!DOCTYPE html>
@php
    $initialSchedule = ! isset($rescheduleRequest);
@endphp
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $initialSchedule ? 'Installation Schedule Confirmed' : 'Updated Installation Schedule' }}</title>
@include('emails.responsive-styles')
</head>
<body style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-family: Arial, sans-serif; color: #1f2933; line-height: 1.6; margin: 0; padding: 24px; background: #f5f7fa;">
    <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;width:100%;max-width:600px; margin: 0 auto; background: #ffffff; border: 1px solid #dbe6ee; border-radius: 12px; padding: 32px;">
        <h1 style="margin-top: 0; font-size: 24px; color: #102a43;">{{ $initialSchedule ? 'Your Installation Schedule Is Confirmed' : 'Your Installation Schedule Has Been Updated' }}</h1>

        <p>Hello {{ $consumer->full_name }},</p>
        <p>{{ $initialSchedule ? 'The administrator has scheduled the installation of your water connection.' : 'The administrator has assigned a new installation date and time to your account.' }}</p>
        @unless($initialSchedule)
            @include('emails.consumer.reschedule-limit')
        @endunless

        @php
            $isLoan = ! $initialSchedule && $rescheduleRequest->isLoan();
            $rescheduleUrl = $rescheduleUrl ?? ($isLoan
                ? \Illuminate\Support\Facades\URL::signedRoute('installation.reschedule.loan', ['loan' => $rescheduleRequest->loan_id])
                : \Illuminate\Support\Facades\URL::signedRoute('installation.reschedule.connection', ['consumer' => $consumer->id]));
        @endphp

        <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 20px; margin: 24px 0;">
            <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 8px; font-size: 16px; color: #166534;">
                <strong>{{ $initialSchedule ? 'Scheduled Installation Date' : 'New Installation Schedule' }}:</strong> {{ $newSchedule }}@if ($isLoan) (Philippine Time)@endif
            </p>
            @unless($initialSchedule)
                <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 8px; font-size: 14px; color: #4b5563;">
                    <strong>Previous Schedule:</strong> {{ $originalSchedule }}@if ($isLoan) (Philippine Time)@endif
                </p>
            @endunless
            @if (!empty($adminNotes))
                <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 12px; font-size: 14px; color: #1f2937;">
                    <strong>Administrator Notes:</strong> {{ $adminNotes }}
                </p>
            @endif
            <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 16px; font-size: 13px; color: #4b5563;">{{ $initialSchedule ? 'Please ensure that someone is available at the service address on the scheduled date.' : 'Please ensure that someone is available at home during the scheduled installation date and time.' }}</p>
            @if (($rescheduleRequest->assignment_number ?? 0) < \App\Services\InstallationRescheduleService::LIMIT)
            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td align="center" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;word-wrap:break-word;overflow-wrap:break-word;border-radius: 6px; background-color: #0f6e8c;">
                        <a href="{{ $rescheduleUrl }}" target="_blank" rel="noopener" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;display: inline-block; padding: 10px 22px; font-family: Arial, sans-serif; font-size: 14px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 6px; line-height: 1.2;">Request for Reschedule</a>
                    </td>
                </tr>
            </table>
            @endif
        </div>

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin-bottom: 8px;"><strong>Installation &amp; Account Details:</strong></p>
        <table style="width: 100%; max-width: 600px; table-layout: fixed; border-collapse: collapse; margin-bottom: 20px;">
            <tr>
                <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;padding:8px 0;border-bottom:1px solid #dbe6ee;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;"><span class="email-detail-label" style="display:block;">Type</span><span class="email-detail-value" style="display:block;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-all;"><strong>{{ $isLoan ? 'Loan Installation' : 'Water Connection Installation' }}</strong></span></td>
            </tr>
            @if ($consumer->account_number)
                <tr>
                    <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;padding:8px 0;border-bottom:1px solid #dbe6ee;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;"><span class="email-detail-label" style="display:block;">Account Number</span><span class="email-detail-value" style="display:block;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-all;"><strong>{{ $consumer->account_number }}</strong></span></td>
                </tr>
            @endif
            @if (!empty($meterUsername))
                <tr>
                    <td style="padding:8px 0;border-bottom:1px solid #dbe6ee;">
                        <span class="email-detail-label" style="display:block;">Assigned Meter Number (Username)</span>
                        <span class="email-detail-value" style="display:block;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-all;"><strong>{{ $meterUsername }}</strong></span>
                    </td>
                </tr>
            @endif
            @if ($consumer->application_reference)
                <tr>
                    <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;padding:8px 0;border-bottom:1px solid #dbe6ee;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;"><span class="email-detail-label" style="display:block;">Application Reference</span><span class="email-detail-value" style="display:block;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-all;"><strong>{{ $consumer->application_reference }}</strong></span></td>
                </tr>
            @endif
            @if ($consumer->service_address)
                <tr>
                    <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;padding:8px 0;border-bottom:1px solid #dbe6ee;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;"><span class="email-detail-label" style="display:block;">Service Address</span><span class="email-detail-value" style="display:block;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-all;">{{ $consumer->service_address }}</span></td>
                </tr>
            @endif
        </table>

        @if (!empty($meterUsername) && !empty($passwordSetupUrl))
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:18px;margin:20px 0;">
                <p style="margin:0 0 10px;"><strong>Secure Consumer Portal Access</strong></p>
                <p style="margin:0 0 12px;">Use your assigned meter number as your username. Set a private password using the single-use link below. The link expires after {{ (int) config('auth.passwords.users.expire', 60) }} minutes and can only be used once.</p>
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr><td align="center" style="border-radius:6px;background:#0f6e8c;">
                        <a href="{{ $passwordSetupUrl }}" target="_blank" rel="noopener" style="display:inline-block;padding:10px 22px;font-family:Arial,sans-serif;font-size:14px;font-weight:700;color:#fff;text-decoration:none;border-radius:6px;line-height:1.2;">Set Up Your Password</a>
                    </td></tr>
                </table>
            </div>
        @endif

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-size: 13px; color: #6a7d86; word-break: break-all; margin: 0 0 20px;">
            You can log into your account anytime to view your installation status:<br>
            <a href="{{ $loginUrl ?? route('login') }}" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;color: #0f6e8c;">{{ $loginUrl ?? route('login') }}</a>
        </p>

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin-bottom: 0;">Thank you.</p>
    </div>
</body>
</html>
