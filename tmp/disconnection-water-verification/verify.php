<?php

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\DisconnectionNotice;
use App\Models\User;
use App\Services\DisconnectionRecordsService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

require __DIR__.'/../../vendor/autoload.php';

final class VerifyWaterNotice extends TestCase
{
    private const INBOX = 'water-notice-qa@example.test';

    public function runQa(): void
    {
        parent::setUp();
        if (! app()->environment('local') || DB::getDriverName() !== 'mysql'
            || DB::getDatabaseName() !== 'water_billing_disconnection_qa' || DB::connection()->getConfig('host') !== '127.0.0.1') {
            throw new RuntimeException('This verification requires the isolated local MySQL QA schema.');
        }
        config(['session.driver' => 'array']);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        self::assertSame(0, Artisan::call('migrate', ['--force' => true]));
        self::assertSame(0, Consumer::count(), 'Use an empty QA schema on the first run.');

        $realTime = now();
        $period = $realTime->copy()->startOfMonth();
        $issueTime = $realTime->copy()->subDays(4)->startOfHour();
        if (! $issueTime->isSameMonth($period)) {
            throw new RuntimeException('The QA case requires four prior days in the current month.');
        }
        Carbon::setTestNow($issueTime);
        $admin = User::factory()->create(['email' => 'admin@example.com', 'username' => 'water-notice-admin', 'role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $user = User::factory()->create(['email' => self::INBOX, 'role' => 'consumer']);
        $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Water Notice Development QA', 'email' => self::INBOX,
            'account_number' => 'QA-WATER-001', 'meter_number' => 'QA-DISC-WATER-001', 'status' => 'Active', 'membership_type' => 'Non-Member']);
        $this->actingAs($admin);
        foreach ([2 => 300, 1 => 380] as $offset => $amount) {
            $month = $period->copy()->subMonthsNoOverflow($offset);
            $this->post(route('billing.store'), [
                'consumer_id' => $consumer->id, 'billing_period' => $month->toDateString(), 'due_date' => $month->copy()->addDays(19)->toDateString(),
                'prn' => 'QA-WATER-'.$month->format('Ym'), 'amount' => $amount, 'status' => 'Unpaid',
            ])->assertSessionHasNoErrors();
        }
        self::assertSame(0, DisconnectionNotice::count());
        $this->actingAs($reader)->post(route('meter-readings.store'), ['meter_number' => $consumer->meter_number,
            'reading_date' => today()->toDateString(), 'current_reading' => 12])->assertSessionHasNoErrors();

        // First attempt uses a closed loopback port. The notice must commit and
        // remain visible while the error is recorded for the scheduled retry.
        $this->smtp(2529);
        $cycle = ['billing_period' => $period->toDateString(), 'due_date' => $period->copy()->endOfMonth()->toDateString(),
            'base_charge' => 150, 'member_unit_rate' => 18, 'non_member_unit_rate' => 20];
        $this->actingAs($admin)->post(route('billing.generate'), $cycle)->assertSessionHasNoErrors();
        $notice = DisconnectionNotice::query()->sole();
        $bill = Bill::query()->findOrFail($notice->bill_id);
        self::assertSame(1070.0, $bill->balance_due);
        self::assertSame(680.0, (float) $bill->arrears_amount);
        self::assertSame(2, $notice->months_unpaid);
        self::assertSame($issueTime->copy()->addDays(3)->toDateTimeString(), $notice->deadline_at->toDateTimeString());
        $event = 'disconnection_notice_'.$notice->bill_id;
        $log = ConsumerNotificationLog::query()->where('event', $event)->sole();
        self::assertSame(ConsumerNotificationLog::STATUS_FAILED, $log->status);
        self::assertStringContainsString('2529', $log->error_message);
        $this->verifyRecord($consumer, 'grace', 'Within Grace Period');

        $this->smtp(2528);
        Carbon::setTestNow($issueTime->copy()->addHour());
        self::assertSame(0, Artisan::call('billing:monitor-overdue'));
        self::assertSame(ConsumerNotificationLog::STATUS_SENT, $log->fresh()->status);
        self::assertSame(self::INBOX, $log->fresh()->recipient);
        $notice->refresh();
        self::assertSame(now()->addDays(3)->toDateTimeString(), $notice->deadline_at->toDateTimeString());
        $deadline = $notice->deadline_at->toDateTimeString();
        self::assertSame(0, Artisan::call('billing:monitor-overdue'));
        self::assertSame($deadline, $notice->fresh()->deadline_at->toDateTimeString());
        self::assertSame(1, ConsumerNotificationLog::query()->where('event', $event)->count());
        $this->verifyRecord($consumer, 'grace', 'Within Grace Period');
        $this->post(route('consumers.confirm-disconnection', $consumer))->assertSessionHasErrors('disconnection');

        Carbon::setTestNow($notice->deadline_at);
        $this->verifyRecord($consumer, 'ready', 'Ready for Disconnection');
        $this->verifyRecord($consumer, 'ready', 'Ready for Disconnection');
        self::assertSame('Active', $consumer->fresh()->status);

        // Exercise settlement through Admin Payments. Keep the isolated QA
        // notice available in Ready state for a fresh real browser session.
        DB::beginTransaction();
        try {
            $this->post(route('payments.store'), ['bill_id' => $bill->id, 'amount' => $bill->balance_due,
                'payment_type' => 'Water', 'payment_date' => today()->toDateString(), 'payment_channel' => 'Counter', 'payment_method' => 'Cash'])
                ->assertSessionHasNoErrors();
            self::assertSame(DisconnectionNotice::STATUS_SETTLED, $notice->fresh()->status);
            $this->verifyRecord($consumer, 'paid', 'Resolved');
            self::assertSame('Active', $consumer->fresh()->status);
        } finally {
            DB::rollBack();
        }
        Carbon::setTestNow();
        file_put_contents(__DIR__.'/result.json', json_encode([
            'notice' => $notice->id, 'bill' => $bill->id, 'account' => $consumer->account_number, 'meter' => $consumer->meter_number,
            'name' => $consumer->full_name, 'deadline' => $notice->deadline_at->copy()->timezone('Asia/Manila')->format('F d, Y g:i A'),
            'deadline_ui' => $notice->deadline_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A'),
            'subject' => 'Disconnection Notice - Immediate Payment Required', 'amount' => '1,070.00', 'inbox' => self::INBOX,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        echo "QA verified generation, failed SMTP logging, scheduled retry, deduplication, and the Grace, Ready and Resolved table states.\n";
    }

    private function smtp(int $port): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp' => ['transport' => 'smtp', 'scheme' => 'smtp', 'host' => '127.0.0.1',
            'port' => $port, 'timeout' => 4, 'auto_tls' => false], 'mail.from.address' => 'viaflow-qa@example.test']);
        Mail::purge('smtp');
        Mail::mailer('smtp')->alwaysTo(self::INBOX);
    }

    private function verifyRecord(Consumer $consumer, string $filter, string $status): void
    {
        $query = app(DisconnectionRecordsService::class)->paginate($consumer->meter_number, $filter);
        self::assertSame(1, $query->total());
        $record = $query->first();
        self::assertSame('Water Billing Overdue', $record['reason']);
        self::assertSame($status, $record['record_status']);
        $this->get(route('consumers.index', ['account_category' => 'active', 'notice_search' => $consumer->meter_number, 'notice_status' => $filter]))
            ->assertOk()->assertSee('data-record-status="'.$status.'"', false)->assertSee('Water Billing Overdue')->assertSee($consumer->meter_number);
    }
}

try {
    (new VerifyWaterNotice('runQa'))->runQa();
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL.$error->getTraceAsString().PHP_EOL);
    exit(1);
} finally {
    Carbon::setTestNow();
}
