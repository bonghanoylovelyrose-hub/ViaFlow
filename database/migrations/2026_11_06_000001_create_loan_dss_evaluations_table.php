<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_dss_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('recommendation');
            $table->string('evaluation_type')->default('initial');
            $table->string('criteria_version');
            $table->json('evaluated_values');
            $table->json('failed_criteria');
            $table->json('missing_information');
            $table->string('evidence_hash', 64);
            $table->timestamp('evaluated_at');
            $table->timestamps();
            $table->index(['loan_id', 'evaluated_at']);
            $table->unique(['loan_id', 'evidence_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_dss_evaluations');
    }
};
