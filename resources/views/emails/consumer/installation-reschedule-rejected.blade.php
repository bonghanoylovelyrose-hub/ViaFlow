<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reschedule Request Update</title>
@include('emails.responsive-styles')
</head>
<body style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-family: Arial, sans-serif; color: #1f2933; line-height: 1.6; margin: 0; padding: 24px; background: #f5f7fa;">
    <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;width:100%;max-width:600px; margin: 0 auto; background: #ffffff; border: 1px solid #dbe6ee; border-radius: 12px; padding: 32px;">
        <h1 style="margin-top: 0; font-size: 24px; color: #102a43;">Installation Reschedule Request Update</h1>

        <p>Hello {{ $consumer->full_name }},</p>
        <p>The administrator has reviewed your request to reschedule your installation. Unfortunately, your reschedule request could not be accommodated at this time.</p>

        @php
            $isLoan = $rescheduleRequest->isLoan();
            $rescheduleUrl = $rescheduleUrl ?? ($isLoan
                ? \Illuminate\Support\Facades\URL::signedRoute('installation.reschedule.loan', ['loan' => $rescheduleRequest->loan_id])
                : \Illuminate\Support\Facades\URL::signedRoute('installation.reschedule.connection', ['consumer' => $consumer->id]));
        @endphp

        <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 20px; margin: 24px 0;">
            <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 8px; font-size: 16px; color: #92400e;">
                <strong>Original Schedule Retained:</strong> {{ $originalSchedule }}
            </p>
            @if (!empty($adminNotes))
                <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 12px; font-size: 14px; color: #78350f;">
                    <strong>Administrator Reason / Notes:</strong> {{ $adminNotes }}
                </p>
            @endif
            <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin: 0 0 16px; font-size: 13px; color: #78350f;">
                Your installation remains scheduled for the original date and time shown above. If your circumstances change or you need to submit another request, you can do so using the button below.
            </p>
            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td align="center" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;word-wrap:break-word;overflow-wrap:break-word;border-radius: 6px; background-color: #0f6e8c;">
                        <a href="{{ $rescheduleUrl }}" target="_blank" rel="noopener" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;display: inline-block; padding: 10px 22px; font-family: Arial, sans-serif; font-size: 14px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 6px; line-height: 1.2;">Request for Reschedule</a>
                    </td>
                </tr>
            </table>
        </div>

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-size: 13px; color: #6a7d86; word-break: break-all; margin: 0 0 20px;">
            You can sign in to your account at:<br>
            <a href="{{ route('login') }}" style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;color: #0f6e8c;">{{ route('login') }}</a>
        </p>

        <p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;margin-bottom: 0;">Thank you.</p>
    </div>
</body>
</html>
