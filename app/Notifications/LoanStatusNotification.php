<?php

namespace App\Notifications;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LoanStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Loan $loan,
        private readonly string $title,
        private readonly string $message,
        private readonly array $extraData = [],
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
        return array_merge([
            'title' => $this->title,
            'message' => $this->message,
            'loan_id' => $this->loan->id,
            'consumer_id' => $this->loan->consumer_id,
            'account_number' => $this->loan->consumer?->account_number,
            'status' => $this->loan->status,
            'amount' => $this->loan->amount,
            'url' => route('loans.index'),
        ], $this->extraData);
    }
}
