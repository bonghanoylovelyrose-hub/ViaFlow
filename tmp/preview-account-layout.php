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
if (! is_dir(__DIR__.'/admin-layout-view-cache')) mkdir(__DIR__.'/admin-layout-view-cache', 0777, true);
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'mail.default' => 'array', 'session.driver' => 'array', 'view.compiled' => __DIR__.'/admin-layout-view-cache']);
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
$admin = App\Models\User::factory()->create(['role' => 'admin']);
Illuminate\Support\Facades\Auth::login($admin);
Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag);
for ($number = 1; $number <= 140; $number++) {
    $status = match (true) {
        $number <= 60 => 'Active', $number <= 100 => 'Disconnected', $number <= 120 => 'Pending Verification', default => 'Rejected',
    };
    $account = App\Models\Consumer::query()->create([
        'full_name' => 'Consumer '.str_pad((string) $number, 3, '0', STR_PAD_LEFT).' Sample Resident',
        'account_number' => $status === 'Pending Verification' ? null : 'VV-2026-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
        'meter_number' => $status === 'Pending Verification' ? null : 'MTR-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
        'status' => $status, 'membership_type' => 'Non-Member', 'email' => 'sample'.$number.'@example.test',
        'service_address' => 'Block 12, Lot 8, Via Vida, Panabo City', 'address_block' => 'Block 12', 'address_street' => 'Via Vida Street',
        'contact_number' => '09171234567', 'association_member' => true, 'association_registered' => true,
    ]);
    if ($number % 2 === 0) {
        $bill = App\Models\Bill::query()->create([
            'consumer_id' => $account->id, 'billing_period' => today()->startOfMonth(), 'due_date' => today()->addMonth(),
            'prn' => 'PREVIEW-'.$number, 'amount' => 725.50, 'status' => 'Unpaid',
        ]);
        if ($number <= 40) {
            App\Models\DisconnectionNotice::query()->create([
                'consumer_id' => $account->id, 'bill_id' => $bill->id, 'status' => 'Active',
                'issued_at' => now()->subDays(4), 'deadline_at' => now()->subDay(), 'outstanding_amount' => 725.50, 'months_unpaid' => 3,
            ]);
        }
    }
}
for ($number = 1; $number <= 30; $number++) {
    App\Models\User::factory()->create(['role' => 'staff']);
    App\Models\Incident::query()->create([
        'consumer_id' => $number, 'reported_by' => $admin->id, 'category' => 'Leak',
        'location' => 'Via Vida', 'purok' => '1', 'block' => '1', 'description' => 'Sample service issue '.$number,
        'status' => 'Pending', 'reported_at' => now(),
    ]);
    App\Models\Announcement::query()->create([
        'user_id' => $admin->id, 'title' => 'Sample advisory '.$number, 'body' => str_repeat('Sample advisory information. ', 8),
        'category' => 'Service Advisory', 'audience' => 'All', 'is_pinned' => false, 'published_at' => now(),
    ]);
    App\Models\Loan::query()->create([
        'consumer_id' => $number, 'applied_by_user_id' => $admin->id, 'amount' => 3000,
        'term_months' => 6, 'purpose' => 'Sample installation '.$number, 'monthly_installment' => 500,
        'status' => 'Pending Review', 'review_status' => 'Pending Review', 'installation_status' => 'Not Scheduled', 'applied_at' => now(),
    ]);
}
foreach (App\Models\Bill::query()->limit(20)->get() as $bill) {
    App\Models\Payment::query()->create([
        'consumer_id' => $bill->consumer_id, 'bill_id' => $bill->id, 'reference_number' => 'PAYMENT-PREVIEW-'.$bill->id,
        'amount' => 200, 'payment_method' => 'Cash', 'payment_channel' => 'Counter', 'payment_date' => today(), 'status' => 'Pending',
    ]);
}
App\Models\Consumer::query()->findOrFail(5)->update([
    'membership_type' => 'Member', 'requested_membership_type' => 'Member', 'membership_verification_status' => 'Pending',
]);
foreach ([2, 4] as $consumerId) {
    App\Models\Consumer::query()->findOrFail($consumerId)->update(['installation_date' => today()->addDay()]);
    App\Models\InstallationRescheduleRequest::query()->create([
        'consumer_id' => $consumerId, 'reschedule_type' => App\Models\InstallationRescheduleRequest::TYPE_CONNECTION,
        'status' => App\Models\InstallationRescheduleRequest::STATUS_PENDING, 'original_installation_date' => today()->addDay(),
        'original_installation_time' => '09:00:00', 'reason' => 'Sample installation reschedule request',
    ]);
}
foreach (['all' => '', 'active' => 'active', 'inactive' => 'inactive', 'pending' => 'pending', 'rejected' => 'rejected'] as $label => $category) {
    $request = Illuminate\Http\Request::create('http://127.0.0.1:8000/consumers', 'GET', ['account_category' => $category]);
    $request->setUserResolver(fn () => $admin);
    $application->instance('request', $request);
    Illuminate\Support\Facades\URL::setRequest($request);
    Illuminate\Pagination\Paginator::currentPageResolver(fn () => 1);
    $html = $application->make(App\Http\Controllers\ConsumerController::class)->index($request)->render();
    file_put_contents(__DIR__.'/account-layout-'.$label.'.html', $html);
}
foreach (['dashboard', 'payments', 'billing', 'staff', 'incidents', 'loans', 'reports', 'meter-readings', 'announcements', 'dss/loans', 'dss/incidents'] as $module) {
    $request = Illuminate\Http\Request::create('http://127.0.0.1:8000/'.$module);
    $kernel = $application->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    file_put_contents(__DIR__.'/admin-scroll-'.str_replace('/', '-', $module).'.html', $response->getContent());
    echo $module.': '.$response->getStatusCode()."\n";
    $kernel->terminate($request, $response);
}
echo "Rendered account and Admin module previews using only temporary SQLite records.\n";
foreach ([
    'consumers-active-empty-notices' => ['/consumers', ['account_category' => 'active', 'notice_search' => 'No matching account']],
    'reports-billing' => ['/reports', ['report' => 'billing']],
    'consumers-applications' => ['/consumers', ['account_category' => 'pending', 'pending_type' => 'applications']],
    'consumers-reschedules' => ['/consumers', ['account_category' => 'pending', 'pending_type' => 'reschedules']],
    'consumers-membership' => ['/consumers', ['membership' => 'member', 'membership_verification' => 'pending']],
] as $label => [$path, $query]) {
    $request = Illuminate\Http\Request::create('http://127.0.0.1:8000'.$path, 'GET', $query);
    $kernel = $application->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    file_put_contents(__DIR__.'/admin-scroll-'.$label.'.html', $response->getContent());
    echo $label.': '.$response->getStatusCode()."\n";
    $kernel->terminate($request, $response);
}
