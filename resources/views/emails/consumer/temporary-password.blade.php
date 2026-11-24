<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Consumer Temporary Password</title>
@include('emails.responsive-styles')
</head>
<body style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-family: Arial, sans-serif; color: #1f2933; line-height: 1.6; margin: 0; padding: 24px; background: #f5f7fa;">
    <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;width:100%;max-width:600px; margin: 0 auto; background: #ffffff; border: 1px solid #dbe6ee; border-radius: 12px; padding: 32px;">
        <h1 style="margin-top: 0; font-size: 24px;">Your temporary password</h1>

        <p>Hello {{ $consumer->full_name }},</p>
        <p>We received a request to recover the password for your Via-Vida Waterworks consumer account. A new temporary password has been generated for you.</p>

        <table style="width: 100%; max-width: 600px; table-layout: fixed; border-collapse: collapse; margin: 20px 0;">
            <tr>
                <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;padding:8px 0;border-bottom:1px solid #dbe6ee;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;"><span class="email-detail-label" style="display:block;">Username / Meter Number</span><span class="email-detail-value" style="display:block;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-all;"><strong>{{ $consumer->meter_number }}</strong></span></td>
            </tr>
            <tr>
                <td style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;padding:8px 0;border-bottom:1px solid #dbe6ee;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;"><span class="email-detail-label" style="display:block;">Temporary Password</span><span class="email-detail-value" style="display:block;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-all;"><strong>{{ $temporaryPassword }}</strong></span></td>
            </tr>
        </table>

        <p>Sign in using your <strong>meter number</strong> and the temporary password above. You will be required to change this temporary password immediately after signing in.</p>

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 12px 14px; color: #9a3412;">
            If you did not request this temporary password, contact ViaFlow immediately and do not use the password above.
        </p>

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin-bottom: 0;">Thank you.</p>
    </div>
</body>
</html>
