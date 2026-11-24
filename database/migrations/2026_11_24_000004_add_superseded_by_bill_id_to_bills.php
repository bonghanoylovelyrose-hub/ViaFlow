<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->foreignId('superseded_by_bill_id')->nullable()->constrained('bills')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bills', fn (Blueprint $table) => $table->dropConstrainedForeignId('superseded_by_bill_id'));
    }
};
