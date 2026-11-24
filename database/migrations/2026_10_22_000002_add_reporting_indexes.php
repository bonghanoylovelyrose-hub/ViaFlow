<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->index(['billing_period', 'status'], 'bills_reporting_period_status_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['payment_date', 'status'], 'payments_reporting_date_status_index');
            $table->index('payment_channel', 'payments_reporting_channel_index');
        });

        Schema::table('incidents', function (Blueprint $table): void {
            $table->index(['reported_at', 'status'], 'incidents_reporting_date_status_index');
            $table->index('category', 'incidents_reporting_category_index');
        });

        Schema::table('loans', function (Blueprint $table): void {
            $table->index(['applied_at', 'status'], 'loans_reporting_date_status_index');
        });

        Schema::table('loan_repayments', function (Blueprint $table): void {
            $table->index('payment_date', 'loan_repayments_reporting_date_index');
        });

        Schema::table('loan_installments', function (Blueprint $table): void {
            $table->index(['billing_period', 'status'], 'loan_installments_reporting_period_status_index');
        });

        Schema::table('meter_readings', function (Blueprint $table): void {
            $table->index(['reading_date', 'status'], 'meter_readings_reporting_date_status_index');
        });

        Schema::table('service_requests', function (Blueprint $table): void {
            $table->index(
                ['request_type', 'status', 'reviewed_at'],
                'service_requests_reporting_type_status_reviewed_index',
            );
            $table->index('requested_at', 'service_requests_reporting_requested_index');
        });

        Schema::table('consumers', function (Blueprint $table): void {
            $table->index('status', 'consumers_reporting_status_index');
            $table->index('service_purok', 'consumers_reporting_purok_index');
        });
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table): void {
            $table->dropIndex('consumers_reporting_status_index');
            $table->dropIndex('consumers_reporting_purok_index');
        });

        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropIndex('service_requests_reporting_type_status_reviewed_index');
            $table->dropIndex('service_requests_reporting_requested_index');
        });

        Schema::table('meter_readings', function (Blueprint $table): void {
            $table->dropIndex('meter_readings_reporting_date_status_index');
        });

        Schema::table('loan_installments', function (Blueprint $table): void {
            $table->dropIndex('loan_installments_reporting_period_status_index');
        });

        Schema::table('loan_repayments', function (Blueprint $table): void {
            $table->dropIndex('loan_repayments_reporting_date_index');
        });

        Schema::table('loans', function (Blueprint $table): void {
            $table->dropIndex('loans_reporting_date_status_index');
        });

        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropIndex('incidents_reporting_date_status_index');
            $table->dropIndex('incidents_reporting_category_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_reporting_date_status_index');
            $table->dropIndex('payments_reporting_channel_index');
        });

        Schema::table('bills', function (Blueprint $table): void {
            $table->dropIndex('bills_reporting_period_status_index');
        });
    }
};
