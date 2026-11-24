<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disconnection_notices', function (Blueprint $table) {
            $table->decimal('outstanding_at_disconnection', 12, 2)->nullable();
            $table->text('disconnection_reason')->nullable();
        });
        Schema::table('consumer_reconnections', function (Blueprint $table) {
            $table->foreignId('disconnection_notice_id')->nullable()->unique()->constrained('disconnection_notices')->nullOnDelete();
            $table->dateTime('payment_settled_at')->nullable();
        });

        DB::table('consumer_reconnections')->orderBy('id')->each(function ($reconnection) {
            $settledAt = DB::table('payments')->where('consumer_id', $reconnection->consumer_id)
                ->whereIn('id', json_decode($reconnection->verified_payment_ids, true) ?? [])
                ->where('status', 'Verified')
                ->when($reconnection->disconnected_at !== null, fn ($query) => $query->where('verified_at', '>=', $reconnection->disconnected_at))
                ->where('verified_at', '<=', $reconnection->activated_at)
                ->whereIn('verified_by', DB::table('users')->where('role', 'admin')->select('id'))->max('verified_at');
            if ($settledAt !== null) {
                DB::table('consumer_reconnections')->where('id', $reconnection->id)->update(['payment_settled_at' => $settledAt]);
            }
            if ($reconnection->disconnected_at === null) {
                return;
            }
            $notice = DB::table('disconnection_notices')->where('consumer_id', $reconnection->consumer_id)
                ->where('disconnected_at', $reconnection->disconnected_at)->where('status', 'Disconnected')
                ->whereNotIn('id', DB::table('consumer_reconnections')->whereNotNull('disconnection_notice_id')->select('disconnection_notice_id'))
                ->orderByDesc('id')->first();
            if ($notice === null) {
                return;
            }
            DB::table('consumer_reconnections')->where('id', $reconnection->id)->update(['disconnection_notice_id' => $notice->id]);
            if ($notice->settled_at === null && $settledAt !== null) {
                DB::table('disconnection_notices')->where('id', $notice->id)->update(['settled_at' => $settledAt]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('consumer_reconnections', function (Blueprint $table) {
            $table->dropForeign(['disconnection_notice_id']);
            $table->dropUnique(['disconnection_notice_id']);
            $table->dropColumn(['disconnection_notice_id', 'payment_settled_at']);
        });
        Schema::table('disconnection_notices', function (Blueprint $table) {
            $table->dropColumn(['outstanding_at_disconnection', 'disconnection_reason']);
        });
    }
};
