<?php

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$sandbox = ($argv[1] ?? '') === 'qa';
if (! $app->isLocal() || ($sandbox ? DB::getDriverName() !== 'sqlite' || ! in_array(basename(DB::getDatabaseName()), ['activation-sort.sqlite', 'activation-sort-run2.sqlite'], true)
    : DB::getDriverName() !== 'mysql' || DB::getDatabaseName() !== 'water_billing_systems')) {
    throw new RuntimeException('Unexpected verification database.');
}

if ($sandbox) {
    if (Artisan::call('migrate', ['--force' => true]) !== 0 || Consumer::exists()) {
        throw new RuntimeException('Use an empty isolated verification database.');
    }
    $admin = User::factory()->create(['role' => 'admin']);
    $prior = Consumer::forceCreate(['full_name' => 'QA Sort Prior Reconnection', 'account_number' => 'SORT-OLD',
        'meter_number' => 'QA-SORT-RECONNECT', 'status' => 'Active', 'membership_type' => 'Non-Member',
        'verified_at' => now()->subYear(), 'reconnection_pending' => true,
        'reconnection_scheduled_at' => now()->subMinute(), 'installation_date' => today()]);
    $reconnection = $prior->reconnections()->create(['confirmed_by' => $admin->id, 'reconnection_date' => today(),
        'activated_at' => now()->subHour(), 'installation_scheduled_at' => $prior->reconnection_scheduled_at,
        'reconnection_fee' => 0, 'verified_payment_ids' => []]);
    $applicant = Consumer::forceCreate(['full_name' => 'QA Sort Approved Connection', 'status' => 'Pending Verification',
        'email' => 'qa-activation@example.test', 'membership_type' => 'Non-Member', 'created_at' => now()->subYears(2)]);
    foreach (range(1, 11) as $number) {
        Consumer::create(['full_name' => 'QA Sort Older '.$number, 'account_number' => 'SORT-'.$number,
            'meter_number' => 'QA-SORT-'.$number, 'status' => 'Active', 'membership_type' => 'Non-Member',
            'verified_at' => now()->subDays($number), 'installation_date' => today()]);
    }
    file_put_contents(__DIR__.'/qa-fixtures.json', json_encode(['applicant' => $applicant->id, 'prior' => $prior->id, 'reconnection' => $reconnection->id], JSON_THROW_ON_ERROR));
} else {
    $admin = User::query()->where('role', 'admin')->firstOrFail();
    $tables = ['consumers', 'consumer_reconnections', 'disconnection_notices', 'bills', 'payments'];
    $checksums = [];
    foreach ($tables as $table) {
        $checksums[$table] = hash('sha256', json_encode(DB::table($table)->orderBy('id')->get(), JSON_THROW_ON_ERROR));
    }
    file_put_contents(__DIR__.'/main-checksums.json', json_encode($checksums, JSON_THROW_ON_ERROR));
    $expected = Consumer::query()->activeAccounts()->whereDoesntHave('disconnectionNotices', fn ($query) => $query->active())
        ->with('reconnections')->get()->map(fn (Consumer $account) => [
            'id' => $account->id,
            'meter' => $account->meter_number,
            'occurred_at' => max($account->verified_at?->getTimestamp(), $account->reconnected_at?->getTimestamp(),
                $account->reconnections->max('activated_at')?->getTimestamp(), $account->reconnections->max('completed_at')?->getTimestamp()),
        ])->sortBy([['occurred_at', 'desc'], ['id', 'desc']])->take(10)->pluck('meter')->all();
    file_put_contents(__DIR__.'/main-order.json', json_encode($expected, JSON_THROW_ON_ERROR));
}

$session = app('session')->driver();
$session->start();
Auth::guard('web')->login($admin);
$session->save();
$cipher = app('encrypter');
$name = $session->getName();
file_put_contents(__DIR__.'/'.($sandbox ? 'qa' : 'main').'-session.json', json_encode([
    'id' => $session->getId(), 'name' => $name,
    'value' => $cipher->encrypt(CookieValuePrefix::create($name, $cipher->getKey()).$session->getId(), false),
], JSON_THROW_ON_ERROR));
echo $sandbox ? 'Isolated activation and reconnection cases are ready.'.PHP_EOL : 'Local MySQL order calculated from the persisted timestamps.'.PHP_EOL;
