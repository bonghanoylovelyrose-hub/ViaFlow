<?php

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('DB_URL=');
putenv('MAIL_MAILER=array');
putenv('SESSION_DRIVER=array');

require __DIR__.'/../vendor/autoload.php';
$application = require __DIR__.'/../bootstrap/app.php';
$application->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'mail.default' => 'array', 'session.driver' => 'array']);
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
$admin = App\Models\User::factory()->create(['role' => 'admin']);
$consumer = App\Models\Consumer::query()->create([
    'full_name' => 'Scroll Preview Consumer', 'account_number' => 'VV-SCROLL-001',
    'meter_number' => 'MTR-SCROLL-001', 'status' => 'Active', 'membership_type' => 'Member',
]);
foreach (range(1, 24) as $index) {
    $status = $index % 3 === 0 ? 'Overdue' : 'Unpaid';
    $bill = App\Models\Bill::query()->create([
        'consumer_id' => $consumer->id, 'billing_period' => today()->subMonths($index)->startOfMonth()->toDateString(),
        'due_date' => today()->subDays($index)->toDateString(), 'prn' => sprintf('SCROLL-PRN-%02d', $index),
        'amount' => 500 + $index, 'status' => $status, 'months_unpaid' => 1,
    ]);
    if ($index <= 12) {
        App\Models\Payment::query()->create([
            'bill_id' => $bill->id, 'consumer_id' => $consumer->id,
            'reference_number' => sprintf('SCROLL-PAY-%02d', $index), 'amount' => 100,
            'payment_date' => today()->subDays($index)->toDateString(), 'payment_method' => 'Cash',
            'payment_channel' => $index % 2 ? 'Counter' : 'Online', 'status' => $index % 2 ? 'Pending' : 'Verified',
            'verified_at' => $index % 2 ? null : now(), 'verified_by' => $index % 2 ? null : $admin->id,
        ]);
    }
}
Illuminate\Support\Facades\Auth::login($admin);
Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag);
foreach (['all' => [], 'open' => ['view' => 'open_bills'], 'history' => ['view' => 'payment_history']] as $name => $query) {
    $request = Illuminate\Http\Request::create('http://127.0.0.1:8000/payments', 'GET', $query);
    $request->setUserResolver(fn () => $admin);
    $application->instance('request', $request);
    Illuminate\Support\Facades\URL::setRequest($request);
    Illuminate\Pagination\Paginator::currentPageResolver(fn () => 1);
    $html = $application->make(App\Http\Controllers\PaymentController::class)->index($request)->render();
    file_put_contents(__DIR__.'/payments-scroll-'.$name.'.html', $html);
}
echo "Rendered payment views with temporary SQLite fixture records.\n";
