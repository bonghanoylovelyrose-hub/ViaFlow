<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_dss_evaluations', function (Blueprint $table): void {
            $table->json('income_evidence')->nullable()->after('evaluated_values');
            $table->string('income_verification_status')->nullable()->after('income_evidence');
        });
    }

    public function down(): void
    {
        Schema::table('loan_dss_evaluations', function (Blueprint $table): void {
            $table->dropColumn(['income_evidence', 'income_verification_status']);
        });
    }
};
