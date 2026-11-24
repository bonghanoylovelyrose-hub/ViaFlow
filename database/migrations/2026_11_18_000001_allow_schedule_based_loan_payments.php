<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('bill_id')->nullable()->change();
            $table->foreignId('loan_id')->nullable()->after('bill_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Schedule-based payments must be retained; reversing this migration
        // requires linking them to bills first.
        if (DB::table('payments')->whereNull('bill_id')->exists()) {
            throw new RuntimeException('Link schedule-based payments to bills before rolling back.');
        }
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('loan_id');
            $table->foreignId('bill_id')->nullable(false)->change();
        });
    }
};
