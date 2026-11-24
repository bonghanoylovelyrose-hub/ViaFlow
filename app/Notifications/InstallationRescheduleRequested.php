<?php

namespace App\Notifications;

use App\Models\InstallationRescheduleRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InstallationRescheduleRequested extends Notification
{
    use Queueable;

    public function __construct(
        private readonly InstallationRescheduleRequest $rescheduleRequest,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $consumer = $this->rescheduleRequest->consumer;
        $name = $consumer?->full_name ?? 'A consumer';
        $typeLabel = $this->rescheduleRequest->isLoan() ? 'Loan Installation' : 'New Connection Installation';
        if ($this->rescheduleRequest->isReconnection()) {
            $typeLabel = 'Reconnection';
        }
        $originalSchedule = $this->rescheduleRequest->formattedOriginalSchedule();

        $reference = $this->rescheduleRequest->isLoan()
            ? ($this->rescheduleRequest->loan_id ? 'Loan #'.$this->rescheduleRequest->loan_id : 'Water Service Loan')
            : ($consumer?->application_reference ?: ($consumer?->account_number ?: 'Ref #'.$consumer?->id));

        $referenceLabel = $this->rescheduleRequest->isLoan()
            ? "Loan Ref: {$reference}"
            : "Application Ref: {$reference}";

        $reason = $this->rescheduleRequest->reason;

        $message = "{$name} ({$referenceLabel}) submitted a reschedule request for {$typeLabel}. Current Installation Date: {$originalSchedule}. Reason: \"{$reason}\"";
        if ($this->rescheduleRequest->isReconnection()) {
            $message .= ' Preferred schedule: '.$this->rescheduleRequest->formattedPreferredSchedule().'.';
        }

        return [
            'title' => 'Installation Reschedule Requested',
            'message' => $message,
            'consumer_name' => $name,
            'reference' => $reference,
            'application_reference' => $consumer?->application_reference,
            'loan_reference' => $this->rescheduleRequest->loan_id ? 'Loan #'.$this->rescheduleRequest->loan_id : null,
            'current_installation_date' => $originalSchedule,
            'preferred_schedule' => $this->rescheduleRequest->formattedPreferredSchedule(),
            'reason' => $reason,
            'reschedule_request_id' => $this->rescheduleRequest->id,
            'consumer_id' => $this->rescheduleRequest->consumer_id,
            'loan_id' => $this->rescheduleRequest->loan_id,
            'reschedule_type' => $this->rescheduleRequest->reschedule_type,
            'url' => $this->rescheduleRequest->isLoan()
                ? route('loans.index', ['tab' => 'pending', 'pending' => 'reschedule'])
                : route('consumers.index', ['account_category' => 'pending', 'pending_type' => 'reschedules']),
        ];
    }
}
