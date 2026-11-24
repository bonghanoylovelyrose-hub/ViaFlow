<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\Consumer;
use App\Services\BillingComputationService;
use App\Services\ConsumerBalanceService;
use App\Services\OverdueMonitoringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoDisconnectionNoticesSeeder extends Seeder
{
    public function run(BillingComputationService $billing, OverdueMonitoringService $monitor): void
    {
        $connection = DB::connection();
        if (! app()->isLocal() || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'water_billing_systems'
            || ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new RuntimeException('This seeder runs only in the local ViaFlow MySQL database.');
        }

        DB::transaction(function () use ($billing, $monitor): void {
            $this->seedAccount('MT-8989', '24', [8, 10, 9], true, $billing, $monitor);
            $this->seedAccount('MT02', '15', [9, 11, 10], false, $billing, $monitor);
        });
    }

    private function seedAccount(
        string $meter,
        string $accountNumber,
        array $usage,
        bool $expired,
        BillingComputationService $billing,
        OverdueMonitoringService $monitor,
    ): void {
        $consumer = Consumer::query()->where('meter_number', $meter)->lockForUpdate()->sole();
        if ($consumer->account_number !== $accountNumber || ! $consumer->user?->isConsumer()) {
            throw new RuntimeException("The expected demo consumer for {$meter} could not be confirmed.");
        }

        $prefix = Bill::DEMO_DISCONNECTION_PRN_PREFIX.$meter.'-';
        $sampleBills = $consumer->bills()->where('prn', 'like', $prefix.'%')->get();
        if ($sampleBills->count() === count($usage)
            && $consumer->disconnectionNotices()->whereIn('bill_id', $sampleBills->modelKeys())->exists()) {
            return;
        }

        $waterBills = $consumer->bills()->where('bill_type', 'Water');
        if ($consumer->status !== 'Active' || $sampleBills->isNotEmpty() || $consumer->disconnectionNotices()->exists()
            || (clone $waterBills)->whereBetween('billing_period', ['2026-08-01', '2026-10-31'])->exists()
            || app(ConsumerBalanceService::class)->recordedOutstandingFor($consumer->id) > 0) {
            throw new RuntimeException("Existing records for {$meter} overlap the demo disconnection cycle.");
        }

        $rate = $consumer->membership_type === 'Member'
            ? BillingComputationService::MEMBER_UNIT_RATE
            : BillingComputationService::NON_MEMBER_UNIT_RATE;
        $previous = null;
        foreach ($usage as $index => $units) {
            $period = Carbon::create(2026, 8 + $index, 1);
            $priorBalance = $previous?->balance_due ?? 0.0;
            $charges = $billing->calculate($units, BillingComputationService::DEFAULT_BASE_CHARGE, $rate, $priorBalance);

            $bill = $consumer->bills()->create([
                'bill_type' => 'Water',
                'billing_period' => $period,
                'due_date' => $period->copy()->day(25),
                'prn' => $prefix.$period->format('Ym'),
                'amount' => $charges['total_due'],
                'base_charge' => $charges['base_charge'],
                'water_charge' => $charges['usage_charge'],
                'arrears_amount' => $charges['previous_balance'],
                'penalty_amount' => $charges['penalty_amount'],
                'carried_forward_from_bill_id' => $previous?->id,
                'units_consumed' => $units,
                'unit_rate' => $rate,
                'status' => 'Overdue',
            ]);
            $previous?->forceFill([
                'carried_forward_to_bill_id' => $bill->id,
                'carried_balance_snapshot' => $priorBalance,
                'carried_paid_snapshot' => 0,
                'status' => 'Paid',
            ])->save();
            $previous = $bill;
        }

        $monitor->evaluate($consumer);
        $notice = $consumer->activeDisconnectionNotice();
        if (! $notice || $notice->bill_id !== $previous->id) {
            throw new RuntimeException("The demo bills for {$meter} did not produce the expected disconnection notice.");
        }

        if ($expired) {
            $deadline = now()->subDay();
            $notice->update([
                'issued_at' => $deadline->copy()->subDays(ConsumerBalanceService::PAYMENT_GRACE_DAYS),
                'deadline_at' => $deadline,
            ]);
        }
    }
}
