<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Services\BillingComputationService;
use App\Services\ConsumerBalanceService;
use App\Services\ConsumerStatusService;
use App\Services\DisconnectionNoticeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NovemberWaterDisconnectionDemoSeeder extends Seeder
{
    public function run(BillingComputationService $billing, ConsumerBalanceService $balances, DisconnectionNoticeService $notices): void
    {
        $connection = DB::connection();
        if (! app()->isLocal() || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'water_billing_systems'
            || ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new RuntimeException('This seeder runs only in the local ViaFlow MySQL database.');
        }

        foreach (['MT-3232' => '14', 'MT-4444' => '17'] as $meter => $account) {
            $result = DB::transaction(function () use ($meter, $account, $billing, $balances, $notices): string {
                $consumer = Consumer::query()->where('account_number', $account)->lockForUpdate()->sole();
                if ($consumer->meter_number !== $meter) {
                    return "Skipped {$meter}. Account {$account} is assigned to {$consumer->meter_number}.";
                }
                if (app(ConsumerStatusService::class)->key($consumer) !== ConsumerStatusService::ACTIVE || ! $consumer->user?->isConsumer()) {
                    return "Skipped {$meter}. The linked consumer must already be active and connected.";
                }

                $issued = Carbon::create(2026, 11, 10, 9, 0, 0, 'Asia/Manila')->utc();
                $deadline = $issued->copy()->addDays(ConsumerBalanceService::PAYMENT_GRACE_DAYS);
                $prefix = Bill::DEMO_DISCONNECTION_PRN_PREFIX.$meter.'-';
                $fixtureBills = $consumer->bills()->whereIn('prn', [$prefix.'202609', $prefix.'202610'])->orderBy('billing_period')->get();
                if ($fixtureBills->isNotEmpty()) {
                    $september = $fixtureBills->first();
                    $october = $fixtureBills->last();
                    $notice = $consumer->disconnectionNotices()->where('bill_id', $october->id)->first();
                    if ($fixtureBills->count() === 2 && $september->carried_forward_to_bill_id === $october->id
                        && $notice?->source === DisconnectionNotice::SOURCE_WATER && $notice->isActive()
                        && $notice->issued_at?->equalTo($issued) && $notice->deadline_at?->equalTo($deadline)
                        && $balances->recordedOutstandingFor($consumer->id, includeDemo: false) === 700.0) {
                        return "{$meter} already has demo bills #{$september->id} and #{$october->id}, and notice #{$notice->id}.";
                    }

                    return "Skipped {$meter}. The existing demonstration has a different billing or notice state.";
                }

                if ($consumer->bills()->where('bill_type', 'Water')->whereBetween('billing_period', ['2026-09-01', '2026-10-31'])->exists()
                    || $balances->recordedOutstandingFor($consumer->id, includeDemo: false) > 0
                    || $consumer->disconnectionNotices()->active()->exists()) {
                    return "Skipped {$meter}. An existing bill or notice conflicts with the demonstration.";
                }

                $rate = $consumer->hasVerifiedMembership() ? BillingComputationService::MEMBER_UNIT_RATE : BillingComputationService::NON_MEMBER_UNIT_RATE;
                $base = BillingComputationService::DEFAULT_BASE_CHARGE;
                // Backfill only these two fixture periods. Normal billing rejects
                // inserting an older cycle after an existing November statement.
                $previous = null;
                foreach ([9 => 300.0, 10 => 400.0] as $month => $currentCharge) {
                    $period = Carbon::create(2026, $month, 1, 0, 0, 0, 'UTC');
                    $units = round(($currentCharge - $base) / $rate, 2);
                    $charges = $billing->calculate($units, $base, $rate, $previous?->balance_due ?? 0.0);
                    if ($charges['current_charges'] !== $currentCharge) {
                        throw new RuntimeException("The current water rate cannot reproduce the expected fixture amount for {$meter}.");
                    }
                    $bill = $consumer->bills()->create([
                        'bill_type' => 'Water', 'billing_period' => $period, 'due_date' => $period->copy()->day(25),
                        'prn' => $prefix.$period->format('Ym'), 'amount' => $charges['total_due'],
                        'base_charge' => $charges['base_charge'], 'water_charge' => $charges['current_charges'],
                        'arrears_amount' => $charges['previous_balance'], 'carried_forward_from_bill_id' => $previous?->id,
                        'units_consumed' => $units, 'unit_rate' => $rate, 'status' => 'Overdue',
                    ]);
                    $previous?->forceFill([
                        'carried_forward_to_bill_id' => $bill->id, 'carried_balance_snapshot' => $charges['previous_balance'],
                        'carried_paid_snapshot' => 0, 'status' => 'Paid',
                    ])->save();
                    $previous = $bill;
                }

                // Use the normal persistence and grace-period calculation.
                // The demo PRN excludes this fixture from outbound mail.
                Date::setTestNow($issued);
                try {
                    $months = $balances->consecutiveUnpaidMonthsFor($consumer->id);
                    $notice = $notices->raiseNotice($consumer, $months, $previous);
                } finally {
                    Date::setTestNow();
                }
                if ($months !== 2 || ! $notice?->isActive() || ! $notice->deadline_at?->equalTo($deadline)
                    || (float) $notice->outstanding_amount !== 700.0 || ! $notice->paymentWindowHasElapsed()) {
                    throw new RuntimeException("The demonstration notice could not be verified for {$meter}.");
                }

                return "{$meter} created demo bills #{$previous->carried_forward_from_bill_id} and #{$previous->id}, and notice #{$notice->id}.";
            });
            $this->command?->line($result);
        }
    }
}
