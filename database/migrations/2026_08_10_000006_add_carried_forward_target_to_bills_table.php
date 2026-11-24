<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A balance that a newer bill absorbs as its arrears carry-forward has MOVED;
 * it has not been duplicated. This column records where each carried balance
 * went, so the older bill can be closed instead of being left looking unpaid
 * while also being counted again inside the newer bill.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bills', 'carried_forward_to_bill_id')) {
            Schema::table('bills', function (Blueprint $table): void {
                $table->foreignId('carried_forward_to_bill_id')
                    ->nullable()
                    ->after('carried_forward_from_bill_id')
                    ->constrained('bills')
                    ->nullOnDelete();
            });
        }

        $this->backfill();
    }

    /**
     * Closes historical bills whose balance a later bill already absorbed, so
     * no existing debt is counted twice after the change.
     */
    private function backfill(): void
    {
        if (DB::table('bills')->whereNotNull('carried_forward_to_bill_id')->exists()) {
            return;
        }

        $consumerIds = DB::table('bills')->distinct()->pluck('consumer_id');

        foreach ($consumerIds as $consumerId) {
            $paidByBill = DB::table('payments')
                ->where('consumer_id', $consumerId)
                ->where('status', 'Verified')
                ->groupBy('bill_id')
                ->selectRaw('bill_id, COALESCE(SUM(amount), 0) AS paid')
                ->pluck('paid', 'bill_id');

            $bills = DB::table('bills')
                ->where('consumer_id', $consumerId)
                ->orderBy('billing_period')
                ->orderBy('id')
                ->get()
                ->sortByDesc(fn ($bill) => sprintf('%s-%010d', (string) $bill->billing_period, $bill->id))
                ->values();

            // Newest first: $absorbed is the debt already represented inside a
            // newer bill, $absorber is the bill that now holds it.
            $absorbed = 0.0;
            $absorber = null;
            $closures = [];

            foreach ($bills as $bill) {
                $balance = round((float) $bill->amount - (float) ($paidByBill[$bill->id] ?? 0), 2);
                $arrears = round((float) ($bill->arrears_amount ?? 0), 2);

                if ($balance > 0 && $absorbed >= $balance && $absorber !== null) {
                    $closures[$bill->id] = $absorber->id;
                } elseif ($balance > 0) {
                    // This bill still holds live debt of its own.
                    $absorber = $bill;
                }

                // A bill that absorbed older debt remains the destination for
                // them even once it has itself been paid in full.
                if ($arrears > 0) {
                    $absorber = $bill;
                }

                $absorbed = round($absorbed + $arrears, 2);
            }

            foreach ($closures as $billId => $absorberId) {
                DB::table('bills')->where('id', $billId)->update([
                    'status' => 'Paid',
                    'carried_forward_to_bill_id' => $absorberId,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('carried_forward_to_bill_id');
        });
    }
};