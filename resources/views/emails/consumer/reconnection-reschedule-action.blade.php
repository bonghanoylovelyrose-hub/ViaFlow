@if ($rescheduleUrl)
    <a href="{{ $rescheduleUrl }}" target="_blank" rel="noopener" style="display:inline-block;padding:10px 22px;font-family:Arial,sans-serif;font-size:14px;font-weight:700;color:#0f6e8c;background-color:#ffffff;border:1px solid #0f6e8c;text-decoration:none;border-radius:6px;line-height:1.2;">Request for Reschedule</a>
@endif
<p style="font-size:13px;color:#52606d;margin:8px 0 12px;">
    <strong>Reschedule Attempts Remaining:</strong> {{ $rescheduleRemaining }}
    @if ($rescheduleRemaining === 0)
        <br>You have reached the limit of three reschedule requests for this reconnection.
    @elseif ($rescheduleUrl)
        <br>This link expires in seven days. Please keep it private.
    @endif
</p>
