<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table): void {
            if (! Schema::hasColumn('consumers', 'disconnected_at')) {
                $table->timestamp('disconnected_at')->nullable()->after('full_payment_verified_at');
            }

            if (! Schema::hasColumn('consumers', 'reconnected_at')) {
                $table->timestamp('reconnected_at')->nullable()->after('disconnected_at');
            }
        });

        Schema::table('consumers', function (Blueprint $table): void {
            $table->index('disconnected_at', 'consumers_reporting_disconnected_index');
            $table->index('reconnected_at', 'consumers_reporting_reconnected_index');
        });

        $this->backfillReconnectedDates();
    }

    /**
     * Completed reconnection requests already carry the authoritative
     * completion timestamp, so existing reconnections can be backfilled.
     * There is no equivalent historical source for disconnections; those
     * dates are deliberately left null rather than invented.
     */
    private function backfillReconnectedDates(): void
    {
        if (! Schema::hasTable('service_requests')) {
            return;
        }

        DB::table('service_requests')
            ->where('request_type', 'Reconnection')
            ->where('status', 'Completed')
            ->whereNotNull('reviewed_at')
            ->orderBy('reviewed_at')
            ->get(['consumer_id', 'reviewed_at'])
            ->groupBy('consumer_id')
            ->each(function ($requests, $consumerId): void {
                DB::table('consumers')
                    ->where('id', $consumerId)
                    ->whereNull('reconnected_at')
                    ->update(['reconnected_at' => $requests->last()->reviewed_at]);
            });
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table): void {
            $table->dropIndex('consumers_reporting_disconnected_index');
            $table->dropIndex('consumers_reporting_reconnected_index');
            $table->dropColumn(['disconnected_at', 'reconnected_at']);
        });
    }
};
