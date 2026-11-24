@if ($completed)
    <span class="badge bg-success">Completed</span>
    @if ($completedAt)
        <span class="consumer-installation-date">Completed Date: {{ $completedAt->format('M d, Y') }}</span>
    @endif
@elseif ($scheduledDate)
    <span class="badge bg-primary">Pending Installation</span>
    <span class="consumer-installation-date">Scheduled Date: {{ $scheduledDate->format('M d, Y') }}</span>
    @if ($hasReschedule ?? false)
        <span class="consumer-installation-date">Reschedule Requested</span>
    @elseif ($completionUrl)
        <form method="POST" action="{{ $completionUrl }}" class="installation-completion-form" data-consumer-name="{{ $consumer->full_name }}" data-account-number="{{ $consumer->account_number ?: 'Not assigned' }}" data-scheduled-date="{{ $scheduledDate->format('M d, Y') }}" data-installation-status="Pending Installation">
            @csrf
            @if ($isLoan ?? false)
                @method('PATCH')
                <input type="hidden" name="installation_status" value="Completed">
                <input type="hidden" name="physical_installation_confirmed" value="">
            @endif
            <button type="button" class="btn btn-sm btn-outline-success" data-installation-complete-trigger>Mark Complete</button>
        </form>
    @endif
@else
    <span class="badge bg-secondary">Not Scheduled</span>
@endif
