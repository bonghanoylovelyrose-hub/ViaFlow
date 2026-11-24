<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->date('membership_started_at')->nullable()->after('membership_type');
        });

        DB::table('consumers')
            ->where('membership_type', 'Member')
            ->update(['membership_started_at' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->dropColumn('membership_started_at');
        });
    }
};
