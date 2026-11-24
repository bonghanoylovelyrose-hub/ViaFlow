<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->foreignId('bill_id')->nullable()->after('loan_id')->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->after('bill_id')->unique()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
            $table->dropConstrainedForeignId('bill_id');
        });
    }
};
