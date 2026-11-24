<?php

namespace App\Notifications;

use App\Models\Consumer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ConnectionApplicationResubmitted extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Consumer $consumer,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $applicant = $this->consumer->full_name
            ?: 'An applicant';

        return [
            'title' => 'Connection Application Resubmitted',
            'message' => "{$applicant} corrected and resubmitted water connection application {$this->consumer->application_reference}. It is pending your re-verification.",
            'consumer_id' => $this->consumer->id,
            'application_reference' => $this->consumer->application_reference,
            'url' => route('consumers.index', ['search' => $this->consumer->application_reference]),
        ];
    }
}
