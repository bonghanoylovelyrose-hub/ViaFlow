<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $subject }}</title>
    @include('emails.responsive-styles')
</head>
<body style="margin:0;padding:24px 12px;background:#fff1f2;color:#451a1a;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border:1px solid #fecaca;border-radius:14px;">
        <tr><td style="padding:20px 24px;background:#991b1b;color:#fff;border-radius:14px 14px 0 0;">
            <h1 style="font-size:20px;margin:0;">{{ $subject }}</h1>
        </td></tr>
        <tr><td style="padding:24px;font-size:15px;line-height:1.6;">
            @php($isLoan = $notice->source === \App\Models\DisconnectionNotice::SOURCE_LOAN)
            <p style="margin-top:0;">Hello {{ $consumer->full_name }},</p>
            @if ($isLoan)
                <p>Your meter loan reached its final due date on {{ $notice->loan_due_date?->format('F d, Y') }} with a balance still unpaid.</p>
            @else
                <p>Your account has unpaid water bills past their payment deadlines. Please settle the balance below to avoid possible disconnection.</p>
            @endif
            <div style="padding:16px;background:#fef2f2;border-left:4px solid #b91c1c;color:#7f1d1d;">
                <strong>Account number:</strong> {{ $consumer->account_number ?: 'Pending assignment' }}<br>
                <strong>Meter number:</strong> {{ $consumer->meter_number ?: 'Pending assignment' }}<br>
                <strong>Disconnection reason:</strong> {{ \App\Models\DisconnectionNotice::reasonForSources(! $isLoan, $isLoan) }}<br>
                <strong>Outstanding {{ $isLoan ? 'loan' : 'water' }} balance:</strong> PHP {{ number_format($outstanding, 2) }}<br>
                @if ($isLoan)<strong>Overdue loan amount:</strong> PHP {{ number_format($outstanding, 2) }}<br>@endif
                <strong>Notice issued:</strong> {{ $notice->issued_at?->copy()->timezone('Asia/Manila')->format('F d, Y g:i A') }}<br>
                <strong>Payment deadline:</strong> {{ $deadline?->copy()->timezone('Asia/Manila')->format('F d, Y g:i A') }} Philippine time
            </div>
            <p>Please settle the full outstanding balance and have the payment verified by the deadline. The payment window is three days.</p>
            <p>Your service remains connected unless an administrator confirms physical disconnection.</p>
            <p style="margin-bottom:0;color:#694747;">{{ config('app.name', 'ViaFlow System') }} &middot; Via Vida Townhomes Homeowners Association</p>
        </td></tr>
    </table>
</body>
</html>
