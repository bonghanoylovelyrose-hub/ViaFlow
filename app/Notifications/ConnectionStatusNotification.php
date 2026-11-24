<?php

namespace App\Notifications;

use App\Models\Consumer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ConnectionStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Consumer $consumer,
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
            'consumer_id' => $this->consumer->id,
            'status' => $this->consumer->status,
            'url' => route('connection.status'),
        ];
    }
}
