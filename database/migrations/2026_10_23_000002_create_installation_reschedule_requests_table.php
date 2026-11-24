<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_reschedule_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reschedule_type'); // 'connection' or 'loan'
            $table->foreignId('consumer_id')->constrained('consumers')->cascadeOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->cascadeOnDelete();
            $table->string('status')->default('pending'); // 'pending', 'approved', 'rejected'
            $table->text('reason');
            $table->date('original_installation_date');
            $table->time('original_installation_time')->nullable();
            $table->date('new_installation_date')->nullable();
            $table->time('new_installation_time')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['consumer_id', 'status']);
            $table->index(['loan_id', 'status']);
            $table->index(['status', 'reschedule_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_reschedule_requests');
    }
};
