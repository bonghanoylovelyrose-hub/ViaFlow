<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores the full life cycle of every disconnection notice.
     *
     * The notice rows are the audit trail: a settled notice stays on record
     * even after the account is removed from the active notice list.
     */
    public function up(): void
    {
        Schema::create('disconnection_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->string('status')->default('Active');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('outstanding_amount', 10, 2)->default(0);
            $table->unsignedTinyInteger('months_unpaid')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            // One notice per consumer and triggering bill. This is the
            // database-level guard against duplicate notices for the same
            // delinquent billing cycle.
            $table->unique(['consumer_id', 'bill_id']);
            $table->index(['consumer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disconnection_notices');
    }
};
