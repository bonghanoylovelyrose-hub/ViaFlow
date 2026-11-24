<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->decimal('carried_balance_snapshot', 10, 2)->nullable();
            $table->decimal('carried_paid_snapshot', 10, 2)->nullable();
        });

        // Backfill only when the complete group of source bills can be matched
        // to the saved arrears and all verified payments predate the new bill.
        $destinations = DB::table('bills')->where('bill_type', 'Water')
            ->whereNotNull('carried_forward_to_bill_id')->distinct()->pluck('carried_forward_to_bill_id');
        foreach ($destinations as $destinationId) {
            $destination = DB::table('bills')->where('id', $destinationId)->first();
            if (! $destination || ! $destination->created_at) {
                continue;
            }
            $sources = DB::table('bills')->where('bill_type', 'Water')->where('carried_forward_to_bill_id', $destinationId)->get();
            $snapshots = [];
            foreach ($sources as $source) {
                if ($source->consumer_id !== $destination->consumer_id || (float) $source->waived_reconnection_fee_amount !== 0.0) {
                    $snapshots = [];
                    break;
                }
                $payments = DB::table('payments')->where('bill_id', $source->id)->where('status', 'Verified')->get();
                if ($payments->contains(fn ($payment) => ! $payment->verified_at || $payment->verified_at > $destination->created_at)) {
                    $snapshots = [];
                    break;
                }
                $paid = round((float) $payments->sum('amount'), 2);
                $balance = round(max(0, (float) $source->amount - $paid), 2);
                $snapshots[] = ['id' => $source->id, 'paid' => $paid, 'balance' => $balance];
            }
            if (! $snapshots || abs(round(array_sum(array_column($snapshots, 'balance')) - (float) $destination->arrears_amount, 2)) > 0.001) {
                continue;
            }
            foreach ($snapshots as $snapshot) {
                DB::table('bills')->where('id', $snapshot['id'])->update([
                    'carried_paid_snapshot' => $snapshot['paid'],
                    'carried_balance_snapshot' => $snapshot['balance'],
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['carried_balance_snapshot', 'carried_paid_snapshot']);
        });
    }
};

