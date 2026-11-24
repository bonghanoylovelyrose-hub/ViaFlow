<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('pdf_base64');
            $table->timestamp('issued_at');
            $table->string('email_status', 20)->default('pending');
            $table->string('email_recipient')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_agreements');
    }
};
