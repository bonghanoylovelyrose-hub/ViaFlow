<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Payment $payment,
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
            'payment_id' => $this->payment->id,
            'reference_number' => $this->payment->reference_number,
            'receipt_number' => $this->payment->receipt_number,
            'status' => $this->payment->status,
            'url' => $this->payment->status === 'Verified'
                ? route('payments.receipt', $this->payment)
                : route('payments.index'),
        ];
    }
}
