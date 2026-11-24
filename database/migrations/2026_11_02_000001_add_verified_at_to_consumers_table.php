<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records when a consumer account actually became verified/activated.
 *
 * The meter reading queue is FIFO by verification time. Existing timestamps
 * (membership_reviewed_at, updated_at) are not reliable for every account:
 * membership review only applies to member claims, and updated_at changes on
 * every edit. This column is written once, when the account first satisfies
 * the verified state, and is never moved by later profile edits.
 */
return new class extends Migration
{
    private const VERIFIED_STATUSES = ['Active', 'For Review', 'Disconnected', 'For Reconnection'];

    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table): void {
            if (! Schema::hasColumn('consumers', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('membership_verification_notes');
                $table->index('verified_at', 'consumers_verified_at_index');
            }
        });

        $this->backfillVerificationDates();
    }

    /**
     * Existing accounts have no explicit verification timestamp. The active
     * meter assignment is stamped when the account is activated, so it is the
     * best available source; membership review and creation dates are the
     * fallbacks for records that predate meter assignment history.
     */
    private function backfillVerificationDates(): void
    {
        $hasAssignments = Schema::hasTable('meter_assignments');

        DB::table('consumers')
            ->whereIn('status', self::VERIFIED_STATUSES)
            ->whereNotNull('account_number')
            ->where('account_number', '<>', '')
            ->whereNotNull('meter_number')
            ->where('meter_number', '<>', '')
            ->whereNull('verified_at')
            ->orderBy('id')
            ->chunkById(200, function ($consumers) use ($hasAssignments): void {
                foreach ($consumers as $consumer) {
                    $assignedAt = $hasAssignments
                        ? DB::table('meter_assignments')
                            ->where('consumer_id', $consumer->id)
                            ->whereNull('retired_at')
                            ->whereRaw('LOWER(meter_number) = ?', [mb_strtolower(trim((string) $consumer->meter_number))])
                            ->max('assigned_at')
                        : null;

                    DB::table('consumers')
                        ->where('id', $consumer->id)
                        ->update([
                            'verified_at' => $assignedAt
                                ?? $consumer->membership_reviewed_at
                                ?? $consumer->created_at
                                ?? $consumer->updated_at
                                ?? now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table): void {
            if (Schema::hasColumn('consumers', 'verified_at')) {
                $table->dropIndex('consumers_verified_at_index');
                $table->dropColumn('verified_at');
            }
        });
    }
};
