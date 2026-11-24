<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->date('start_billing_period')->nullable()->after('next_due_date');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable()->after('meter_reading_id')->constrained()->nullOnDelete();
            $table->decimal('loan_installment_amount', 10, 2)->default(0)->after('penalty_amount');
        });

    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_id');
            $table->dropColumn('loan_installment_amount');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('start_billing_period');
        });
    }
};
