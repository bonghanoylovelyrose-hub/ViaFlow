<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->boolean('reconnection_pending')->default(false);
        });

        Schema::table('consumer_reconnections', function (Blueprint $table) {
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consumer_reconnections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn('completed_at');
        });

        Schema::table('consumers', fn (Blueprint $table) => $table->dropColumn('reconnection_pending'));
    }
};
