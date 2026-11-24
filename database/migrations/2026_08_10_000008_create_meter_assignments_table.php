<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps every meter ever issued to a consumer so re-assignments can be audited,
 * while the consumer record continues to hold the currently active meter.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meter_assignments')) {
            Schema::create('meter_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('consumer_id')->constrained()->cascadeOnDelete();
                $table->string('meter_number');
                $table->timestamp('assigned_at');
                $table->timestamp('retired_at')->nullable();
                $table->string('reason')->nullable();
                $table->foreignId('replaced_by_assignment_id')->nullable()->constrained('meter_assignments')->nullOnDelete();
                $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                // History lookup only. Active uniqueness is enforced by the
                // unique index on consumers.meter_number, so the same number can
                // appear more than once in one account's history.
                $table->index(
                    ['consumer_id', 'meter_number'],
                    'meter_assignments_consumer_meter_index'
                );
                $table->index(['consumer_id', 'retired_at'], 'meter_assignments_consumer_active_index');
            });
        }

        // Snapshot of the meter a reading was taken from, so the history of a
        // replaced meter stays intact and auditable.
        if (! Schema::hasColumn('meter_readings', 'meter_number')) {
            Schema::table('meter_readings', function (Blueprint $table): void {
                $table->string('meter_number')->nullable()->after('consumer_id');
            });
        }

        $this->seedFromExistingMeters();
    }

    /**
     * Every consumer with a meter gets an opening active assignment.
     */
    private function seedFromExistingMeters(): void
    {
        if (DB::table('meter_assignments')->exists()) {
            return;
        }

        $rows = DB::table('consumers')
            ->whereNotNull('meter_number')
            ->where('meter_number', '<>', '')
            ->select('id', 'meter_number', 'created_at')
            ->get();

        $now = now();

        foreach ($rows as $row) {
            DB::table('meter_assignments')->insert([
                'consumer_id' => $row->id,
                'meter_number' => $row->meter_number,
                'assigned_at' => $row->created_at ?? $now,
                'retired_at' => null,
                'reason' => 'Initial meter assignment',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Stamp the active meter onto existing readings for auditing.
        DB::statement(
            'UPDATE meter_readings
             SET meter_number = (
                 SELECT c.meter_number FROM consumers c WHERE c.id = meter_readings.consumer_id
             )
             WHERE meter_number IS NULL'
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('meter_readings', 'meter_number')) {
            Schema::table('meter_readings', function (Blueprint $table): void {
                $table->dropColumn('meter_number');
            });
        }

        Schema::dropIfExists('meter_assignments');
    }
};