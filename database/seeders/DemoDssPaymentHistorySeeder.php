<?php

namespace Database\Seeders;

use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\User;
use App\Services\BillingComputationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DemoDssPaymentHistorySeeder extends Seeder
{
    public function run(BillingComputationService $billing): void
    {
        if (! app()->isLocal() || ! in_array(DB::connection()->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)
            || DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'water_billing_systems') {
            throw new RuntimeException('This seeder runs only in the local ViaFlow MySQL database.');
        }

        $admin = User::where('role', 'admin')->oldest('id')->firstOrFail();
        $reader = User::whereIn('role', ['meter_reader', 'staff'])->oldest('id')->firstOrFail();
        $accounts = [
            'MT-1313' => ['Daniel Padila', 118, [11, 14, 12, 16, 13]],
            'MT-1212' => ['Liza Soberano', 204, [9, 12, 10, 13, 11]],
        ];

        DB::transaction(function () use ($accounts, $admin, $reader, $billing): void {
            foreach ($accounts as $meter => [$name, $openingReading, $usage]) {
                $consumer = Consumer::where('meter_number', $meter)->lockForUpdate()->sole();
                if ($consumer->full_name !== $name || $consumer->status !== 'Active' || ! $consumer->isVerifiedViohoaMember()
                    || ! $consumer->user?->isConsumer()) {
                    throw new RuntimeException("The expected active demo account {$meter} could not be verified.");
                }

                $prefix = 'DEMO-DSS-'.substr($meter, 3).'-';
                $history = $consumer->bills()->where('bill_type', 'Water');
                if ((clone $history)->where('prn', 'like', $prefix.'%')->count() === 5) {
                    continue;
                }
                if ($history->exists() || $consumer->payments()->exists() || $consumer->meterReadings()->exists() || $consumer->loans()->exists()) {
                    throw new RuntimeException("Unexpected existing transactions on {$meter}. No demo history was inserted.");
                }

                $this->addHistory($consumer, $admin, $reader, $billing, $prefix, $openingReading, $usage);
                $consumer->loans()->create([
                    'applied_by_user_id' => $consumer->user_id,
                    'amount' => 1200,
                    'term_months' => 6,
                    'monthly_installment' => 200,
                    'purpose' => 'Local demo: payment history DSS evaluation',
                    'status' => 'Pending Review',
                    'review_status' => 'Pending Review',
                    'income_verification_status' => 'Pending Verification',
                    'proof_of_income_path' => 'loan-income-proofs/demo-dss-income.png',
                    'applied_at' => now(),
                ]);
            }

            $this->writeDemoDocument();
        });
    }

    private function addHistory(Consumer $consumer, User $admin, User $reader, BillingComputationService $billing, string $prefix, int $previous, array $usage): void
    {
        $paymentDays = [10, 20, 24];
        $unpaidOctober = null;
        foreach ($usage as $index => $consumption) {
            $period = Carbon::create(2026, 7 + $index, 1);
            $readingDate = $period->copy()->day(3)->setTime(9, 0);
            $reading = $consumer->meterReadings()->create([
                'meter_number' => $consumer->meter_number,
                'reader_id' => $reader->id,
                'reading_date' => $readingDate->toDateString(),
                'submitted_at' => $readingDate,
                'previous_reading' => $previous,
                'current_reading' => $previous + $consumption,
                'consumption' => $consumption,
                'status' => MeterReading::STATUS_ENCODED,
                'remarks' => 'Local DSS demonstration reading.',
            ]);
            $reading->forceFill(['created_at' => $readingDate])->save();
            $previous += $consumption;

            $charges = $billing->calculate(
                $consumption,
                BillingComputationService::DEFAULT_BASE_CHARGE,
                BillingComputationService::MEMBER_UNIT_RATE,
                (float) ($unpaidOctober?->balance_due ?? 0),
            );
            $reference = $prefix.$period->format('Ym');
            $bill = $consumer->bills()->create([
                'bill_type' => 'Water',
                'meter_reading_id' => $reading->id,
                'billing_period' => $period,
                'due_date' => $period->copy()->day(12),
                'prn' => $reference,
                'amount' => $charges['total_due'],
                'base_charge' => $charges['base_charge'],
                'water_charge' => $charges['usage_charge'],
                'arrears_amount' => $charges['previous_balance'],
                'penalty_amount' => $charges['penalty_amount'],
                'units_consumed' => $consumption,
                'unit_rate' => BillingComputationService::MEMBER_UNIT_RATE,
                'carried_forward_from_bill_id' => $unpaidOctober?->id,
                'status' => 'Unpaid',
                'months_unpaid' => 0,
            ]);
            $bill->forceFill(['created_at' => $readingDate])->save();

            if ($unpaidOctober) {
                $unpaidOctober->forceFill([
                    'carried_forward_to_bill_id' => $bill->id,
                    'carried_balance_snapshot' => $charges['previous_balance'],
                    'carried_paid_snapshot' => 0,
                ])->save();
                $unpaidOctober->syncPaymentStatus(false);
            }
            if ($index === 3) {
                $unpaidOctober = $bill;
            }
            if (isset($paymentDays[$index])) {
                $paymentDate = $period->copy()->day($paymentDays[$index])->setTime(10, 0);
                $payment = $bill->payments()->create([
                    'consumer_id' => $consumer->id,
                    'payment_type' => 'Water',
                    'reference_number' => $reference.'-PAY',
                    'receipt_number' => $reference.'-RCP',
                    'amount' => $charges['total_due'],
                    'payment_date' => $paymentDate->toDateString(),
                    'payment_method' => 'Cash',
                    'payment_channel' => 'Counter',
                    'status' => 'Verified',
                    'verified_at' => $paymentDate,
                    'verified_by' => $admin->id,
                    'notes' => 'Local DSS demonstration payment.',
                ]);
                $payment->forceFill(['created_at' => $paymentDate])->save();
            }
            $bill->syncPaymentStatus(false);
        }
    }

    private function writeDemoDocument(): void
    {
        $disk = Storage::disk('local');
        $path = 'loan-income-proofs/demo-dss-income.png';
        if ($disk->exists($path)) {
            return;
        }
        $image = imagecreatetruecolor(640, 230);
        $background = imagecolorallocate($image, 248, 250, 252);
        $ink = imagecolorallocate($image, 20, 38, 65);
        imagefill($image, 0, 0, $background);
        imagestring($image, 5, 24, 32, 'DEMO ONLY: Sample Income Document', $ink);
        imagestring($image, 4, 24, 88, 'Synthetic document for local DSS workflow testing.', $ink);
        imagestring($image, 4, 24, 120, 'This is not a statement of actual income.', $ink);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        if (! $disk->put($path, $png)) {
            throw new RuntimeException('The demo income proof could not be saved.');
        }
    }
}
