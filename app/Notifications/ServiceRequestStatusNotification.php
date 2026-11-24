<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ServiceRequestStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ServiceRequest $serviceRequest,
        private readonly string $title,
        private readonly string $message,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'service_request_id' => $this->serviceRequest->id,
            'request_type' => $this->serviceRequest->request_type,
            'status' => $this->serviceRequest->status,
            'url' => route('billing.index'),
        ];
    }
}
