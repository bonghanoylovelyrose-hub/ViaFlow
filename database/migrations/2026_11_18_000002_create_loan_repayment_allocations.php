<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_repayment_allocations')) {
            Schema::create('loan_repayment_allocations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('loan_repayment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('loan_installment_id')->constrained()->restrictOnDelete();
                $table->decimal('amount', 10, 2);
                $table->decimal('installment_balance_after', 10, 2);
                $table->timestamps();
            });
        }
        if (! Schema::hasIndex('loan_repayment_allocations', 'loan_repayment_installment_unique')) {
            Schema::table('loan_repayment_allocations', function (Blueprint $table): void {
                $table->unique(['loan_repayment_id', 'loan_installment_id'], 'loan_repayment_installment_unique');
            });
        }
        if (! Schema::hasColumn('loan_repayments', 'loan_balance_before')) {
            Schema::table('loan_repayments', function (Blueprint $table): void {
                $table->decimal('loan_balance_before', 10, 2)->nullable();
            });
        }
        if (! Schema::hasColumn('loan_repayments', 'loan_balance_after')) {
            Schema::table('loan_repayments', function (Blueprint $table): void {
                $table->decimal('loan_balance_after', 10, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table): void {
            $table->dropColumn(['loan_balance_before', 'loan_balance_after']);
        });
        Schema::dropIfExists('loan_repayment_allocations');
    }
};
