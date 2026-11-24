<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->string('account_number')->nullable()->change();
            $table->string('meter_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->string('account_number')->nullable(false)->change();
            $table->string('meter_number')->nullable(false)->change();
        });
    }
};
