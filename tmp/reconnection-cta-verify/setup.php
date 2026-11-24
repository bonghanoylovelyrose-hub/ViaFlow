<?php

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->isLocal() || DB::getDriverName() !== 'sqlite' || basename(DB::getDatabaseName()) !== 'reconnection-cta.sqlite') {
    throw new RuntimeException('Use the isolated reconnection email verification database.');
}
if (Artisan::call('migrate', ['--force' => true]) !== 0 || Consumer::exists()) {
    throw new RuntimeException('Use an empty verification database.');
}

$admin = User::factory()->create(['role' => 'admin']);
$owner = User::factory()->create(['role' => 'consumer', 'email' => 'reconnection-qa@example.test']);
$consumer = Consumer::create([
    'full_name' => 'Reconnection Email QA', 'account_number' => 'QA-RECONNECT-CTA', 'meter_number' => 'QA-CTA-METER', 'email' => $owner->email,
    'user_id' => $owner->id, 'status' => 'Disconnected', 'membership_type' => 'Non-Member',
    'installation_date' => today()->subYear(), 'disconnected_at' => now()->subDays(2),
]);
$bill = Bill::create(['consumer_id' => $consumer->id, 'bill_type' => 'Water', 'billing_period' => today()->subMonthNoOverflow()->startOfMonth(),
    'due_date' => today()->subWeek(), 'prn' => 'QA-RECONNECT-CTA-BILL', 'amount' => 300, 'status' => 'Paid']);
Payment::create(['consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'payment_type' => 'Water', 'reference_number' => 'QA-RECONNECT-CTA-PAY',
    'amount' => 300, 'status' => 'Verified', 'payment_date' => today(), 'verified_at' => now(), 'verified_by' => $admin->id,
    'payment_channel' => 'Counter', 'payment_method' => 'Cash']);
foreach ([1, 2, 3] as $number) {
    $consumer->installationRescheduleRequests()->create(['reschedule_type' => 'connection', 'status' => 'assigned',
        'original_installation_date' => today()->subYear()->addDays($number), 'new_installation_date' => today()->subYear()->addDays($number + 1),
        'assigned_at' => now()->subYear()->addDays($number), 'assignment_number' => $number, 'reason' => 'Previous installation request']);
}

$session = app('session')->driver();
$session->start();
Auth::guard('web')->login($admin);
$session->save();
$cipher = app('encrypter');
$name = $session->getName();
file_put_contents(__DIR__.'/session.json', json_encode(['id' => $session->getId(), 'name' => $name,
    'value' => $cipher->encrypt(CookieValuePrefix::create($name, $cipher->getKey()).$session->getId(), false)], JSON_THROW_ON_ERROR));

$dates = [];
foreach (range(1, 4) as $offset) {
    $day = today('Asia/Manila')->addDays($offset);
    $dates[] = ['input' => $day->toDateString(), 'label' => $day->format('F d, Y')];
}
file_put_contents(__DIR__.'/fixture.json', json_encode(['consumer' => $consumer->id, 'account' => $consumer->account_number,
    'meter' => $consumer->meter_number, 'email' => $owner->email, 'dates' => $dates], JSON_THROW_ON_ERROR));
echo 'Isolated reconnection account, verified payment, and Admin session are ready.'.PHP_EOL;
