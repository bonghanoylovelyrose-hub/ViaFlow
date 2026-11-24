@props([
    'status',
])

@php
    $normalizedStatus = trim((string) $status);
    $badgeClass = match ($normalizedStatus) {
        'Paid', 'Verified', 'Approved', 'Active', 'Completed', 'Encoded', 'Resolved' => 'badge-soft-success',
        'Overdue', 'Rejected', 'Needs Revisit', 'Disconnected' => 'badge-soft-danger',
        'Pending', 'Pending Review', 'Pending Verification', 'Unpaid', 'Paid with Balance', 'Ongoing', 'For Review', 'For Reconnection' => 'badge-soft-warning',
        default => 'badge-soft-primary',
    };
@endphp

<span {{ $attributes->class(['badge', 'status-badge', $badgeClass]) }}>
    {{ $normalizedStatus !== '' ? $normalizedStatus : 'N/A' }}
</span>
