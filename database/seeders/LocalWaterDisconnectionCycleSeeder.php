<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Models\MeterReading;
use App\Services\BillingComputationService;
use App\Services\ConsumerBalanceService;
use App\Services\DisconnectionNoticeService;
use App\Services\WaterBillingLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class LocalWaterDisconnectionCycleSeeder extends Seeder
{
    protected const USE_RECORDED_ISSUE_DATE = false;

    /** @return array{meter: string, name: string, original_prn: string} */
    abstract protected function account(): array;

    public function run(BillingComputationService $billing, ConsumerBalanceService $balances, DisconnectionNoticeService $notices): void
    {
        $database = DB::connection();
        if (! app()->isLocal() || $database->getDriverName() !== 'mysql'
            || $database->getDatabaseName() !== 'water_billing_systems' || $database->getConfig('host') !== '127.0.0.1'
            || strcasecmp($database->selectOne('SELECT @@hostname AS host')->host, gethostname()) !== 0) {
            throw new RuntimeException('This seeder requires the local ViaFlow development database.');
        }

        $account = $this->account();
        $meter = $account['meter'];
        $prn = Bill::DEMO_DISCONNECTION_PRN_PREFIX.$meter.'-202611';
        $notice = DB::transaction(function () use ($billing, $balances, $notices, $account, $meter, $prn): DisconnectionNotice {
            $consumer = Consumer::query()->where('meter_number', $meter)->where('full_name', $account['name'])->lockForUpdate()->sole();
            $bills = $consumer->bills()->withBillingFixtures()->where('bill_type', 'Water');
            $alreadyIssued = (clone $bills)->where('prn', $prn)->first();
            if ($alreadyIssued) {
                return $consumer->disconnectionNotices()->where('bill_id', $alreadyIssued->id)->sole();
            }

            $original = (clone $bills)->where('prn', $account['original_prn'])->with('meterReading')->sole();
            $fixture = fn (string $cycle) => (clone $bills)->where('prn', 'DEMO-WATER-'.$meter.'-'.$cycle)
                ->onlyBillingFixtures()->with('meterReading')->sole();
            $september = $fixture('202609');
            $october = $fixture('202610');
            $projection = $fixture('202611');
            $oldNotice = $consumer->disconnectionNotices()->onlyBillingFixtures()->where('bill_id', $october->id)->active()->sole();

            if ($consumer->status !== 'Active' || $consumer->disconnected_at || $consumer->reconnection_pending
                || $consumer->disconnectionNotices()->active()->exists() || $oldNotice->source !== DisconnectionNotice::SOURCE_WATER
                || (clone $bills)->count() !== 4 || (clone $bills)->whereHas('payments', fn ($query) => $query->where('status', 'Verified'))->exists()
                || $september->carried_forward_to_bill_id !== $october->id || $october->carried_forward_to_bill_id !== $projection->id
                || $original->superseded_by_bill_id !== $projection->id || $projection->superseded_by_bill_id !== null
                || $original->meterReading?->reading_date?->toDateString() !== '2026-11-23'
                || $original->meterReading->status !== MeterReading::STATUS_ENCODED || ! $original->meterReading->submitted_at
                || ! $september->meterReading?->is_billing_fixture || ! $october->meterReading?->is_billing_fixture
                || ! $projection->meterReading?->is_billing_fixture) {
                throw new RuntimeException("{$meter} no longer matches the expected development records. No changes were made.");
            }

            $baseCharge = BillingComputationService::DEFAULT_BASE_CHARGE;
            $unitRate = $consumer->hasVerifiedMembership() ? BillingComputationService::MEMBER_UNIT_RATE : BillingComputationService::NON_MEMBER_UNIT_RATE;
            $previousBalance = 0.0;
            foreach ([$september, $october] as $bill) {
                $reading = $bill->meterReading;
                $units = (float) $reading->current_reading - (float) $reading->previous_reading;
                $charges = $billing->calculate($units, $baseCharge, $unitRate, $previousBalance);
                if ($units <= 0 || $charges['total_due'] !== (float) $bill->amount || $charges['current_charges'] !== (float) $bill->water_charge
                    || $previousBalance !== (float) $bill->arrears_amount || ! $balances->isOverdue($bill)) {
                    throw new RuntimeException('The historical fixture bills must reproduce the approved charges and be overdue.');
                }
                $previousBalance = $charges['total_due'];
            }

            // Preserve the earlier capture and use its November consumption on the test ledger's cumulative meter scale.
            $recordedReading = $original->meterReading;
            $previousReading = (float) $october->meterReading->current_reading;
            $units = (float) $recordedReading->current_reading - (float) $recordedReading->previous_reading;
            if ($units <= 0 || $units !== (float) $recordedReading->consumption
                || (float) $projection->meterReading->current_reading !== $previousReading + $units) {
                throw new RuntimeException('The captured November usage and development meter sequence do not agree.');
            }
            $reading = $consumer->meterReadings()->create([
                'reader_id' => $recordedReading->reader_id,
                'meter_number' => $meter,
                'reading_date' => $recordedReading->reading_date,
                'submitted_at' => $recordedReading->submitted_at,
                'previous_reading' => $previousReading,
                'current_reading' => $previousReading + $units,
                'consumption' => $units,
                'status' => MeterReading::STATUS_ENCODED,
                'remarks' => 'Development test reading. Uses the November 23 captured consumption with the October test closing reading.',
            ]);

            $charges = $billing->calculate($units, $baseCharge, $unitRate, $previousBalance);
            $november = $consumer->bills()->make([
                'bill_type' => 'Water',
                'meter_reading_id' => $reading->id,
                'billing_period' => WaterBillingLedger::NOVEMBER_PERIOD,
                'due_date' => $original->due_date,
                'prn' => $prn,
                'amount' => $charges['total_due'],
                'base_charge' => $charges['base_charge'],
                'water_charge' => $charges['current_charges'],
                'arrears_amount' => $charges['previous_balance'],
                'penalty_amount' => $charges['penalty_amount'],
                'carried_forward_from_bill_id' => $october->id,
                'units_consumed' => $units,
                'unit_rate' => $unitRate,
                'status' => 'Unpaid',
            ]);
            $recordedIssueTime = static::USE_RECORDED_ISSUE_DATE ? $original->created_at : null;
            if ($recordedIssueTime) {
                $november->created_at = $recordedIssueTime;
            }
            $november->save();
            $october->carryBalanceInto($november);
            // Add the existing historical test bills to the normal ledger so the notice and payment rules use all three months.
            foreach ([$september, $october] as $bill) {
                $bill->forceFill(['is_billing_fixture' => false, 'is_demo' => false])->save();
                $bill->meterReading->forceFill(['is_billing_fixture' => false])->save();
            }
            (clone $bills)->whereKey([$original->id, $projection->id])->toBase()->update(['superseded_by_bill_id' => $november->id]);
            $oldNotice->forceFill(['status' => 'Superseded'])->save();

            return $this->recordNotice($consumer, $november, $balances, $notices, $charges['total_due'], $recordedIssueTime);
        });

        $issued = $notice->issued_at->copy()->timezone('Asia/Manila')->toDateTimeString();
        $deadline = $notice->deadline_at->copy()->timezone('Asia/Manila')->toDateTimeString();
        $this->command?->info($meter." notice #{$notice->id}, issued {$issued}, deadline {$deadline} Philippine time.");
    }

    private function recordNotice(Consumer $consumer, Bill $november, ConsumerBalanceService $balances, DisconnectionNoticeService $notices, float $outstanding, ?Carbon $recordedIssueTime): DisconnectionNotice
    {
        $createdAt = now();
        $previousClock = Date::getTestNow();
        if ($recordedIssueTime) {
            Date::setTestNow($recordedIssueTime);
        }

        try {
            $standing = $balances->waterNoticeStanding($consumer->id);
            if (! $standing['notice_bill']?->is($november) || $balances->totalOutstandingFor($consumer->id) !== $outstanding) {
                throw new RuntimeException('The November bill did not qualify with the three-cycle rule or the outstanding balance differs.');
            }

            // The test notice is stored separately from any delivery event.
            $notice = $notices->raiseNotice($consumer, $standing['months_unpaid'], $november);
            if (! $notice || ! $notice->deadline_at?->equalTo($notice->issued_at->copy()->addDays(ConsumerBalanceService::PAYMENT_GRACE_DAYS))
                || $notice->issued_at->lt($november->created_at)) {
                throw new RuntimeException('A full three-day notice could not be recorded after the November bill.');
            }
        } finally {
            Date::setTestNow($previousClock);
        }

        $notice->forceFill([
            'created_at' => $createdAt,
            'notes' => 'Local water billing demonstration. The recorded test notice does not represent notification delivery.',
        ])->save();
        $november->forceFill(['months_unpaid' => $standing['months_unpaid'], 'notice_level' => ConsumerBalanceService::DISCONNECTION_NOTICE])->save();

        return $notice;
    }
}
