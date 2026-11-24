<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disconnection_notices', function (Blueprint $table) {
            $table->string('source')->default('water');
            $table->foreignId('loan_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->date('loan_due_date')->nullable();
            $table->decimal('loan_balance_due', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('disconnection_notices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_id');
            $table->dropColumn(['source', 'loan_due_date', 'loan_balance_due']);
        });
    }
};
