<?php

namespace App\Notifications;

use App\Models\Consumer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewConnectionRequest extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Consumer $consumer,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $classification = $this->consumer->membership_type ?: $this->consumer->requested_membership_type;
        $applicationLabel = $classification === 'Member'
            ? 'Existing VVTHOA member - membership verification pending'
            : 'Non-Member application';

        return [
            'title' => 'New Connection Request',
            'message' => "{$this->consumer->full_name} has submitted water connection application {$this->consumer->application_reference} ({$applicationLabel}).",
            'consumer_id' => $this->consumer->id,
            'application_reference' => $this->consumer->application_reference,
            'membership_type' => $this->consumer->membership_type,
            'requested_membership_type' => $this->consumer->requested_membership_type,
            'membership_verification_status' => $this->consumer->membership_verification_status,
            'url' => route('consumers.index', ['search' => $this->consumer->application_reference]),
        ];
    }
}
