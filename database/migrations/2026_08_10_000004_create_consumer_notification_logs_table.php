<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consumer_notification_logs')) {
            return;
        }

        Schema::create('consumer_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel')->default('email');
            $table->string('event');
            $table->string('recipient')->nullable();
            $table->string('status')->default('sent');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // One delivery attempt per consumer + event keeps duplicate
            // notifications from being sent for the same lifecycle event.
            $table->unique(['consumer_id', 'event'], 'consumer_notification_logs_consumer_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumer_notification_logs');
    }
};