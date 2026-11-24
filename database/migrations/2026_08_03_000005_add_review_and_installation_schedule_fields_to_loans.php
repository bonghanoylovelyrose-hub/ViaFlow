<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('review_status')->default('Pending Review')->after('status');
            $table->text('review_notes')->nullable()->after('recommendation_notes');
            $table->string('installation_status')->default('Not Scheduled')->after('review_notes');
            $table->date('installation_date')->nullable()->after('installation_status');
            $table->time('installation_time')->nullable()->after('installation_date');
            $table->text('installation_notes')->nullable()->after('installation_time');
        });

        DB::table('loans')
            ->where('status', 'Completed')
            ->update(['review_status' => 'Completed']);

        DB::table('loans')
            ->whereIn('status', ['Approved', 'Active', 'Ongoing', 'Rejected'])
            ->update(['review_status' => 'Reviewed']);
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'review_status',
                'review_notes',
                'installation_status',
                'installation_date',
                'installation_time',
                'installation_notes',
            ]);
        });
    }
};
