<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\DisconnectionNotice;
use App\Models\Loan;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\ConsumerNoticeNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Customer-facing notices for overdue bills and disconnection.
 *
 * Every event carries its own key so the same notice is never sent twice for
 * the same overdue event.
 */
class DisconnectionNoticeService
{
    public const LOAN_NOTICE_SUBJECT = 'LOAN OVERDUE — DISCONNECTION NOTICE';

    public const WATER_NOTICE_SUBJECT = 'Disconnection Notice - Immediate Payment Required';

    public function __construct(
        private readonly ConsumerBalanceService $balances,
        private readonly LoanRepaymentAllocationService $loanPayments,
    ) {
    }

    /**
     * The notice currently governing the account, if any.
     */
    public function activeNoticeFor(int $consumerId, ?string $source = null): ?DisconnectionNotice
    {
        return DisconnectionNotice::query()
            ->where('consumer_id', $consumerId)
            ->active()
            ->when($source !== null, fn ($query) => $query->where('source', $source))
            ->latest('deadline_at')
            ->latest('issued_at')
            ->latest('id')
            ->first();
    }

    /**
     * Raises one notice for the current delinquent cycle.
     *
     * The unique consumer/bill pair plus the active-notice lookup make this
     * idempotent: repeated sweeps and page reconciliations cannot create a
     * second notice for the same run of unpaid bills.
     */
    public function raiseNotice(Consumer $consumer, int $monthsUnpaid, Bill $trigger): ?DisconnectionNotice
    {
        return DB::transaction(function () use ($consumer, $monthsUnpaid, $trigger): ?DisconnectionNotice {
            $lockedConsumer = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);

            if ($lockedConsumer->reconnection_pending || ! in_array($lockedConsumer->status, ['Active', 'For Review'], true)) {
                return null;
            }

            return $this->createNotice($lockedConsumer, $monthsUnpaid, $trigger);
        });
    }

    private function createNotice(Consumer $consumer, int $monthsUnpaid, Bill $trigger): ?DisconnectionNotice
    {
        $active = $this->activeNoticeFor($consumer->id, DisconnectionNotice::SOURCE_WATER);

        if ($active) {
            return $active;
        }

        $outstanding = $this->balances->totalOutstandingFor($consumer->id);

        if ($trigger->consumer_id !== $consumer->id || $trigger->bill_type !== 'Water' || $outstanding <= 0) {
            return null;
        }

        $issuedAt = now();
        $notice = DisconnectionNotice::query()->firstOrCreate(
            [
                'consumer_id' => $consumer->id,
                'bill_id' => $trigger->id,
            ],
            [
                'source' => DisconnectionNotice::SOURCE_WATER,
                'status' => DisconnectionNotice::STATUS_ACTIVE,
                'issued_at' => $issuedAt,
                'deadline_at' => $issuedAt->copy()->addDays(ConsumerBalanceService::PAYMENT_GRACE_DAYS),
                'outstanding_amount' => $outstanding,
                'months_unpaid' => $monthsUnpaid,
                'disconnection_reason' => DisconnectionNotice::reasonForSources(water: true, loan: false),
            ],
        );

        return $notice->isActive() ? $notice : null;
    }

    /** Reconcile the loan notices while the consumer row is locked. */
    public function reconcileLoanNotices(Consumer $consumer): array
    {
        $issued = false;
        $settled = false;
        foreach ($consumer->disconnectionNotices()->active()->where('source', DisconnectionNotice::SOURCE_LOAN)->with('loan')->get() as $notice) {
            $balance = $this->loanNoticeBalance($notice);
            $notice->fill(['loan_balance_due' => $balance]);
            if ($balance <= 0) {
                $this->settleNotice($notice);
                $settled = true;
            } elseif ($notice->isDirty('loan_balance_due')) {
                $notice->save();
            }
        }

        if ($consumer->reconnection_pending || ! in_array($consumer->status, ['Active', 'For Review'], true)) {
            return [$issued, $settled];
        }

        $loans = $consumer->loans()->withExpiredUnpaidSchedule()->with('installments')->get();
        foreach ($loans as $loan) {
            $term = (int) $loan->term_months;
            $final = $loan->installments->firstWhere('installment_number', $term);
            if (! $final?->due_date || $loan->installments->count() !== $term || $loan->installments->contains(fn ($row) => ! $row->due_date || $row->due_date->gt($final->due_date))
                || round((float) $loan->installments->sum('required_amount'), 2) !== round((float) $loan->amount, 2)) {
                continue;
            }

            $balance = $this->loanPayments->remainingBalance($loan);
            if ($balance <= 0) {
                continue;
            }

            $issuedAt = now();
            $notice = DisconnectionNotice::query()->firstOrCreate(['loan_id' => $loan->id], [
                'consumer_id' => $consumer->id,
                'source' => DisconnectionNotice::SOURCE_LOAN,
                'status' => DisconnectionNotice::STATUS_ACTIVE,
                'issued_at' => $issuedAt,
                'deadline_at' => $issuedAt->copy()->addDays(ConsumerBalanceService::PAYMENT_GRACE_DAYS),
                'loan_due_date' => $final->due_date,
                'outstanding_amount' => $balance,
                'loan_balance_due' => $balance,
            ]);
            $issued = $issued || $notice->wasRecentlyCreated;
        }

        return [$issued, $settled];
    }

    private function loanNoticeBalance(DisconnectionNotice $notice): float
    {
        return $notice->loan ? $this->loanPayments->remainingBalance($notice->loan) : 0;
    }

    public function outstandingForReconnection(Consumer $consumer): float
    {
        $water = $this->balances->recordedOutstandingFor($consumer->id, includeDemo: false);
        $loan = Loan::query()->whereIn('id', $this->disconnectedLoanIds($consumer))->get()
            ->sum(fn (Loan $loan) => $this->loanPayments->remainingBalance($loan));

        return round($water + $loan, 2);
    }

    public function disconnectedLoanIds(Consumer $consumer): array
    {
        if (! $consumer->disconnected_at) {
            return [];
        }

        return $consumer->disconnectionNotices()->where('source', DisconnectionNotice::SOURCE_LOAN)
            ->where('disconnected_at', $consumer->disconnected_at)->whereNotNull('loan_id')
            ->whereIn('status', [DisconnectionNotice::STATUS_DISCONNECTED, DisconnectionNotice::STATUS_INCLUDED_IN_DISCONNECTION])
            ->pluck('loan_id')->all();
    }

    /**
     * Retires the active notice because the balance was settled before an
     * actual disconnection. The row itself is kept as history.
     */
    public function settleNotice(DisconnectionNotice $notice): void
    {
        if (! $notice->isActive()) {
            return;
        }

        $notice->forceFill([
            'status' => DisconnectionNotice::STATUS_SETTLED,
            'settled_at' => now(),
        ])->save();
    }

    /**
     * Records the administrator-confirmed physical disconnection.
     *
     * The account only becomes Disconnected here - never merely because a
     * notice was raised. The notice row remains as the audit trail.
     */
    public function confirmDisconnection(Consumer $consumer, User $admin, ?string $reason = null): DisconnectionNotice
    {
        abort_unless($admin->isAdmin(), 403);

        $notice = DB::transaction(function () use ($consumer, $admin, $reason): DisconnectionNotice {
            $lockedConsumer = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);

            return $this->confirmLockedDisconnection($lockedConsumer, $admin, $reason);
        });

        $consumer->refresh();

        if ($notice->status === DisconnectionNotice::STATUS_SETTLED) {
            throw ValidationException::withMessages([
                'disconnection' => 'The balance is already fully settled, so the notice has been closed instead of disconnecting the account.',
            ]);
        }

        return $notice;
    }

    private function confirmLockedDisconnection(Consumer $consumer, User $admin, ?string $reason): DisconnectionNotice
    {
        if ($consumer->reconnection_pending) {
            throw ValidationException::withMessages(['disconnection' => 'Water service is still awaiting reconnection.']);
        }

        $active = $consumer->disconnectionNotices()->active()->with('loan')->latest('deadline_at')->latest('id')->get();
        $notice = $active->first();

        if (! $notice) {
            throw ValidationException::withMessages([
                'disconnection' => 'This account has no active disconnection notice to confirm.',
            ]);
        }

        $waterBalance = $this->balances->recordedOutstandingFor($consumer->id, includeDemo: false);
        $unpaid = $active->filter(function (DisconnectionNotice $item) use ($waterBalance) {
            $balance = $item->source === DisconnectionNotice::SOURCE_LOAN ? $this->loanNoticeBalance($item) : $waterBalance;
            if ($balance <= 0) {
                $this->settleNotice($item);
            }

            return $balance > 0;
        });
        if ($unpaid->isEmpty()) {

            Bill::query()
                ->where('consumer_id', $consumer->id)
                ->where('bill_type', 'Water')
                ->where('notice_level', ConsumerBalanceService::DISCONNECTION_NOTICE)
                ->update(['notice_level' => null]);

            return $notice->fresh();
        }

        $notice = $unpaid->first();

        if (! $notice->paymentWindowHasElapsed()) {
            throw ValidationException::withMessages([
                'disconnection' => 'The consumer still has until '
                    .$notice->deadline_at?->format('F d, Y g:i A')
                    .' to settle the full outstanding balance.',
            ]);
        }

        if (! in_array($consumer->status, ['Active', 'For Review'], true)) {
            throw ValidationException::withMessages([
                'disconnection' => 'Only a verified active account can be confirmed for disconnection.',
            ]);
        }

        $hasWater = $unpaid->contains('source', DisconnectionNotice::SOURCE_WATER);
        $hasLoan = $unpaid->contains('source', DisconnectionNotice::SOURCE_LOAN);
        $outstanding = round(($hasWater ? $waterBalance : 0) + $unpaid->where('source', DisconnectionNotice::SOURCE_LOAN)
            ->sum(fn (DisconnectionNotice $item) => $this->loanNoticeBalance($item)), 2);

        DB::transaction(function () use ($consumer, $notice, $unpaid, $admin, $reason, $outstanding, $hasWater, $hasLoan): void {
            $disconnectedAt = now();
            $notice->forceFill([
                'status' => DisconnectionNotice::STATUS_DISCONNECTED,
                'disconnected_at' => $disconnectedAt,
                'confirmed_by' => $admin->id,
                'outstanding_at_disconnection' => $outstanding,
                'disconnection_reason' => filled($reason) ? trim($reason) : ($notice->notes ?: DisconnectionNotice::reasonForSources($hasWater, $hasLoan)),
            ])->save();

            foreach ($unpaid->reject(fn (DisconnectionNotice $item) => $item->is($notice)) as $included) {
                $included->forceFill([
                    'status' => DisconnectionNotice::STATUS_INCLUDED_IN_DISCONNECTION,
                    'disconnected_at' => $disconnectedAt,
                    'confirmed_by' => $admin->id,
                ])->save();
            }

            $consumer->forceFill([
                'status' => 'Disconnected',
                'disconnected_at' => $disconnectedAt,
                'full_payment_verified_at' => null,
                'reconnection_fee' => null,
                'reconnection_fee_bill_id' => null,
            ])->save();

            // The active notice is over; the notices row still records it.
            Bill::query()
                ->where('consumer_id', $consumer->id)
                ->where('bill_type', 'Water')
                ->where('notice_level', ConsumerBalanceService::DISCONNECTION_NOTICE)
                ->update(['notice_level' => null]);
        });

        return $notice->fresh();
    }

    /**
     * Warns the consumer as soon as a bill passes its due date, before the
     * grace period ends, so they can avoid disconnection.
     */
    public function sendOverdueReminder(Consumer $consumer, Bill $bill): bool
    {
        if (! $bill->hasBeenIssued() || $bill->is_demo || $bill->isDemoDisconnectionBill() || blank($consumer->email)) {
            return false;
        }

        // Nothing to say before the bill is actually due.
        if (! $bill->due_date || today()->lessThanOrEqualTo($bill->due_date->copy()->startOfDay())) {
            return false;
        }

        $outstanding = $this->balances->totalOutstandingFor($consumer->id);

        if ($outstanding <= 0) {
            return false;
        }

        $event = 'overdue_bill_'.$bill->id;
        $deadline = $bill->due_date?->copy()->addDays(ConsumerBalanceService::PAYMENT_GRACE_DAYS);

        return $this->deliver(
            $consumer,
            $event,
            'Overdue Water Bill - Action Required',
            $this->overdueMessage($consumer, $outstanding, $deadline),
        );
    }

    /**
     * Formal notice once the account qualifies for disconnection.
     */
    public function sendDisconnectionNotice(Consumer $consumer, ?DisconnectionNotice $notice = null): bool
    {
        $deadline = DB::transaction(function () use ($consumer, $notice) {
            $account = Consumer::query()->whereKey($consumer->id)->lockForUpdate()->first();
            if (! $account) {
                return null;
            }
            $locked = $consumer->disconnectionNotices()->active()->where('source', DisconnectionNotice::SOURCE_WATER)
                ->when($notice, fn ($query) => $query->whereKey($notice->id))
                ->latest('deadline_at')->latest('id')->lockForUpdate()->first();
            if (! $locked || $locked->bill?->isDemoDisconnectionBill() || $locked->bill?->is_demo) {
                return null;
            }
            $outstanding = $this->balances->totalOutstandingFor($consumer->id);
            if ($outstanding <= 0) {
                return null;
            }

            // A delayed first delivery still gives the full three days to pay.
            // A successful retry of the same operation does not move the date.
            $deadline = now()->addDays(ConsumerBalanceService::PAYMENT_GRACE_DAYS)->max($locked->deadline_at);
            $event = 'disconnection_notice_'.($locked->bill_id ?? 'notice_'.$locked->id);
            if (! $this->sendWarning($account, $locked, $event, self::WATER_NOTICE_SUBJECT, $outstanding, $deadline)) {
                return null;
            }
            $locked->update(['deadline_at' => $deadline]);

            return $deadline;
        });

        if ($deadline) {
            try {
                $consumer->user?->notify(new ConsumerNoticeNotification(self::WATER_NOTICE_SUBJECT,
                    'Your overdue water balance must be paid by '.$deadline->copy()->timezone('Asia/Manila')->format('F d, Y g:i A').' Philippine time.'));
            } catch (Throwable $exception) {
                // An in-app notification failure must never retry an accepted email.
                Log::warning('Could not add the in-app disconnection notification.', ['consumer_id' => $consumer->id, 'error' => $exception->getMessage()]);
            }
        }

        return $deadline !== null;
    }

    public function sendLoanNotices(Consumer $consumer): void
    {
        $consumer->disconnectionNotices()->active()->where('source', DisconnectionNotice::SOURCE_LOAN)
            ->eachById(function (DisconnectionNotice $notice) use ($consumer) {
                DB::transaction(function () use ($consumer, $notice) {
                    $locked = DisconnectionNotice::query()->active()->lockForUpdate()->find($notice->id);
                    if (! $locked || (float) $locked->loan_balance_due <= 0) {
                        return;
                    }

                    $this->sendWarning($consumer, $locked, 'loan_overdue_disconnection_'.$locked->loan_id,
                        self::LOAN_NOTICE_SUBJECT, (float) $locked->loan_balance_due, $locked->deadline_at);
                });
            });
    }

    /**
 * Grants reconnection eligibility once an administrator has verified the
     * payment that fully cleared the outstanding balance.
     *
     * Paying alone is not enough: eligibility only appears after the payment is
     * verified and approved here.
     */
    public function grantEligibilityIfFullySettled(Consumer $consumer, float $verifiedAmount): bool
    {
        if ($this->outstandingForReconnection($consumer) > 0) {
            return false;
        }

        if (! in_array($consumer->status, ['Disconnected', 'For Reconnection'], true)) {
            return false;
        }

        app(ConsumerDisconnectionHistoryService::class)->recordSettlement($consumer);

        if ($consumer->full_payment_verified_at) {
            return false;
        }

        $consumer->forceFill(['full_payment_verified_at' => now()])->save();

        $this->sendPaymentVerifiedForReconnection($consumer, $verifiedAmount);

        return true;
    }

    /**
     * Confirms a verified payment cleared the account and reconnection may be
     * requested.
     */
    public function sendPaymentVerifiedForReconnection(Consumer $consumer, float $amount): bool
    {
        if (blank($consumer->email)) {
            return false;
        }

        return $this->deliver(
            $consumer,
            'payment_verified_'.now()->format('YmdHis'),
            'Payment Verified - Ready for Reconnection',
            'We have verified your payment of PHP '.number_format($amount, 2).'. '
            .'Your outstanding balance is now fully settled and your account is ready for reconnection. '
            .'Please contact the office to arrange authorized reconnection. '
            .'Please note that your water supply will only be restored after the association completes its reconnection procedure.',
        );
    }

    private function overdueMessage(Consumer $consumer, float $outstanding, $deadline): string
    {
        return 'Hello '.$consumer->full_name.', '
            .'Your water bill is now past its due date. '
            .'Total outstanding balance: PHP '.number_format($outstanding, 2).'. '
            .($deadline ? 'Please pay in full on or before '.($deadline->format('F d, Y')).' to avoid disconnection. ' : '')
            .'You can settle this from your account under Payments.';
    }

    private function sendWarning(Consumer $consumer, DisconnectionNotice $notice, string $event, string $subject, float $outstanding, ?Carbon $deadline): bool
    {
        $recipient = trim((string) $consumer->email);
        $result = ConsumerNotificationLog::sendOnce($consumer->id, $event, $recipient, function () use ($consumer, $notice, $event, $subject, $outstanding, $deadline, $recipient): void {
            $context = ['consumer_id' => $consumer->id, 'notice_id' => $notice->id, 'event' => $event, 'mailer' => config('mail.default')];
            Log::info('Disconnection email delivery attempt.', $context);
            if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('The disconnection notice requires a valid registered email address.');
            }
            if (! app()->runningUnitTests() && ! $this->usesDeliveryTransport((string) config('mail.default'))) {
                throw new RuntimeException('The disconnection mailer must submit messages to a mail server.');
            }
            $message = Mail::send('emails.consumer.disconnection-warning', compact('consumer', 'notice', 'subject', 'outstanding', 'deadline'),
                fn ($mail) => $mail->to($recipient)->subject($subject));
            if (! $message && ! app()->runningUnitTests()) {
                throw new RuntimeException('The configured mailer did not submit the disconnection email.');
            }
            Log::info('Disconnection email accepted by the mail transport.', $context + ['message_id' => $message?->getMessageId()]);
        });

        return $result === ConsumerNotificationLog::RESULT_SENT;
    }

    private function usesDeliveryTransport(string $mailer): bool
    {
        $config = config('mail.mailers.'.$mailer, []);

        return match ($config['transport'] ?? null) {
            'array', 'log', null => false,
            'failover', 'roundrobin' => ! empty($config['mailers'])
                && collect($config['mailers'])->every(fn ($member) => $this->usesDeliveryTransport($member)),
            default => true,
        };
    }

    /**
     * Sends a consumer-facing notice exactly once per event and records the
     * outcome so a later retry can tell success from failure.
     *
     * The event string must identify the trigger (for example a bill id or a
     * request id) so the same notice is never repeated for one occurrence.
     */
    public function sendNotice(Consumer $consumer, string $event, string $subject, string $message, bool $force = false): bool
    {
        return $this->deliver($consumer, $event, $subject, $message, $force) === ConsumerNotificationLog::RESULT_SENT;
    }

    /**
     * Sends once per event and records the outcome.
     */
    private function deliver(Consumer $consumer, string $event, string $subject, string $message, bool $force = false): string
    {
        $result = ConsumerNotificationLog::sendOnce(
            consumerId: $consumer->id,
            event: $event,
            recipient: $consumer->email,
            force: $force,
            send: function () use ($consumer, $subject, $message): void {
                Mail::send('emails.consumer.notice', [
                    'consumer' => $consumer->fresh(),
                    'subject' => $subject,
                    // "message" is reserved by Laravel's mailer, which injects the
                    // mail object under that key.
                    'noticeBody' => e($message),
                ], function ($mail) use ($consumer, $subject): void {
                    $mail->to($consumer->email)->subject($subject);
                });

                $user = $consumer->user;
                if ($user) {
                    $user->notify(new ConsumerNoticeNotification($subject, $message));
                }
            },
        );

        return $result;
    }
}
