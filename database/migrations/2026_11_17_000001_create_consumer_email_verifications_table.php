<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('consumer_email_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consumer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumer_email_verifications');
    }
};
