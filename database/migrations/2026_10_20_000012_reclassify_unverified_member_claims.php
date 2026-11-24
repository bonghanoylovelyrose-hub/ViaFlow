<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consumers')) {
            return;
        }

        // Some accounts created before automatic classification captured a
        // "yes" answer only as a pending claim while keeping the effective
        // type Non-Member. Classify every such claim as Member now; member
        // benefits remain gated by hasVerifiedMembership() until the claim is
        // verified.
        DB::table('consumers')
            ->where('requested_membership_type', 'Member')
            ->where('membership_verification_status', 'Pending')
            ->where('membership_type', 'Non-Member')
            ->update([
                'membership_type' => 'Member',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // This is a one-time data correction. Reversing it could reclassify
        // claims submitted or verified after the migration.
    }
};
