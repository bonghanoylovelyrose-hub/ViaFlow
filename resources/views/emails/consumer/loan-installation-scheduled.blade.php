<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Loan Installation Scheduled</title>
@include('emails.responsive-styles')
</head>
<body style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-family: Arial, sans-serif; color: #1f2933; line-height: 1.6; margin: 0; padding: 24px; background: #f5f7fa;">
    <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;width:100%;max-width:600px; margin: 0 auto; background: #ffffff; border: 1px solid #dbe6ee; border-radius: 12px; padding: 32px;">
        <h1 style="margin-top: 0; font-size: 24px; color: #102a43;">Your Loan Installation Has Been Scheduled</h1>

        <p>@if ($consumer->status === 'Active')Your water service account is active. @endif Your installation for your water service loan (<strong>{{ $loan->purpose ?: 'Materials & Meter Loan' }}</strong>) has been scheduled.</p>
        <p>
            <strong>Consumer Name:</strong> {{ $consumer->full_name }}<br>
            <strong>Account Number:</strong> {{ $consumer->account_number }}<br>
            <strong>New Assigned Meter Number:</strong> {{ $consumer->meter_number }}<br>
            <strong>Username:</strong> {{ $consumer->user?->username ?? $consumer->meter_number }}<br>
            <strong>Registered Email Address:</strong> {{ $consumer->email ?: $consumer->user?->email }}
        </p>
        @if ($hasAgreementAttachment ?? false)
            <p>Your finalized Loan Agreement and installment schedule are attached as a PDF.</p>
        @endif

        <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;background-color: #f0f7f9; border: 1px solid #cce4ec; border-radius: 8px; padding: 20px; margin: 24px 0;">
            <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 8px; font-size: 16px; color: #102a43;">
                <strong>Scheduled Date:</strong> {{ $installationDate }}
            </p>
            @if (!empty($installationTime))
                <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 8px; font-size: 15px; color: #102a43;">
                    <strong>Scheduled Time:</strong> {{ $installationTime }} (Philippine Time)
                </p>
            @endif
            @if (!empty($loan->installation_notes))
                <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 12px; font-size: 14px; color: #52606d;">
                    <strong>Admin Notes:</strong> {{ $loan->installation_notes }}
                </p>
            @endif
            <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 16px; font-size: 13px; color: #52606d;">
                Please ensure that you or an authorized representative is present at your registered service address.
                @if ($rescheduleUrl) If you are unavailable, you can submit a request to reschedule. @endif
            </p>
            @if ($rescheduleUrl)
            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td align="center" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;word-wrap:break-word;overflow-wrap:break-word;border-radius: 6px; background-color: #0f6e8c;">
                        <a href="{{ $rescheduleUrl }}" target="_blank" rel="noopener" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;display: inline-block; padding: 10px 22px; font-family: Arial, sans-serif; font-size: 14px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 6px; line-height: 1.2;">Request for Reschedule</a>
                    </td>
                </tr>
            </table>
            @endif
        </div>

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin-bottom: 8px;"><strong>Loan Details:</strong></p>
        <table style="width: 100%; max-width: 600px; table-layout: fixed; border-collapse: collapse; margin-bottom: 20px;">
            <tr>
                <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;padding:8px 0;border-bottom:1px solid #dbe6ee;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;"><span class="email-detail-label" style="display:block;">Total Installation Cost</span><span class="email-detail-value" style="display:block;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-all;"><strong>P {{ number_format((float) ($loan->installation_cost_total ?? $loan->amount), 2) }}</strong></span></td>
            </tr>
            <tr>
                <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;padding:8px 0;border-bottom:1px solid #dbe6ee;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;"><span class="email-detail-label" style="display:block;">Term</span><span class="email-detail-value" style="display:block;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-all;"><strong>{{ $loan->term_months }} months</strong></span></td>
            </tr>
            <tr>
                <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;padding:8px 0;border-bottom:1px solid #dbe6ee;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;"><span class="email-detail-label" style="display:block;">Monthly Installment</span><span class="email-detail-value" style="display:block;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-all;"><strong>P {{ number_format((float) $loan->monthly_installment, 2) }}</strong></span></td>
            </tr>
        </table>

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-size: 13px; color: #6a7d86; word-break: break-all; margin: 0 0 20px;">
            <a href="{{ $loanPortalUrl }}" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;color: #0f6e8c;">View your loan in the ViaFlow portal</a>
        </p>

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin-bottom: 0;">Thank you.</p>
    </div>
</body>
</html>
