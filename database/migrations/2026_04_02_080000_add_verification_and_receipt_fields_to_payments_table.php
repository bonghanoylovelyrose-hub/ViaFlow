<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_channel')->default('Counter')->after('payment_method');
            $table->string('gateway_reference')->nullable()->after('payment_channel');
            $table->string('receipt_number')->nullable()->unique()->after('reference_number');
            $table->timestamp('verified_at')->nullable()->after('status');
            $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn([
                'payment_channel',
                'gateway_reference',
                'receipt_number',
                'verified_at',
            ]);
        });
    }
};
