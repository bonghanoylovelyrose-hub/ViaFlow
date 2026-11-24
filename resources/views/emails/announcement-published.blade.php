<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8">@include('emails.responsive-styles')</head>
<body style="margin:0;padding:12px;background:#f4f8fb;">
<div style="font-family:Arial,sans-serif;color:#243746;line-height:1.6;max-width:640px;margin:auto">
    <div style="background:#087b91;color:#fff;padding:22px 28px;border-radius:12px 12px 0 0">
        <strong style="font-size:18px">ViaFlow Service Announcement</strong>
    </div>
    <div style="border:1px solid #dce7ec;border-top:0;padding:28px;border-radius:0 0 12px 12px">
        <h1 style="font-size:22px;margin:0 0 16px">{{ $announcement->title }}</h1>
        <p style="font-size:13px;color:#526675">{{ $announcement->category }} · {{ $announcement->priority }}</p>
        <div style="white-space:pre-line">{{ $announcement->body }}</div>
        @if ($announcement->affected_area || $announcement->service_starts_at || $announcement->service_restores_at)
            <div style="padding:12px;margin-top:16px;background:#f1f8f9;border:1px solid #dce7ec;word-wrap:break-word;overflow-wrap:anywhere;">
                @if ($announcement->affected_area)<div><strong>Affected area:</strong> {{ $announcement->affected_area }}</div>@endif
                @if ($announcement->service_starts_at)<div><strong>Interruption starts:</strong> {{ $announcement->service_starts_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</div>@endif
                @if ($announcement->service_restores_at)<div><strong>Expected restoration:</strong> {{ $announcement->service_restores_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</div>@endif
            </div>
        @endif
        <p style="margin:24px 0"><a href="{{ $announcementUrl }}" style="background:#087b91;color:#fff;text-decoration:none;padding:11px 18px;border-radius:7px">View Announcement</a></p>
        @if ($announcement->photo_path)
            <p style="color:#657784;font-size:13px">The announcement attachment is included with this email.</p>
        @endif
        <p style="color:#657784;font-size:12px;margin-top:28px">This message was sent to you individually based on the announcement audience.</p>
    </div>
</div>
</body>
</html>
