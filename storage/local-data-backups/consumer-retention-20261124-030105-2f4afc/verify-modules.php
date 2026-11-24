<?php

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Incident;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

putenv('SESSION_DRIVER=array');
putenv('CACHE_STORE=array');
putenv('LOG_CHANNEL=stderr');
$project = dirname(__DIR__, 3);
require $project.'/vendor/autoload.php';
$app = require $project.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

config(['session.driver' => 'array', 'cache.default' => 'array']);
Mail::fake();
Notification::fake();
Queue::fake();
Bus::fake();
$db = DB::connection();

function must(bool $assertion, string $message): void
{
    if (!$assertion) {
        throw new RuntimeException($message);
    }
}

try {
    must($app->environment('local') && $db->getConfig('host') === '127.0.0.1' && $db->getDatabaseName() === 'water_billing_systems', 'Unexpected database for module checks.');
    $admin = User::query()->where('role', 'admin')->firstOrFail();
    $reader = User::query()->where('role', 'meter_reader')->firstOrFail();
    $active = Consumer::query()->with('user')->where('meter_number', 'MT-2121')->firstOrFail();
    $other = Consumer::query()->with('user')->where('meter_number', 'MT-7777')->firstOrFail();
    $meters = ['MT-0909', 'MT-2121', 'MT-3333', 'MT-7777', 'MT-8989', 'MT-9191'];
    $bill = Bill::query()->where('consumer_id', $active->id)->where('bill_type', 'Water')->firstOrFail();
    $payment = Payment::query()->whereIn('consumer_id', [$active->id, $other->id])->where('status', 'Verified')->firstOrFail();
    $incident = Incident::query()->where('consumer_id', $active->id)->whereNotNull('photo_path')->firstOrFail();
    $cases = [
        [$admin, '/dashboard', 200, 'admin.dashboard'],
        [$admin, '/consumers', 200, 'admin.consumers.index', function ($data, $html) use ($meters) {
            must($data['consumers']->total() === 6 && $data['consumers']->getCollection()->pluck('meter_number')->sort()->values()->all() === $meters, 'Admin Consumer Management did not return exactly the six meters.');
            foreach ($meters as $meter) {
                must(str_contains($html, $meter), 'A retained meter is missing from the rendered list.');
            }
            foreach (['MT3232', 'MT-4444', 'MT-5674', 'MT-7878', 'MT-1111', 'MT-9999', 'MT-1212', 'MT-1313', 'MT-8181', 'MT-7676', 'MT-2525', 'MT-0808'] as $meter) {
                must(!str_contains($html, $meter), 'An approved removed meter still appears in the rendered list.');
            }
        }],
        [$admin, '/consumers?account_category=pending&pending_type=applications', 200, 'admin.consumers.index',
            fn ($data) => must($data['consumers']->total() === 0, 'A removed pending application still appears.')],
        [$admin, '/consumers/57/billing-history', 404, null],
        [$admin, '/billing', 200, 'admin.billing.index'],
        [$admin, '/payments', 200, 'admin.payments.index'],
        [$admin, '/loans', 200, 'admin.loans.index'],
        [$admin, '/incidents', 200, 'admin.incidents.index',
            fn ($data) => must($data['incidents']->total() === 5, 'Unexpected Admin incident count.')],
        [$reader, '/dashboard', 200, 'meter-reading.dashboard'],
        [$reader, '/meter-readings', 200, 'meter-reading.meter-readings.index'],
        [$active->user, '/dashboard', 200, 'consumer.dashboard'],
        [$active->user, '/profile', 200, 'consumer.profile.index',
            fn ($data) => must($data['consumer']->meter_number === 'MT-2121', 'Retained profile did not open.')],
        [$active->user, '/billing', 200, 'consumer.billing.index'],
        [$active->user, '/billing/'.$bill->id, 200, 'consumer.billing.statement'],
        [$active->user, '/payments', 200, 'consumer.payments.index'],
        [$payment->consumer->user, '/payments/'.$payment->id.'/receipt', 200, 'consumer.payments.receipt'],
        [$active->user, '/loans', 200, 'consumer.loans.index'],
        [$active->user, '/incidents', 200, 'consumer.incidents.index',
            fn ($data) => must($data['incidents']->total() === 5, 'Retained incident history did not display.')],
        [$active->user, '/incidents/'.$incident->id.'/photo', 200, null],
        [$other->user, '/incidents', 200, 'consumer.incidents.index',
            fn ($data) => must($data['incidents']->total() === 0, 'Another consumer saw the retained incident history.')],
    ];

    foreach ($cases as $case) {
        [$user, $path, $expectedStatus, $view] = $case;
        $db->beginTransaction();
        try {
            $request = Request::create(rtrim(config('app.url'), '/').$path);
            $app->instance('request', $request);
            Auth::forgetGuards();
            Auth::guard('web')->setUser($user);
            $response = $kernel->handle($request);
            $status = $response->getStatusCode();
            must($status === $expectedStatus, "Unexpected HTTP $status for {$user->role} $path, expected $expectedStatus.");
            if ($view !== null) {
                $original = $response->getOriginalContent();
                must($original instanceof Illuminate\View\View && $original->name() === $view, "Unexpected view for {$user->role} $path.");
                if (isset($case[4])) {
                    $case[4]($original->getData(), $response->getContent());
                }
            }
            printf("%s %s | HTTP %d\n", $user->role, $path, $status);
        } finally {
            $db->rollBack(0);
        }
    }
    printf("%d routed checks passed. Any reconciliation writes were rolled back.\n", count($cases));
} catch (Throwable $error) {
    printf("Verification failed: %s\n", $error->getMessage());
    exit(1);
}
