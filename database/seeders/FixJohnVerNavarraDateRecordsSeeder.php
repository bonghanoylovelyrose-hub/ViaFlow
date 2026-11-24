<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixJohnVerNavarraDateRecordsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $consumer = Consumer::query()
                ->where('meter_number', 'MT-2233')
                ->first();

            if (! $consumer) {
                return;
            }

            $currentBill = Bill::query()
                ->with('meterReading')
                ->where('consumer_id', $consumer->id)
                ->where('billing_period', now()->startOfMonth()->toDateString())
                ->orderByDesc('id')
                ->first();

            if ($currentBill?->meterReading) {
                $currentBill->meterReading->update([
                    'reading_date' => now()->startOfMonth()->day(9)->toDateString(),
                ]);
            }

            Payment::query()
                ->where('reference_number', 'PAY-0038')
                ->where('consumer_id', $consumer->id)
                ->update([
                    'payment_date' => now()->startOfMonth()->day(9)->toDateString(),
                ]);
        });
    }
}
