<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Consumer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReconnectionFeeService
{
    public function assess(Consumer $consumer, ?float $fee): void
    {
        if ($fee !== null && $fee > 0) {
            throw ValidationException::withMessages(['reconnection_fee' => 'Reconnection fees have been removed. No additional reconnection charge is allowed.']);
        }
        $this->waiveOutstandingFor($consumer);
    }

    public function waiveOutstandingFor(Consumer $consumer): void
    {
        DB::transaction(function () use ($consumer): void {
            $account = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
            $feeIds = $account->bills()->where(function ($query) use ($account) {
                $query->where('prn', 'like', 'RECON-FEE-%')->orWhere('id', $account->reconnection_fee_bill_id);
            })->pluck('id')->flip()->all();
            $portions = app(ConsumerBalanceService::class)->remainingBillPortionsFor($account->id);
            foreach ($portions as $billId => $amounts) {
                $waiver = round(array_sum(array_intersect_key($amounts, $feeIds)), 2);
                if ($waiver > 0) {
                    $bill = Bill::query()->findOrFail($billId);
                    $bill->forceFill(['waived_reconnection_fee_amount' => round((float) $bill->waived_reconnection_fee_amount + $waiver, 2)])->save();
                    $bill->syncPaymentStatus(false);
                }
            }
            $account->update(['reconnection_fee' => 0]);
        });
        $consumer->refresh();
    }

    public function removeAllCharges(): void
    {
        Consumer::query()->where(function ($query) {
            $query->whereNotNull('reconnection_fee')->orWhereHas('bills', fn ($query) => $query->where('prn', 'like', 'RECON-FEE-%'));
        })->eachById(fn (Consumer $consumer) => $this->waiveOutstandingFor($consumer));
    }
}
