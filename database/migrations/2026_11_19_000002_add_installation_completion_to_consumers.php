<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table): void {
            $table->timestamp('installation_completed_at')->nullable();
            $table->unsignedBigInteger('installation_completed_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table): void {
            $table->dropColumn(['installation_completed_at', 'installation_completed_by']);
        });
    }
};
