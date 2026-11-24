<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->string('membership_type')->default('Non-Member')->after('status');
            $table->boolean('housing_rights_documented')->default(false)->after('membership_type');
            $table->boolean('association_member')->default(false)->after('housing_rights_documented');
            $table->boolean('association_registered')->default(false)->after('association_member');
            $table->date('membership_renewal_date')->nullable()->after('association_registered');
        });
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->dropColumn([
                'membership_type',
                'housing_rights_documented',
                'association_member',
                'association_registered',
                'membership_renewal_date',
            ]);
        });
    }
};
