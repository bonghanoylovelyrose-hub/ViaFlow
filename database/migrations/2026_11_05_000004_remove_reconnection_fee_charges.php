<?php

use App\Services\ReconnectionFeeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->decimal('waived_reconnection_fee_amount', 10, 2)->default(0);
        });
        // This migration runs before the development billing fixture columns exist.
        app()->instance('billing.legacySchemaMigration', true);
        try {
            app(ReconnectionFeeService::class)->removeAllCharges();
        } finally {
            app()->forgetInstance('billing.legacySchemaMigration');
        }
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('waived_reconnection_fee_amount');
        });
    }
};
