<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            if (! Schema::hasColumn('consumers', 'address_block')) {
                $table->string('address_block')->nullable()->after('contact_number');
            }

            if (! Schema::hasColumn('consumers', 'address_street')) {
                $table->string('address_street')->nullable()->after('address_block');
            }
        });
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->dropColumn(['address_block', 'address_street']);
        });
    }
};