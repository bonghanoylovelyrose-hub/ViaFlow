<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            if (! Schema::hasColumn('bills', 'carried_forward_from_bill_id')) {
                $table->foreignId('carried_forward_from_bill_id')
                    ->nullable()
                    ->after('arrears_amount')
                    ->constrained('bills')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            if (Schema::hasColumn('bills', 'carried_forward_from_bill_id')) {
                $table->dropConstrainedForeignId('carried_forward_from_bill_id');
            }
        });
    }
};