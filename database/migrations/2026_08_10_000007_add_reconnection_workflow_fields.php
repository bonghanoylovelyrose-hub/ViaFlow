<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table): void {
            // Granted only once an administrator has verified the payment that
            // cleared the outstanding balance. Until then the reconnection
            // request stays unavailable.
            if (! Schema::hasColumn('consumers', 'full_payment_verified_at')) {
                $table->timestamp('full_payment_verified_at')->nullable()->after('status');
            }
        });

        Schema::table('service_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_requests', 'proof_path')) {
                $table->string('proof_path')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('service_requests', 'information')) {
                $table->text('information')->nullable()->after('proof_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropColumn(['proof_path', 'information']);
        });

        Schema::table('consumers', function (Blueprint $table): void {
            $table->dropColumn('full_payment_verified_at');
        });
    }
};