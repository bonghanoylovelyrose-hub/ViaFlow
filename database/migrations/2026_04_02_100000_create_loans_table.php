<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->unsignedTinyInteger('term_months');
            $table->text('purpose');
            $table->string('status')->default('Pending Review');
            $table->string('risk_level')->default('Medium Risk');
            $table->text('recommendation_notes')->nullable();
            $table->decimal('monthly_installment', 10, 2)->nullable();
            $table->timestamp('applied_at');
            $table->timestamp('approved_at')->nullable();
            $table->date('next_due_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
