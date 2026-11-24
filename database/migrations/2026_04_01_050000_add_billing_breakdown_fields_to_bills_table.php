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
        Schema::table('bills', function (Blueprint $table) {
            $table->decimal('base_charge', 10, 2)->nullable()->after('amount');
            $table->decimal('water_charge', 10, 2)->nullable()->after('base_charge');
            $table->decimal('arrears_amount', 10, 2)->nullable()->after('water_charge');
            $table->decimal('penalty_amount', 10, 2)->nullable()->after('arrears_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn([
                'base_charge',
                'water_charge',
                'arrears_amount',
                'penalty_amount',
            ]);
        });
    }
};
