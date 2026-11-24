<?php

namespace App\Notifications;

use App\Models\MeterReading;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MeterReadingNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly MeterReading $meterReading,
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
            'meter_reading_id' => $this->meterReading->id,
            'meter_number' => $this->meterReading->consumer->meter_number,
            'consumer_name' => $this->meterReading->consumer->full_name,
            'current_reading' => $this->meterReading->current_reading,
            'url' => route('billing.index'),
        ];
    }
}
