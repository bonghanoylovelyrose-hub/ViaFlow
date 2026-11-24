<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('installment_number');
            $table->date('billing_period');
            $table->date('due_date')->nullable();
            $table->decimal('required_amount', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('remaining_balance', 10, 2);
            $table->date('payment_date')->nullable();
            $table->string('status')->default('Billed');
            $table->timestamps();

            $table->unique(['loan_id', 'installment_number']);
            $table->unique(['loan_id', 'billing_period']);
        });

        if (Schema::hasTable('bills')) {
            DB::table('bills')
                ->whereNotNull('loan_id')
                ->where('loan_installment_amount', '>', 0)
                ->orderBy('loan_id')
                ->orderBy('billing_period')
                ->orderBy('id')
                ->get()
                ->groupBy('loan_id')
                ->each(function ($bills): void {
                    $installmentNumber = 1;

                    foreach ($bills as $bill) {
                        $requiredAmount = round((float) $bill->loan_installment_amount, 2);

                        DB::table('loan_installments')->insert([
                            'loan_id' => $bill->loan_id,
                            'bill_id' => $bill->id,
                            'installment_number' => $installmentNumber,
                            'billing_period' => $bill->billing_period,
                            'due_date' => $bill->due_date,
                            'required_amount' => $requiredAmount,
                            'amount_paid' => 0,
                            'remaining_balance' => $requiredAmount,
                            'status' => 'Billed',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $installmentNumber++;
                    }
                });
        }

        if (Schema::hasTable('loan_repayments')) {
            DB::table('loan_repayments')
                ->orderBy('loan_id')
                ->orderBy('payment_date')
                ->orderBy('id')
                ->get()
                ->groupBy('loan_id')
                ->each(function ($repayments): void {
                    foreach ($repayments as $repayment) {
                        $remainingPayment = round((float) $repayment->amount, 2);

                        if ($remainingPayment <= 0) {
                            continue;
                        }

                        $installments = DB::table('loan_installments')
                            ->where('loan_id', $repayment->loan_id)
                            ->where('remaining_balance', '>', 0)
                            ->orderBy('installment_number')
                            ->orderBy('id')
                            ->get();

                        foreach ($installments as $installment) {
                            if ($remainingPayment <= 0) {
                                break;
                            }

                            $allocated = round(min($remainingPayment, (float) $installment->remaining_balance), 2);

                            if ($allocated <= 0) {
                                continue;
                            }

                            $amountPaid = round((float) $installment->amount_paid + $allocated, 2);
                            $remainingBalance = round(max(0, (float) $installment->required_amount - $amountPaid), 2);

                            DB::table('loan_installments')
                                ->where('id', $installment->id)
                                ->update([
                                    'amount_paid' => $amountPaid,
                                    'remaining_balance' => $remainingBalance,
                                    'payment_date' => $repayment->payment_date,
                                    'status' => $remainingBalance <= 0
                                        ? 'Paid'
                                        : ($amountPaid > 0 ? 'Partially Paid' : 'Billed'),
                                    'updated_at' => now(),
                                ]);

                            $remainingPayment = round($remainingPayment - $allocated, 2);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
    }
};
