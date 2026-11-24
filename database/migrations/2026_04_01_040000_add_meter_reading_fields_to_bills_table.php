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
            $table->foreignId('meter_reading_id')->nullable()->unique()->after('consumer_id')->constrained()->nullOnDelete();
            $table->decimal('units_consumed', 10, 2)->nullable()->after('amount');
            $table->decimal('unit_rate', 10, 2)->nullable()->after('units_consumed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meter_reading_id');
            $table->dropColumn(['units_consumed', 'unit_rate']);
        });
    }
};
