<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['bills', 'meter_readings', 'payments', 'disconnection_notices'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->boolean('is_billing_fixture')->default(false));
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (DB::table($name)->where('is_billing_fixture', true)->exists()) {
                throw new RuntimeException('Remove the development billing fixtures before rolling back their ledger flags.');
            }
        }
        foreach (self::TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('is_billing_fixture'));
        }
    }
};
