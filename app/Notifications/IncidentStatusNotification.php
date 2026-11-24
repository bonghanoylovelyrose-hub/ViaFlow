<?php

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IncidentStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Incident $incident,
        private readonly string $title,
        private readonly string $message,
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'incident_id' => $this->incident->id,
            'category' => $this->incident->category,
            'status' => $this->incident->status,
            'url' => route('incidents.index'),
        ];
    }
}
