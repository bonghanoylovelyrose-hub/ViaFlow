<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->string('bill_type')->default('Water')->after('consumer_id');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('payment_type')->default('Water')->after('consumer_id');
            $table->uuid('submission_key')->nullable()->unique();
        });

        if (Schema::hasTable('loan_installments')) {
            DB::table('bills')->orderBy('id')->get()->each(function (object $bill): void {
                $loanAmount = round((float) ($bill->loan_installment_amount ?? 0), 2);
                if ($loanAmount <= 0 && empty($bill->loan_id)) {
                    return;
                }

                $waterAndCarry = round(
                    (float) ($bill->base_charge ?? 0)
                    + (float) ($bill->water_charge ?? 0)
                    + (float) ($bill->arrears_amount ?? 0)
                    + (float) ($bill->penalty_amount ?? 0),
                    2,
                );
                $installment = DB::table('loan_installments')->where('bill_id', $bill->id)->first();
                $installmentAmount = round((float) ($installment->required_amount ?? 0), 2);
                $amount = round((float) $bill->amount, 2);
                $isPureLoanBill = $waterAndCarry === 0.0
                    && (($loanAmount > 0 && abs($amount - $loanAmount) < 0.01)
                        || ($installmentAmount > 0 && abs($amount - $installmentAmount) < 0.01));

                DB::table('bills')->where('id', $bill->id)->update([
                    'bill_type' => $isPureLoanBill ? 'Loan' : 'Review',
                ]);
            });
        }

        DB::table('payments')->orderBy('id')->get()->each(function (object $payment): void {
            $billType = DB::table('bills')->where('id', $payment->bill_id)->value('bill_type');
            DB::table('payments')->where('id', $payment->id)->update([
                'payment_type' => in_array($billType, ['Water', 'Loan', 'Review'], true) ? $billType : 'Review',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['submission_key']);
            $table->dropColumn(['payment_type', 'submission_key']);
        });

        Schema::table('bills', function (Blueprint $table): void {
            $table->dropColumn('bill_type');
        });
    }
};
