<?php
namespace App\Services;
use App\Models\Bill;
use App\Models\Consumer;
use App\Models\MeterReading;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WaterBillCycleAdjuster
{
    public function adjust(int $billId, Carbon $period, ?Carbon $dueDate = null): Bill
    {
        return DB::transaction(function () use ($billId, $period, $dueDate) {
            $candidate = Bill::query()->findOrFail($billId);
            Consumer::query()->whereKey($candidate->consumer_id)->lockForUpdate()->firstOrFail();
            $bill = Bill::query()->whereKey($billId)->lockForUpdate()->firstOrFail();
            if ($bill->bill_type !== 'Water' || ! $bill->meter_reading_id) {
                throw new InvalidArgumentException('A water bill with a linked reading is required.');
            }
            $reading = MeterReading::query()->whereKey($bill->meter_reading_id)->lockForUpdate()->firstOrFail();
            if ($reading->consumer_id !== $bill->consumer_id) {
                throw new InvalidArgumentException('The reading belongs to a different consumer.');
            }
            $oldPeriod = $bill->billing_period->copy()->startOfMonth();
            $newPeriod = $period->copy()->startOfMonth();
            $months = ($newPeriod->year - $oldPeriod->year) * 12 + $newPeriod->month - $oldPeriod->month;
            $newDue = $dueDate?->copy()->startOfDay() ?? $bill->due_date->copy()->addMonthsNoOverflow($months)->startOfDay();
            if ($newDue->lt($newPeriod)) {
                throw new InvalidArgumentException('The deadline cannot be before the billing period.');
            }
            if ($months !== 0) {
                if ($bill->carried_forward_from_bill_id || $bill->carried_forward_to_bill_id || (float) $bill->arrears_amount > 0) {
                    throw new InvalidArgumentException('A bill with carried balances cannot be moved automatically.');
                }
                $earliest = $oldPeriod->lt($newPeriod) ? $oldPeriod : $newPeriod;
                $otherBill = Bill::query()->where('consumer_id', $bill->consumer_id)->where('bill_type', 'Water')
                    ->whereKeyNot($bill->id)->whereDate('billing_period', '>=', $earliest->toDateString())->exists();
                $otherReading = MeterReading::query()->where('consumer_id', $bill->consumer_id)->whereKeyNot($reading->id)
                    ->whereDate('reading_date', '>=', $earliest->toDateString())->exists();
                if ($otherBill || $otherReading) {
                    throw new InvalidArgumentException('A conflicting or later bill or reading prevents this adjustment.');
                }
            }
            $newReadingDate = $newPeriod->copy()->addDays(min($reading->reading_date->day, $newPeriod->daysInMonth) - 1);
            DB::table('bills')->where('id', $bill->id)->update(['billing_period' => $newPeriod->toDateString(), 'due_date' => $newDue->toDateString()]);
            DB::table('meter_readings')->where('id', $reading->id)->update(['reading_date' => $newReadingDate->toDateString()]);
            $bill->refresh();
            DB::table('bills')->where('id', $bill->id)->update(['status' => $bill->computedStatus()]);
            return $bill->refresh();
        });
    }
}
