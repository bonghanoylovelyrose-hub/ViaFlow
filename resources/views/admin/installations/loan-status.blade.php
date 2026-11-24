@include('admin.installations.status', [
    'isLoan' => true,
    'completed' => $loanInstallation->installation_status === 'Completed',
    'completedAt' => $loanInstallation->installationCompletionDate(),
    'scheduledDate' => $loanInstallation->installation_date,
    'hasReschedule' => $loanInstallation->has_pending_installation_reschedule,
    'completionUrl' => $consumer && $loanInstallation->installation_time && $loanInstallation->isApprovedForInstallation()
        ? route('loans.installation-status.update', $loanInstallation)
        : null,
])
