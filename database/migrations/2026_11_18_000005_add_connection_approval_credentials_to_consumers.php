<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->text('connection_approval_credentials')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->dropColumn('connection_approval_credentials');
        });
    }
};
