<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\Loan;
use App\Models\LoanAgreement;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class LoanInstallationEmailService
{
    public function __construct(
        private readonly LoanAgreementService $agreements,
        private readonly ConsumerEmailUrl $links,
        private readonly InstallationRescheduleService $reschedules,
    ) {}

    public function recordPending(Loan $loan, Consumer $consumer): void
    {
        ConsumerNotificationLog::query()->firstOrCreate([
            'consumer_id' => $consumer->id,
            'event' => $loan->initialInstallationEmailEvent(),
        ], [
            'channel' => 'email',
            'recipient' => $consumer->email,
            'status' => ConsumerNotificationLog::STATUS_PENDING,
        ]);
    }

    public function send(Loan $loan): string
    {
        // Scheduling commits first. Serialize delivery and retries for this loan
        // while keeping mail failures separate from the saved schedule.
        return DB::transaction(function () use ($loan): string {
            $scheduled = Loan::query()->with('consumer.user')->lockForUpdate()->findOrFail($loan->id);
            $consumer = $scheduled->consumer;
            $schedule = $scheduled->installationSchedule();
            $event = $scheduled->initialInstallationEmailEvent();

            abort_unless($consumer && $schedule
                && ConsumerNotificationLog::query()->where('consumer_id', $consumer->id)->where('event', $event)->exists(), 404);

            $recipient = trim((string) $consumer->email);

            return ConsumerNotificationLog::sendOnce($consumer->id, $event, $recipient, function () use ($scheduled, $schedule, $consumer, $recipient): void {
                if ($recipient === '') {
                    throw new RuntimeException('No registered consumer email address.');
                }

                $agreement = $this->agreementFor($scheduled);
                $canReschedule = $scheduled->installation_status !== 'Completed' && $scheduled->isApprovedForInstallation()
                    && $this->reschedules->usage($consumer)['available'];
                $sent = Mail::send('emails.consumer.loan-installation-scheduled', [
                    'consumer' => $consumer,
                    'loan' => $scheduled,
                    'installationDate' => $schedule->format('F j, Y'),
                    'installationTime' => $schedule->format('g:i A'),
                    'rescheduleUrl' => $canReschedule ? $this->links->forLoanInstallationReschedule($scheduled->id) : null,
                    'loanPortalUrl' => $this->links->tryTo(route('loans.index', absolute: false)) ?? route('loans.index'),
                    'hasAgreementAttachment' => $agreement !== null,
                ], function (Message $mail) use ($recipient, $agreement): void {
                    $mail->to($recipient)->subject(config('app.name', 'ViaFlow System').' Loan Installation Scheduled');
                    if ($agreement) {
                        $mail->attachData($agreement->pdfBytes(), $agreement->filename(), ['mime' => 'application/pdf']);
                    }
                });

                if ($sent === null) {
                    throw new RuntimeException('The mailer did not submit the installation confirmation.');
                }

                if ($agreement) {
                    LoanAgreement::query()->whereKey($agreement->id)->whereIn('email_status', [LoanAgreement::PENDING, LoanAgreement::FAILED])
                        ->update(['email_status' => LoanAgreement::SENT, 'email_recipient' => $recipient, 'email_sent_at' => now()]);
                }
            });
        });
    }

    private function agreementFor(Loan $loan): ?LoanAgreement
    {
        if ($loan->applicationStatusCategory() !== 'Approved') {
            return null;
        }

        return $loan->agreement()->first() ?? ($loan->terms_agreed_at ? $this->agreements->finalize($loan) : null);
    }
}
