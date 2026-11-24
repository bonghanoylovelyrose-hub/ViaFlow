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

class JohnVerNavarraBillingSeeder extends Seeder
{
    private const BASE_CHARGE = 150.00;

    private const UNIT_RATE = 20.00;

    private const MINIMUM_CHARGE = 150.00;

    private const PENALTY_RATE = 0.10;

    private const PREVIOUS_BALANCE = 250.00;

    public function run(): void
    {
        DB::transaction(function (): void {
            $admin = User::query()->firstOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'System Admin',
                    'role' => 'admin',
                    'password' => 'password',
                ],
            );

            $reader = User::query()->firstOrCreate(
                ['email' => 'meter.reader@example.com'],
                [
                    'name' => 'Meter Reader',
                    'role' => 'meter_reader',
                    'password' => 'password',
                ],
            );

            if ($admin->role !== 'admin') {
                $admin->forceFill(['role' => 'admin'])->save();
            }

            if (! in_array($reader->role, ['staff', 'meter_reader'], true)) {
                $reader->forceFill(['role' => 'meter_reader'])->save();
            }

            $consumerUser = User::query()->firstOrCreate(
                ['email' => 'john.ver.navarra@example.com'],
                [
                    'name' => 'John Ver Navarra',
                    'role' => 'consumer',
                    'password' => 'password',
                ],
            );

            $consumer = Consumer::query()->updateOrCreate(
                ['meter_number' => 'MT-2233'],
                [
                    'user_id' => $consumerUser->id,
                    'full_name' => 'John Ver Navarra',
                    'email' => 'john.ver.navarra@example.com',
                    'account_number' => 'JVN-2233',
                    'status' => 'Active',
                    'membership_type' => 'Non-Member',
                    'service_address' => 'Via Vida Village',
                    'contact_number' => '09170002233',
                ],
            );

            $previousPeriod = now()->startOfMonth()->subMonth();
            $currentPeriod = now()->startOfMonth();

            Bill::query()
                ->where('consumer_id', $consumer->id)
                ->where('prn', 'JVN-'.$currentPeriod->format('ym').'-CURRENT')
                ->delete();

            MeterReading::query()
                ->where('consumer_id', $consumer->id)
                ->whereBetween('reading_date', [
                    $currentPeriod->copy()->startOfMonth()->toDateString(),
                    $currentPeriod->copy()->endOfMonth()->toDateString(),
                ])
                ->where('remarks', 'John Ver Navarra sample current reading')
                ->delete();

            $previousReading = $this->readingFor(
                $consumer,
                $reader,
                $previousPeriod->copy()->day(8),
                100.00,
                115.00,
                'John Ver Navarra sample previous reading',
            );

            $previousWaterCharge = $this->waterCharge(15.00);
            $previousPaymentAmount = round($previousWaterCharge - self::PREVIOUS_BALANCE, 2);

            $previousBill = Bill::query()->updateOrCreate(
                ['prn' => 'JVN-'.$previousPeriod->format('ym').'-PREV'],
                [
                    'consumer_id' => $consumer->id,
                    'meter_reading_id' => $previousReading->id,
                    'billing_period' => $previousPeriod->toDateString(),
                    'due_date' => $previousPeriod->copy()->endOfMonth()->toDateString(),
                    'amount' => $previousWaterCharge,
                    'base_charge' => self::BASE_CHARGE,
                    'water_charge' => $previousWaterCharge,
                    'arrears_amount' => 0.00,
                    'penalty_amount' => 0.00,
                    'units_consumed' => 15.00,
                    'unit_rate' => self::UNIT_RATE,
                    'status' => 'Unpaid',
                    'months_unpaid' => 0,
                ],
            );

            Payment::query()->updateOrCreate(
                ['reference_number' => 'JVN-'.$previousPeriod->format('ym').'-PARTIAL'],
                [
                    'bill_id' => $previousBill->id,
                    'consumer_id' => $consumer->id,
                    'receipt_number' => 'JVN-'.$previousPeriod->format('ym').'-PARTIAL-RCP',
                    'amount' => $previousPaymentAmount,
                    'payment_date' => $previousPeriod->copy()->endOfMonth()->subDays(3)->toDateString(),
                    'payment_method' => 'Cash',
                    'payment_channel' => 'Counter',
                    'status' => 'Verified',
                    'verified_at' => $previousPeriod->copy()->endOfMonth()->subDays(3)->setTime(10, 0),
                    'verified_by' => $admin->id,
                    'notes' => 'Leaves a sample previous balance for carryover billing.',
                ],
            );

            $previousBill->refresh()->syncPaymentStatus();
            $previousBalance = $previousBill->refresh()->balance_due;
            $currentConsumption = 12.00;
            $currentWaterCharge = $this->waterCharge($currentConsumption);
            $penaltyAmount = round($previousBalance * self::PENALTY_RATE, 2);

            $currentReading = $this->readingForPeriod(
                $consumer,
                $reader,
                $currentPeriod->copy()->day(9),
                115.00,
                127.00,
                'John Ver Navarra sample current reading',
            );

            $currentBill = Bill::query()->firstOrNew([
                'meter_reading_id' => $currentReading->id,
            ]);

            $currentBill->fill([
                'consumer_id' => $consumer->id,
                'billing_period' => $currentPeriod->toDateString(),
                'due_date' => $currentPeriod->copy()->endOfMonth()->toDateString(),
                'prn' => $currentBill->exists ? $currentBill->prn : 'JVN-'.$currentPeriod->format('ym').'-CURRENT',
                'amount' => round($currentWaterCharge + $previousBalance + $penaltyAmount, 2),
                'base_charge' => self::BASE_CHARGE,
                'water_charge' => $currentWaterCharge,
                'arrears_amount' => $previousBalance,
                'penalty_amount' => $penaltyAmount,
                'units_consumed' => $currentConsumption,
                'unit_rate' => self::UNIT_RATE,
                'status' => 'Unpaid',
                'months_unpaid' => 0,
            ])->save();

            $consumer->refresh()->syncAccountStanding();
        });
    }

    private function readingFor(
        Consumer $consumer,
        User $reader,
        Carbon $readingDate,
        float $previousReading,
        float $currentReading,
        string $remarks,
    ): MeterReading {
        return MeterReading::query()->updateOrCreate(
            [
                'consumer_id' => $consumer->id,
                'reading_date' => $readingDate->toDateString(),
            ],
            [
                'reader_id' => $reader->id,
                'previous_reading' => $previousReading,
                'current_reading' => $currentReading,
                'consumption' => round($currentReading - $previousReading, 2),
                'status' => 'Encoded',
                'remarks' => $remarks,
                'meter_photo_path' => 'meter-readings/john-ver-navarra-'.$readingDate->format('Ym').'.jpg',
            ],
        );
    }

    private function readingForPeriod(
        Consumer $consumer,
        User $reader,
        Carbon $readingDate,
        float $previousReading,
        float $currentReading,
        string $remarks,
    ): MeterReading {
        $meterReading = MeterReading::query()
            ->where('consumer_id', $consumer->id)
            ->whereBetween('reading_date', [
                $readingDate->copy()->startOfMonth()->toDateString(),
                $readingDate->copy()->endOfMonth()->toDateString(),
            ])
            ->orderBy('id')
            ->first();

        if (! $meterReading) {
            $meterReading = new MeterReading([
                'consumer_id' => $consumer->id,
                'reading_date' => $readingDate->toDateString(),
            ]);
        }

        $meterReading->fill([
            'reader_id' => $reader->id,
            'reading_date' => $readingDate->toDateString(),
            'previous_reading' => $previousReading,
            'current_reading' => $currentReading,
            'consumption' => round($currentReading - $previousReading, 2),
            'status' => 'Encoded',
            'remarks' => $remarks,
            'meter_photo_path' => 'meter-readings/john-ver-navarra-'.$readingDate->format('Ym').'.jpg',
        ])->save();

        return $meterReading;
    }

    private function waterCharge(float $consumption): float
    {
        return max(self::MINIMUM_CHARGE, round(self::BASE_CHARGE + ($consumption * self::UNIT_RATE), 2));
    }
}
