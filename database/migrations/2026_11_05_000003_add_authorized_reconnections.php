<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->foreignId('reconnection_fee_bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->date('reconnection_date')->nullable();
            $table->dateTime('reconnection_scheduled_at')->nullable();
        });
        Schema::create('consumer_reconnections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('confirmed_by')->constrained('users');
            $table->dateTime('disconnected_at')->nullable();
            $table->date('reconnection_date');
            $table->dateTime('installation_scheduled_at');
            $table->dateTime('activated_at');
            $table->decimal('reconnection_fee', 10, 2);
            $table->json('verified_payment_ids');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumer_reconnections');
        Schema::table('consumers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reconnection_fee_bill_id');
            $table->dropColumn(['reconnection_date', 'reconnection_scheduled_at']);
        });
    }
};
