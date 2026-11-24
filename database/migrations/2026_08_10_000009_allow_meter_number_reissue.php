<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A meter number must never be active on two accounts at once, but the same
 * number can legitimately appear more than once in one account's history (a
 * faulty meter swapped out and later returned, for example).
 *
 * Active uniqueness is enforced by the unique index on consumers.meter_number,
 * which only ever holds the meter currently assigned to the account. The
 * blanket (consumer_id, meter_number) index is dropped so history can be kept
 * in full, and replaced by a plain lookup index.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meter_assignments') && $this->hasIndex('meter_assignments_consumer_meter_unique')) {
            Schema::table('meter_assignments', function (Blueprint $table): void {
                $table->dropUnique('meter_assignments_consumer_meter_unique');
            });
        }

        if (Schema::hasTable('meter_assignments') && ! $this->hasIndex('meter_assignments_consumer_meter_index')) {
            Schema::table('meter_assignments', function (Blueprint $table): void {
                $table->index(['consumer_id', 'meter_number'], 'meter_assignments_consumer_meter_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('meter_assignments', function (Blueprint $table): void {
            $table->dropIndex('meter_assignments_consumer_meter_index');
            $table->unique(['consumer_id', 'meter_number'], 'meter_assignments_consumer_meter_unique');
        });
    }

    private function hasIndex(string $name): bool
    {
        foreach (Schema::getIndexes('meter_assignments') as $index) {
            if ($index['name'] === $name) {
                return true;
            }
        }

        return false;
    }
};