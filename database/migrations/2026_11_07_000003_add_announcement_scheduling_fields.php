<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('priority', 20)->default('Normal');
            $table->timestamp('expires_at')->nullable();
            $table->string('affected_area')->nullable();
            $table->timestamp('service_starts_at')->nullable();
            $table->timestamp('service_restores_at')->nullable();
            $table->timestamp('notifications_queued_at')->nullable();
        });
        DB::table('announcements')->update(['notifications_queued_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['priority', 'expires_at', 'affected_area', 'service_starts_at', 'service_restores_at', 'notifications_queued_at']);
        });
    }
};
