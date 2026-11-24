<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL will not drop an index that is currently backing a foreign key.
        // The consumer_id foreign key relied on the unique index, so a
        // replacement index must exist before the unique one can be removed.
        Schema::table('consumer_notification_logs', function (Blueprint $table): void {
            $table->index(['consumer_id', 'event'], 'consumer_notification_logs_consumer_event_index');
        });

        Schema::table('consumer_notification_logs', function (Blueprint $table): void {
            // A unique (consumer_id, event) index blocked retries: once a
            // 'failed' row existed, a later successful send for the same event
            // raised a duplicate-key error instead of simply succeeding.
            // Duplicate suppression is enforced in the model by checking for a
            // previously 'sent' row, so a plain index is sufficient.
            $table->dropUnique('consumer_notification_logs_consumer_event_unique');
        });
    }

    public function down(): void
    {
        // Only delivered rows can be represented by a unique key.
        DB::table('consumer_notification_logs')
            ->where('status', '!=', 'sent')
            ->delete();

        // Restore the unique index first: once it exists it can back the
        // consumer_id foreign key, which frees the composite index to be dropped.
        Schema::table('consumer_notification_logs', function (Blueprint $table): void {
            $table->unique(['consumer_id', 'event'], 'consumer_notification_logs_consumer_event_unique');
        });

        Schema::table('consumer_notification_logs', function (Blueprint $table): void {
            $table->dropIndex('consumer_notification_logs_consumer_event_index');
        });
    }
};