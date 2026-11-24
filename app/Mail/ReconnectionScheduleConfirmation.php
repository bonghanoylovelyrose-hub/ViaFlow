<?php

namespace App\Mail;

use App\Models\Consumer;
use App\Support\ReconnectionSchedule;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

class ReconnectionScheduleConfirmation extends Mailable
{
    public const SUBJECT = 'Water Service Reconnection Schedule Confirmation';

    public function __construct(
        private Consumer $consumer,
        private string $registeredEmail,
        private Carbon $scheduledAt,
        private ?array $credentials,
        private ?string $loginUrl,
        private ?string $rescheduleUrl,
        private int $rescheduleRemaining,
        private ?string $adminNotes = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::SUBJECT);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.consumer.reconnection-activated', with: [
            'consumer' => $this->consumer,
            'registeredEmail' => $this->registeredEmail,
            'scheduledDate' => ReconnectionSchedule::format($this->scheduledAt, 'F d, Y'),
            'scheduledTime' => ReconnectionSchedule::format($this->scheduledAt, 'g:i A'),
            'accountStatus' => $this->consumer->reconnection_pending ? 'Active / Pending Reconnection' : 'Active / Connected',
            'temporaryPassword' => $this->credentials['temporary_password'] ?? null,
            'passwordSetupUrl' => $this->credentials['password_setup_url'] ?? null,
            'loginUrl' => $this->loginUrl,
            'rescheduleUrl' => $this->rescheduleUrl,
            'rescheduleRemaining' => $this->rescheduleRemaining,
            'adminNotes' => $this->adminNotes,
        ]);
    }
}
