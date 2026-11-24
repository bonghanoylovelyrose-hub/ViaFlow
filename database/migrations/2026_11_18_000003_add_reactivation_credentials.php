<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_reactivation_password')->default(false);
        });
        Schema::table('consumer_reconnections', function (Blueprint $table): void {
            $table->text('pending_credentials')->nullable();
            $table->timestamp('credentials_issued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('consumer_reconnections', function (Blueprint $table): void {
            $table->dropColumn(['pending_credentials', 'credentials_issued_at']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('must_change_reactivation_password');
        });
    }
};
