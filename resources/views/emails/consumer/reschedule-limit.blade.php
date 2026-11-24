@php($assignmentNumber = $rescheduleRequest->assignment_number ?? $rescheduleRequest->consumer->installationRescheduleRequests()->whereNotNull('assigned_at')->count())
<p style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;font-weight:bold;">{{ \App\Services\InstallationRescheduleService::REMINDER }}</p>
<p>{{ $assignmentNumber }} of {{ \App\Services\InstallationRescheduleService::LIMIT }} used.</p>
@if ($assignmentNumber >= \App\Services\InstallationRescheduleService::LIMIT)
    <div style="box-sizing:border-box;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;border:2px solid #b91c1c;background:#fef2f2;color:#991b1b;padding:18px;margin:20px 0;font-size:16px;font-weight:bold;">
        {{ \App\Services\InstallationRescheduleService::FINAL_WARNING }}
    </div>
@endif
