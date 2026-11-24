<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f8; margin: 0; padding: 0; }
        .container { max-width: 560px; margin: 2rem auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #0f6e8c, #dc2626); padding: 2rem; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 1.5rem; }
        .body { padding: 2rem; }
        .body p { color: #374151; line-height: 1.6; }
        .requirements { color: #374151; line-height: 1.6; margin: 0.5rem 0 1rem 1.25rem; padding: 0; }
        .requirements li { margin-bottom: 0.35rem; }
        .fallback { font-size: 0.85rem; color: #6b7280; word-break: break-all; }
    </style>
@include('emails.responsive-styles')
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Connection Request Update</h1>
        </div>
        <div class="body">
            <p>Dear {{ $consumer->full_name }},</p>
            <p><strong>Application Reference:</strong> {{ $consumer->application_reference }}</p>
            <p>After careful review, we regret to inform you that your water connection request has been <strong>rejected</strong>.</p>
            @if ($reason)
                <p><strong>Reason:</strong> {{ $reason }}</p>
            @endif
            @if (! empty($correctionLabels))
                <p><strong>Requirements to correct:</strong></p>
                <ul class="requirements">
                    @foreach ($correctionLabels as $label)
                        <li>{{ $label }}</li>
                    @endforeach
                </ul>
            @endif

            <p>You can correct only the requirements listed above. All other information and valid uploaded documents will be kept unchanged.</p>

            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 24px auto;">
                <tr>
                    <td align="center" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;word-wrap:break-word;overflow-wrap:break-word;border-radius: 8px; background-color: #0f6e8c;">
                        <a href="{{ $resubmitUrl }}" target="_blank" rel="noopener" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;display: inline-block; padding: 14px 28px; font-family: 'Segoe UI', Arial, sans-serif; font-size: 16px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 8px;">Resubmit Requirements</a>
                    </td>
                </tr>
            </table>

            <p class="fallback">
                If the button does not work, copy and paste this link into your browser:<br>
                <a href="{{ $resubmitUrl }}">{{ $resubmitUrl }}</a>
            </p>

            @if ($linkExpiresAt)
                <p class="fallback">For your security, this link expires on {{ $linkExpiresAt->format('F d, Y g:i A') }}.</p>
            @endif

            <p>Once you resubmit, your application status returns to <strong>Pending Verification</strong> and an administrator will review it again.</p>
            <p>Thank you,<br>{{ config('app.name', 'ViaFlow System') }}</p>
        </div>
    </div>
</body>
</html>
