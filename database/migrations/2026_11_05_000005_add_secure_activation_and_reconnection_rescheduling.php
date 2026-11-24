<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumer_password_setups', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->foreignId('consumer_id')->constrained('consumers')->cascadeOnDelete();
            $table->string('token_hash', 64);
            $table->string('recipient_email');
            $table->dateTime('expires_at');
            $table->dateTime('created_at');
        });
        Schema::table('installation_reschedule_requests', function (Blueprint $table) {
            $table->foreignId('consumer_reconnection_id')->nullable();
            $table->foreign('consumer_reconnection_id', 'reschedule_reconnection_fk')->references('id')->on('consumer_reconnections')->nullOnDelete();
            $table->date('preferred_installation_date')->nullable();
            $table->time('preferred_installation_time')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('installation_reschedule_requests', function (Blueprint $table) {
            $table->dropForeign('reschedule_reconnection_fk');
            $table->dropColumn('consumer_reconnection_id');
            $table->dropColumn(['preferred_installation_date', 'preferred_installation_time']);
        });
        Schema::dropIfExists('consumer_password_setups');
    }
};
