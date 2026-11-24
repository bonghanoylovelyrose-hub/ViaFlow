<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            if (! Schema::hasColumn('incidents', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('confirmed_at');
            }
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->index('status');
            $table->index('reported_at');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['reported_at']);
        });

        Schema::table('incidents', function (Blueprint $table) {
            if (Schema::hasColumn('incidents', 'resolved_at')) {
                $table->dropColumn('resolved_at');
            }
        });
    }
};