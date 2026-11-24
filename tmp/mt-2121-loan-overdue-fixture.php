<?php

use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (config('database.default') !== 'mysql') {
    throw new RuntimeException('Use the local MySQL application as the read-only source.');
}

$source = Consumer::where('meter_number', 'MT-2121')->firstOrFail();
$original = $source->loans()->where('term_months', 6)->with('installments')->sole();
if ($original->installments->count() !== 6 || $original->repayments()->exists()) {
    throw new RuntimeException('The source schedule must contain six installments and no repayment history.');
}

$testDatabase = base_path('tmp/mt-2121-loan-overdue.sqlite');
if (file_exists($testDatabase)) {
    throw new RuntimeException('The isolated fixture already exists.');
}
touch($testDatabase);
config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $testDatabase,
    'mail.default' => 'array',
]);
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException(Artisan::output());
}

$admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com', 'password' => 'password']);
$user = User::factory()->create(['role' => 'consumer', 'email' => 'mt-2121-notice@example.test', 'password' => 'OverdueFixture2121']);
$consumer = Consumer::create([
    'user_id' => $user->id,
    'full_name' => $source->full_name,
    'email' => $user->email,
    'account_number' => $source->account_number,
    'meter_number' => $source->meter_number,
    'status' => $source->status,
    'membership_type' => $source->membership_type,
    'membership_verification_status' => $source->membership_verification_status,
    'service_purok' => $source->service_purok,
]);

$finalDue = today('Asia/Manila')->subDay();
$start = $finalDue->copy()->subMonthsNoOverflow(5)->startOfMonth();
$loan = Loan::create([
    'consumer_id' => $consumer->id,
    'applied_by_user_id' => $user->id,
    'amount' => $original->amount,
    'term_months' => $original->term_months,
    'monthly_installment' => $original->monthly_installment,
    'purpose' => $original->purpose ?: 'Meter installation',
    'status' => $original->status,
    'applied_at' => $start->copy()->subDays(10),
    'approved_at' => $start->copy()->subDays(5),
    'start_billing_period' => $start,
    'next_due_date' => $finalDue->copy()->subMonthsNoOverflow(5),
    'installment_schedule_snapshot' => $original->installment_schedule_snapshot,
]);

foreach ($original->installments as $row) {
    $due = $finalDue->copy()->subMonthsNoOverflow(6 - (int) $row->installment_number);
    $loan->installments()->create([
        'installment_number' => $row->installment_number,
        'billing_period' => $due->copy()->startOfMonth(),
        'due_date' => $due,
        'required_amount' => $row->required_amount,
        'amount_paid' => 0,
        'remaining_balance' => $row->required_amount,
        'status' => 'Overdue',
    ]);
}

if (Artisan::call('billing:monitor-overdue') !== 0 || Artisan::call('billing:monitor-overdue') !== 0) {
    throw new RuntimeException(Artisan::output());
}
$notice = $consumer->disconnectionNotices()->sole();
$messages = Mail::getSymfonyTransport()->messages();
if (! $notice->isActive() || $notice->source !== DisconnectionNotice::SOURCE_LOAN || $notice->loan_id !== $loan->id
    || ! $notice->deadline_at->eq($notice->issued_at->copy()->addDays(3)) || $messages->count() !== 1) {
    throw new RuntimeException('Notice or email did not match the isolated fixture expectations.');
}
$message = $messages->first()->getOriginalMessage();
file_put_contents(base_path('tmp/mt-2121-loan-overdue-email.html'), $message->getHtmlBody());
$payment = Payment::create([
    'consumer_id' => $consumer->id, 'loan_id' => $loan->id, 'amount' => $notice->loan_balance_due,
    'payment_type' => 'Loan', 'payment_method' => 'GCash', 'payment_channel' => 'Online',
    'payment_date' => today(), 'reference_number' => 'QA-MT-2121-FULL', 'status' => 'Pending',
]);
echo json_encode([
    'amount' => $notice->loan_balance_due, 'payment_id' => $payment->id,
    'issued' => $notice->issued_at->toIso8601String(), 'deadline' => $notice->deadline_at->toIso8601String(),
    'subject' => $message->getSubject(), 'messages' => $messages->count(),
], JSON_UNESCAPED_UNICODE).PHP_EOL;