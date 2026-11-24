<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $subject }}</title>
@include('emails.responsive-styles')
</head>
<body style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin:0;padding:0;background:#f4f8fb;font-family:'Segoe UI',Arial,sans-serif;">
<div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;padding:24px 12px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;border:1px solid #dbe6ee;">
        <tr>
            <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;word-wrap:break-word;overflow-wrap:break-word;padding:24px 28px;border-bottom:3px solid #0f6e8c;">
                <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-size:18px;font-weight:700;color:#153746;">{{ config('app.name', 'ViaFlow System') }}</div>
                <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-size:13px;color:#6a7d86;">Via Vida Townhomes Homeowners Association</div>
            </td>
        </tr>

        <tr>
            <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;word-wrap:break-word;overflow-wrap:break-word;padding:28px;">
                <h1 style="margin:0 0 16px;font-size:20px;color:#153746;">{{ $subject }}</h1>

                <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin:0 0 16px;font-size:14px;line-height:1.7;color:#33475b;">
                    {!! $noticeBody !!}
                </p>

                <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin:0;font-size:13px;color:#6a7d86;">
                    This message was sent to {{ $consumer->email }} regarding account
                    {{ $consumer->account_number ?? 'pending assignment' }}.
                </p>
            </td>
        </tr>

        <tr>
            <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;word-wrap:break-word;overflow-wrap:break-word;padding:18px 28px;background:#f8fbfd;border-top:1px solid #dbe6ee;border-radius:0 0 14px 14px;">
                <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-size:12px;color:#8aa0ad;">
                    {{ config('app.name', 'ViaFlow System') }} &mdash; this is an automated message. Please do not reply.
                </div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>