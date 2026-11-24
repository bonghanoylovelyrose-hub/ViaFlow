<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->string('requested_membership_type', 20)
                ->default('Non-Member')
                ->after('membership_type');
            $table->string('membership_verification_status', 30)
                ->default('Not Required')
                ->after('requested_membership_type');
            $table->timestamp('membership_reviewed_at')->nullable()->after('membership_verification_status');
            $table->unsignedBigInteger('membership_reviewed_by')->nullable()->index()->after('membership_reviewed_at');
            $table->text('membership_verification_notes')->nullable()->after('membership_reviewed_by');

            $table->index(
                ['requested_membership_type', 'membership_verification_status'],
                'consumers_membership_verification_index',
            );
        });

        DB::table('consumers')->chunkById(100, function ($consumers): void {
            foreach ($consumers as $consumer) {
                $documentPaths = json_decode((string) $consumer->membership_document_paths, true);

                $hasMemberSignals = $consumer->membership_type === 'Member'
                    || (bool) $consumer->association_member
                    || filled($consumer->membership_document_type)
                    || (is_array($documentPaths) && $documentPaths !== []);

                $requestedType = $hasMemberSignals ? 'Member' : 'Non-Member';

                if ($consumer->membership_type === 'Member') {
                    $verificationStatus = 'Verified';
                } elseif ($requestedType === 'Member') {
                    $verificationStatus = 'Pending';
                } else {
                    $verificationStatus = 'Not Required';
                }

                DB::table('consumers')
                    ->where('id', $consumer->id)
                    ->update([
                        'requested_membership_type' => $requestedType,
                        'membership_verification_status' => $verificationStatus,
                        'membership_reviewed_at' => $verificationStatus === 'Verified'
                            ? ($consumer->membership_started_at ?? $consumer->updated_at ?? now())
                            : null,
                        'membership_reviewed_by' => null,
                        'membership_verification_notes' => $verificationStatus === 'Pending'
                            ? 'Migrated record: membership claim requires admin re-verification.'
                            : null,
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->dropIndex('consumers_membership_verification_index');
            $table->dropColumn([
                'requested_membership_type',
                'membership_verification_status',
                'membership_reviewed_at',
                'membership_reviewed_by',
                'membership_verification_notes',
            ]);
        });
    }
};
