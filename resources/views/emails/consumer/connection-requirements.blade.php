<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Connection Application Requirements</title>
    @include('emails.responsive-styles')
</head>
<body style="margin:0;padding:24px;background:#eef5ff;font-family:Arial,sans-serif;color:#102a43">
<div style="max-width:560px;margin:auto;background:#ffffff;border:1px solid #d7e7f7;border-radius:12px;padding:24px">
    <h1>Submit your missing connection requirements</h1>
    <p>Hello {{ $consumer->full_name }},</p>
    <p>The water connection application you submitted in person is pending requirements. Your application reference number is <strong>{{ $consumer->application_reference }}</strong>.</p>
    <p><strong>Missing requirements</strong></p>
    <ul>
        @if ($missing['validId'])
            <li>Valid ID (JPG, JPEG, PNG, or PDF)</li>
        @endif
        @if ($missing['membershipDocument'])
            <li>VVTHOA membership document photos (housing loan contract or proof of residency, in JPG, JPEG, or PNG)</li>
        @endif
    </ul>
    <p>Open the upload form, attach the documents listed above, and select <strong>Submit Requirements</strong>. Once submitted, your application will move to Pending Verification for the administrator to review.</p>
    <p><a href="{{ $url }}" style="display:inline-block;padding:12px 18px;background:#0754c8;color:#ffffff;border-radius:8px;text-decoration:none">Upload Requirements</a></p>
    <p>This link expires in 30 days. Contact the VVWD office for a new link if needed.</p>
</div>
</body>
</html>
