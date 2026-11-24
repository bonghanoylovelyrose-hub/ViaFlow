<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consumers') || ! Schema::hasTable('service_requests')) {
            return;
        }

        // Pending YES applications were previously stored as effective
        // Non-Members. Align them with the new classification while keeping
        // their membership verification Pending.
        DB::table('consumers')
            ->where('status', 'Pending Verification')
            ->where('requested_membership_type', 'Member')
            ->where('membership_verification_status', 'Pending')
            ->where('membership_type', 'Non-Member')
            ->update([
                'membership_type' => 'Member',
                'updated_at' => now(),
            ]);

        // Every pending application should have one reviewable connection
        // request, including applications submitted before automatic creation
        // was introduced.
        $pendingApplications = DB::table('consumers')
            ->where('status', 'Pending Verification')
            ->orderBy('id')
            ->get([
                'id',
                'application_reference',
                'requested_membership_type',
                'membership_type',
                'created_at',
            ]);

        foreach ($pendingApplications as $consumer) {
            $alreadyExists = DB::table('service_requests')
                ->where('consumer_id', $consumer->id)
                ->where('request_type', 'New Connection')
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            $declaredMembership = $consumer->requested_membership_type
                ?: $consumer->membership_type
                ?: 'Non-Member';
            $requestedAt = $consumer->created_at ?: now();

            DB::table('service_requests')->insert([
                'consumer_id' => $consumer->id,
                'request_type' => 'New Connection',
                'status' => 'Pending',
                'current_balance_snapshot' => 0,
                'notes' => 'Backfilled from water connection application '.$consumer->application_reference
                    .'. Declared VIOHOA membership: '.$declaredMembership.'.',
                'admin_notes' => null,
                'requested_at' => $requestedAt,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
                'created_at' => $requestedAt,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // This is a one-time data correction. Reversing it could reclassify
        // applications submitted after the migration, so there is no safe
        // automatic rollback.
    }
};
