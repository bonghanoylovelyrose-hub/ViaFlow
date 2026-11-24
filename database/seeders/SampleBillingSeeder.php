<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SampleBillingSeeder extends Seeder
{
    private const BASE_CHARGE = 150.00;

    private const MEMBER_UNIT_RATE = 18.00;

    private const NON_MEMBER_UNIT_RATE = 20.00;

    private const MINIMUM_CHARGE = 150.00;

    private const PENALTY_RATE = 0.10;

    /**
     * Seed dashboard-friendly billing samples and leave current-month consumers available for meter encoding.
     */
    public function run(): void
    {
        $meterReader = User::query()->whereIn('role', ['staff', 'meter_reader'])->orderBy('id')->firstOrFail();
        $verifier = User::query()->where('role', 'admin')->orderBy('id')->firstOrFail();
        $consumers = Consumer::query()->orderBy('account_number')->get();

        if ($consumers->isEmpty()) {
            $this->command?->warn('SampleBillingSeeder skipped: at least 1 consumer is required.');

            return;
        }

        DB::transaction(function () use ($meterReader, $verifier, $consumers): void {
            $this->purgePreviousSampleData();

            foreach ($consumers as $index => $consumer) {
                $previousReading = 90 + ($index * 35);
                $carryForwardBalance = 0.00;

                foreach (range(5, 0) as $offset) {
                    if ($offset === 0 && $index >= 3) {
                        continue;
                    }

                    $billingMonth = now()->startOfMonth()->subMonths($offset);
                    $readingDate = $billingMonth->copy()->day(min(9 + $index, $billingMonth->daysInMonth));
                    $consumption = 8 + (($index + $offset) % 6) + ($offset === 0 ? 2 : 0);
                    $currentReading = $previousReading + $consumption;
                    $unitRate = $consumer->membership_type === 'Member'
                        ? self::MEMBER_UNIT_RATE
                        : self::NON_MEMBER_UNIT_RATE;
                    $waterCharge = max(self::MINIMUM_CHARGE, round(self::BASE_CHARGE + ($consumption * $unitRate), 2));
                    $arrearsAmount = round($carryForwardBalance, 2);
                    $penaltyAmount = $arrearsAmount > 0 ? round($arrearsAmount * self::PENALTY_RATE, 2) : 0.00;
                    $amount = round($waterCharge + $arrearsAmount + $penaltyAmount, 2);

                    $reading = MeterReading::query()->create([
                        'consumer_id' => $consumer->id,
                        'reader_id' => $meterReader->id,
                        'reading_date' => $readingDate->toDateString(),
                        'previous_reading' => $previousReading,
                        'current_reading' => $currentReading,
                        'consumption' => $consumption,
                        'status' => 'Encoded',
                        'remarks' => 'Sample billing seed',
                        'meter_photo_path' => 'meter-readings/sample-'.$consumer->id.'-'.$billingMonth->format('Ym').'.jpg',
                    ]);

                    $bill = Bill::query()->create([
                        'consumer_id' => $consumer->id,
                        'meter_reading_id' => $reading->id,
                        'billing_period' => $billingMonth->copy()->startOfMonth()->toDateString(),
                        'due_date' => $billingMonth->copy()->endOfMonth()->toDateString(),
                        'prn' => sprintf('SAMPLE-%s-%04d', $billingMonth->format('ym'), $consumer->id),
                        'amount' => $amount,
                        'base_charge' => self::BASE_CHARGE,
                        'water_charge' => $waterCharge,
                        'arrears_amount' => $arrearsAmount,
                        'penalty_amount' => $penaltyAmount,
                        'units_consumed' => $consumption,
                        'unit_rate' => $unitRate,
                        'status' => 'Unpaid',
                        'months_unpaid' => 0,
                    ]);

                    [$paymentAmount, $paymentDate] = $this->samplePaymentFor($index, $offset, $amount, $billingMonth);

                    if ($paymentAmount > 0) {
                        Payment::query()->create([
                            'bill_id' => $bill->id,
                            'consumer_id' => $consumer->id,
                            'reference_number' => sprintf('SAMPLE-REF-%s-%04d', $billingMonth->format('ym'), $consumer->id),
                            'receipt_number' => sprintf('SAMPLE-RCP-%s-%04d', $billingMonth->format('ym'), $consumer->id),
                            'amount' => $paymentAmount,
                            'payment_date' => $paymentDate->toDateString(),
                            'payment_method' => 'Cash',
                            'payment_channel' => 'Counter',
                            'status' => 'Verified',
                            'verified_at' => Carbon::parse($paymentDate->toDateString())->setTime(10, 30),
                            'verified_by' => $verifier->id,
                            'notes' => 'Sample billing seed',
                        ]);
                    }

                    $bill->refresh()->syncPaymentStatus();
                    $carryForwardBalance = $bill->refresh()->balance_due;
                    $previousReading = $currentReading;
                }

                $consumer->refresh()->syncAccountStanding();
            }
        });

        $this->command?->info('Seeded sample meter readings, billing records, and verified payments for dashboard testing.');
    }

    private function samplePaymentFor(int $consumerIndex, int $offset, float $amount, Carbon $billingMonth): array
    {
        $profile = $consumerIndex % 3;
        $dueDate = $billingMonth->copy()->endOfMonth();

        if ($profile === 0) {
            return [
                $amount,
                $dueDate->copy()->subDays(5),
            ];
        }

        if ($profile === 1) {
            return [
                $amount,
                $offset <= 2 ? $dueDate->copy()->addDays(4) : $dueDate->copy()->subDays(3),
            ];
        }

        if ($offset <= 2) {
            return [
                round($amount * 0.45, 2),
                $dueDate->copy()->addDays(7),
            ];
        }

        return [
            round($amount * 0.75, 2),
            $dueDate->copy()->subDays(2),
        ];
    }

    private function purgePreviousSampleData(): void
    {
        Payment::query()
            ->where('reference_number', 'like', 'SAMPLE-REF-%')
            ->delete();

        Bill::query()
            ->where('prn', 'like', 'SAMPLE-%')
            ->delete();

        MeterReading::query()
            ->where('remarks', 'Sample billing seed')
            ->delete();
    }
}
