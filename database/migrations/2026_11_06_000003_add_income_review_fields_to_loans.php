<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->decimal('monthly_income', 12, 2)->nullable()->after('amount');
            $table->decimal('income_verified_monthly_amount', 12, 2)->nullable()->after('monthly_income');
            $table->string('proof_of_income_path')->nullable()->after('income_verified_monthly_amount');
            $table->json('income_ocr_data')->nullable()->after('proof_of_income_path');
            $table->string('income_verification_status')->default('Needs Review')->after('income_ocr_data');
            $table->foreignId('income_verified_by')->nullable()->after('income_verification_status')->constrained('users')->nullOnDelete();
            $table->timestamp('income_verified_at')->nullable()->after('income_verified_by');
            $table->text('income_verification_notes')->nullable()->after('income_verified_at');
            $table->timestamp('review_requested_at')->nullable()->after('income_verification_notes');
            $table->text('review_request_reason')->nullable()->after('review_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('income_verified_by');
            $table->dropColumn([
                'monthly_income', 'income_verified_monthly_amount', 'proof_of_income_path',
                'income_ocr_data', 'income_verification_status', 'income_verified_at',
                'income_verification_notes', 'review_requested_at', 'review_request_reason',
            ]);
        });
    }
};
