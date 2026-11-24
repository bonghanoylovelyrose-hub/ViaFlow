<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consumer credentials now follow the assigned meter number, and a
     * temporary password must be replaced after the first sign-in.
     *
     * Admin and meter-reader rows are deliberately left untouched: only users
     * linked to a consumer account are backfilled.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        foreach ($this->consumerUsers() as $user) {
            $meterNumber = trim((string) $user->meter_number);

            if ($meterNumber === '') {
                continue;
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update(['username' => $meterNumber]);
        }
    }

    public function down(): void
    {
        foreach ($this->consumerUsers() as $user) {
            $email = trim((string) $user->email);

            DB::table('users')
                ->where('id', $user->id)
                ->update(['username' => $email !== '' ? mb_strtolower($email) : null]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }

    private function consumerUsers()
    {
        return DB::table('users')
            ->join('consumers', 'consumers.user_id', '=', 'users.id')
            ->where('users.role', 'consumer')
            ->select(['users.id', 'users.email', 'consumers.meter_number'])
            ->orderBy('users.id')
            ->get();
    }
};
