<?php

use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\DisconnectionNotice;
use App\Services\ConsumerBalanceService;
use App\Services\DisconnectionNoticeService;
use App\Services\DisconnectionRecordsService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('local') || DB::getDriverName() !== 'mysql' || DB::getDatabaseName() !== 'water_billing_systems') {
    throw new RuntimeException('This fixture requires the local ViaFlow MySQL database.');
}

$meters = ['MT-2121', 'MT-7878'];
$purpose = 'Development QA: expired six-month meter loan notice';
$mailbox = 'loan-overdue-qa@example.test';
$term = 6;
$consumers = Consumer::query()->whereIn('meter_number', $meters)->get()->keyBy('meter_number');
if ($consumers->count() !== count($meters)) {
    throw new RuntimeException('Both meter accounts must already exist.');
}

$historyDigest = function () use ($consumers, $purpose): string {
    $consumersIds = $consumers->modelKeys();
    $existingLoans = DB::table('loans')->whereIn('consumer_id', $consumersIds)->where('purpose', '<>', $purpose)->orderBy('id')->get();
    $originalIds = $existingLoans->pluck('id');
    return hash('sha256', json_encode([
        $existingLoans,
        DB::table('loan_installments')->whereIn('loan_id', $originalIds)->orderBy('id')->get(),
        DB::table('loan_repayments')->whereIn('loan_id', $originalIds)->orderBy('id')->get(),
        DB::table('bills')->whereIn('consumer_id', $consumersIds)->orderBy('id')->get(),
        DB::table('payments')->whereIn('consumer_id', $consumersIds)->orderBy('id')->get(),
        DB::table('disconnection_notices')->whereIn('consumer_id', $consumersIds)
            ->where(fn ($query) => $query->whereNull('loan_id')->orWhereIn('loan_id', $originalIds))->orderBy('id')->get(),
    ], JSON_THROW_ON_ERROR));
};
$baseline = __DIR__.'/history.sha256';
$previousHistory = is_file($baseline) ? trim(file_get_contents($baseline)) : $historyDigest();

if (($argv[1] ?? '') === '--seed') {
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp' => ['transport' => 'smtp', 'scheme' => 'smtp', 'host' => '127.0.0.1', 'port' => 2528, 'timeout' => 5, 'auto_tls' => false],
        'mail.from.address' => 'viaflow-development@example.test',
    ]);
    Mail::mailer('smtp')->alwaysTo($mailbox);

    DB::transaction(function () use ($consumers, $purpose, $term, $mailbox, $historyDigest, $previousHistory) {
        $noticeService = app(DisconnectionNoticeService::class);
        $balanceService = app(ConsumerBalanceService::class);
        $issuedAt = now()->subDays(ConsumerBalanceService::PAYMENT_GRACE_DAYS)->subMinute();
        $finalDue = $issuedAt->copy()->timezone('Asia/Manila')->subDay()->startOfDay();
        $firstDue = $finalDue->copy()->subMonthsNoOverflow($term - 1);
        $sendTo = [];

        foreach ($consumers as $account) {
            $consumer = Consumer::query()->lockForUpdate()->findOrFail($account->id);
            $loan = $consumer->loans()->where('purpose', $purpose)->first();
            if ($consumer->status !== 'Active' || $consumer->reconnection_pending
                || $balanceService->consecutiveUnpaidMonthsFor($consumer->id) >= ConsumerBalanceService::DISCONNECTION_NOTICE_AFTER_MONTHS
                || $consumer->disconnectionNotices()->active()->when($loan, fn ($query) => $query->where(fn ($other) => $other->whereNull('loan_id')->orWhere('loan_id', '<>', $loan->id)))->exists()) {
                throw new RuntimeException($consumer->meter_number.' does not qualify for an isolated loan fixture.');
            }
            if (! $loan) {
                $original = $consumer->loans()->where('term_months', $term)->orderBy('id')->firstOrFail();
                $schedule = $original->installments()->orderBy('installment_number')->get();
                if ($schedule->count() !== $term || round((float) $schedule->sum('required_amount'), 2) !== (float) $original->amount) {
                    throw new RuntimeException($consumer->meter_number.' has no matching six-month installment schedule.');
                }
                $loan = $consumer->loans()->create([
                    'amount' => $original->amount,
                    'term_months' => $term,
                    'purpose' => $purpose,
                    'status' => 'Active',
                    'review_status' => 'Reviewed',
                    'monthly_installment' => $original->monthly_installment,
                    'applied_at' => $firstDue->copy()->subMonthNoOverflow(),
                    'approved_at' => $firstDue->copy()->subMonthNoOverflow(),
                    'start_billing_period' => $firstDue->copy()->startOfMonth(),
                    'next_due_date' => $firstDue,
                ]);
                foreach ($schedule as $installment) {
                    $due = $finalDue->copy()->subMonthsNoOverflow($term - $installment->installment_number);
                    $loan->installments()->create([
                        'installment_number' => $installment->installment_number,
                        'billing_period' => $due->copy()->startOfMonth(),
                        'due_date' => $due,
                        'required_amount' => $installment->required_amount,
                        'amount_paid' => 0,
                        'remaining_balance' => $installment->required_amount,
                        'status' => 'Overdue',
                    ]);
                }
            }

            try {
                Date::setTestNow($issuedAt);
                $noticeService->reconcileLoanNotices($consumer);
            } finally {
                Date::setTestNow();
            }
            $notice = $consumer->disconnectionNotices()->active()->where('loan_id', $loan->id)->sole();
            if ($notice->source !== DisconnectionNotice::SOURCE_LOAN || ! $notice->paymentWindowHasElapsed()) {
                throw new RuntimeException('The reconciled notice did not reach the Ready state.');
            }
            $recipient = clone $consumer;
            $recipient->email = $mailbox;
            $sendTo[] = [$recipient, $loan->id];
        }

        if ($historyDigest() !== $previousHistory) {
            throw new RuntimeException('Existing loan, billing, payment, or notice history changed.');
        }
        foreach ($sendTo as [$recipient, $loanId]) {
            $noticeService->sendLoanNotices($recipient);
            $noticeService->sendLoanNotices($recipient);
            $log = ConsumerNotificationLog::query()->where('consumer_id', $recipient->id)->where('event', 'loan_overdue_disconnection_'.$loanId)->sole();
            if ($log->status !== ConsumerNotificationLog::STATUS_SENT || $log->recipient !== $mailbox) {
                throw new RuntimeException('Fixture email was not delivered to the local inbox. '.$log->error_message);
            }
        }
    });
    file_put_contents($baseline, $previousHistory.PHP_EOL);
}

if (! is_file($baseline) || $historyDigest() !== $previousHistory) {
    throw new RuntimeException('Existing record history could not be verified.');
}

$snapshot = [];
foreach (['', 'ready'] as $filter) {
    $rows = app(DisconnectionRecordsService::class)->paginate('', $filter)->getCollection()->take(2);
    if ($rows->pluck('consumer.meter_number')->sort()->values()->all() !== $meters) {
        throw new RuntimeException('Both meters must be first in the actual disconnection query.');
    }
    foreach ($rows as $row) {
        $consumer = $row['consumer'];
        $loan = $consumer->loans()->where('purpose', $purpose)->sole();
        $notice = $consumer->disconnectionNotices()->where('loan_id', $loan->id)->sole();
        $event = ConsumerNotificationLog::query()->where('consumer_id', $consumer->id)->where('event', 'loan_overdue_disconnection_'.$loan->id)->sole();
        if ($loan->term_months != $term || $loan->installments()->count() !== $term || $row['loan_outstanding'] !== (float) $loan->amount
            || $row['reason'] !== 'Meter Loan Overdue' || $row['record_status'] !== 'Ready for Disconnection'
            || $consumer->status !== 'Active' || ! $row['can_confirm_disconnection'] || $notice->deadline_at->isFuture()
            || ! $notice->issued_at->copy()->addDays(ConsumerBalanceService::PAYMENT_GRACE_DAYS)->equalTo($notice->deadline_at)
            || $event->status !== ConsumerNotificationLog::STATUS_SENT || $event->recipient !== $mailbox) {
            throw new RuntimeException('Persisted notice verification failed for '.$consumer->meter_number);
        }
        $snapshot[$consumer->meter_number] = [
            'account' => $consumer->account_number,
            'name' => $consumer->full_name,
            'meter' => $consumer->meter_number,
            'loan' => $loan->id,
            'notice' => $notice->id,
            'balance' => $row['loan_outstanding'],
            'final_due' => $notice->loan_due_date->format('F d, Y'),
            'issued' => $notice->issued_at->copy()->timezone('Asia/Manila')->format('F d, Y g:i A'),
            'deadline' => $notice->deadline_at->copy()->timezone('Asia/Manila')->format('F d, Y g:i A'),
            'issued_ui' => $notice->issued_at->copy()->timezone('Asia/Manila')->format('M d, Y'),
            'deadline_ui' => $notice->deadline_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A'),
            'status' => $row['record_status'],
            'reason' => $row['reason'],
            'account_status' => $consumer->status,
        ];
    }
}
file_put_contents(__DIR__.'/records.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
foreach ($snapshot as $record) {
    printf("%s | loan %d | notice %d | PHP %.2f | %s | %s | %s\n", $record['meter'], $record['loan'], $record['notice'], $record['balance'], $record['deadline'], $record['status'], $record['account_status']);
}
echo "Original loan, installment, repayment, billing, payment, and notice history matches the baseline.\n";
