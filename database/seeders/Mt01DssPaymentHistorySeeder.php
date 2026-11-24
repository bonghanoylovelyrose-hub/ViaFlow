<?php

namespace Database\Seeders;

use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\User;
use App\Services\BillingComputationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Mt01DssPaymentHistorySeeder extends Seeder
{
    private const PREFIX = 'DEMO-DSS-MT01-';

    public function run(BillingComputationService $billing): void
    {
        $connection = DB::connection();
        if (! app()->isLocal() || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'water_billing_systems'
            || ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new RuntimeException('This seeder runs only in the local ViaFlow MySQL database.');
        }

        DB::transaction(function () use ($billing): void {
            $consumer = Consumer::query()->where('meter_number', 'MT01')->lockForUpdate()->sole();
            if ($consumer->account_number !== '14' || ! $consumer->isVerifiedViohoaMember() || ! $consumer->user?->isConsumer()) {
                throw new RuntimeException('The expected verified demo account MT01 could not be confirmed.');
            }
            $history = $consumer->bills()->where('bill_type', 'Water')
                ->where('billing_period', '>=', '2026-07-01')->where('billing_period', '<', '2026-11-01');
            if ((clone $history)->where('is_demo', true)->where('prn', 'like', self::PREFIX.'%')->count() === 4) {
                return;
            }
            if ($history->exists() || $consumer->meterReadings()->where('reading_date', '>=', '2026-07-01')
                ->where('reading_date', '<', '2026-11-01')->exists()) {
                throw new RuntimeException('Existing July–October records would overlap this demo history.');
            }

            $admin = User::query()->where('role', 'admin')->oldest('id')->firstOrFail();
            $reader = $consumer->meterReadings()->latest('reading_date')->firstOrFail()->reader;
            $usage = [8, 11, 10, 12];
            $daysPaid = [20, 22, 24];
            $previous = 0;
            foreach ($usage as $index => $units) {
                $period = Carbon::create(2026, 7 + $index, 1);
                $readingDate = $period->copy()->day(3)->setTime(9, 0);
                $reading = $consumer->meterReadings()->make([
                    'meter_number' => $consumer->meter_number,
                    'reader_id' => $reader->id,
                    'reading_date' => $readingDate->toDateString(),
                    'submitted_at' => $readingDate,
                    'previous_reading' => $previous,
                    'current_reading' => $previous + $units,
                    'consumption' => $units,
                    'status' => MeterReading::STATUS_ENCODED,
                    'remarks' => 'Local demo reading before the existing November meter baseline.',
                ]);
                $reading->forceFill(['created_at' => $readingDate])->save();
                $previous += $units;

                $rate = BillingComputationService::MEMBER_UNIT_RATE;
                $charges = $billing->calculate($units, BillingComputationService::DEFAULT_BASE_CHARGE, $rate, 0);
                $reference = self::PREFIX.$period->format('Ym');
                $bill = $consumer->bills()->make([
                    'bill_type' => 'Water',
                    'meter_reading_id' => $reading->id,
                    'billing_period' => $period,
                    'due_date' => $period->copy()->day(12),
                    'prn' => $reference,
                    'amount' => $charges['total_due'],
                    'base_charge' => $charges['base_charge'],
                    'water_charge' => $charges['usage_charge'],
                    'arrears_amount' => 0,
                    'penalty_amount' => 0,
                    'units_consumed' => $units,
                    'unit_rate' => $rate,
                    'status' => 'Unpaid',
                    'months_unpaid' => 0,
                ]);
                $bill->forceFill(['is_demo' => true, 'created_at' => $readingDate])->save();

                if (isset($daysPaid[$index])) {
                    $paidAt = $period->copy()->day($daysPaid[$index])->setTime(10, 0);
                    $payment = $bill->payments()->make([
                        'consumer_id' => $consumer->id,
                        'payment_type' => 'Water',
                        'reference_number' => $reference.'-PAY',
                        'receipt_number' => $reference.'-RCP',
                        'amount' => $charges['total_due'],
                        'payment_date' => $paidAt->toDateString(),
                        'payment_method' => 'Cash',
                        'payment_channel' => 'Counter',
                        'status' => 'Verified',
                        'verified_at' => $paidAt,
                        'verified_by' => $admin->id,
                        'notes' => 'Local DSS demonstration payment.',
                    ]);
                    $payment->forceFill(['created_at' => $paidAt])->save();
                }

                $bill->syncPaymentStatus(false);
            }
        });
    }
}
