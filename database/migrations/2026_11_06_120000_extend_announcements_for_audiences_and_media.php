<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->json('recipient_user_ids')->nullable();
            $table->string('photo_path')->nullable();
        });

        Schema::create('announcement_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id']);
        });

        Schema::create('announcement_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_email');
            $table->string('status', 20)->default('Queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'recipient_email']);
            $table->index(['status', 'queued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_deliveries');
        Schema::dropIfExists('announcement_recipients');
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['recipient_user_ids', 'photo_path']);
        });
    }
};
