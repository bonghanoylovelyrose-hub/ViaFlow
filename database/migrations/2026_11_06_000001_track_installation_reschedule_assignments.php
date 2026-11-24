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
            $table->time('installation_time')->nullable();
        });
        Schema::table('installation_reschedule_requests', function (Blueprint $table) {
            $table->unsignedInteger('assignment_number')->nullable();
            $table->dateTime('assigned_at')->nullable();
            $table->unique(['consumer_id', 'assignment_number'], 'reschedule_consumer_assignment_unique');
        });
        $counts = [];
        DB::table('installation_reschedule_requests')->whereNotNull('new_installation_date')->orderBy('id')
            ->eachById(function ($request) use (&$counts): void {
                $counts[$request->consumer_id] = ($counts[$request->consumer_id] ?? 0) + 1;
                DB::table('installation_reschedule_requests')->where('id', $request->id)->update([
                    'assignment_number' => $counts[$request->consumer_id],
                    'assigned_at' => $request->reviewed_at ?? $request->updated_at ?? now(),
                ]);
            });
        DB::table('consumers')->whereNotNull('reconnection_scheduled_at')->orderBy('id')->eachById(function ($consumer): void {
            DB::table('consumers')->where('id', $consumer->id)->update(['installation_time' => substr($consumer->reconnection_scheduled_at, 11, 8)]);
        });
    }

    public function down(): void
    {
        Schema::table('installation_reschedule_requests', function (Blueprint $table) {
            $table->dropUnique('reschedule_consumer_assignment_unique');
            $table->dropColumn(['assignment_number', 'assigned_at']);
        });
        Schema::table('consumers', function (Blueprint $table) {
            $table->dropColumn('installation_time');
        });
    }
};
