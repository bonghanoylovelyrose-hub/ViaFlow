<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
        });

        DB::table('users')
            ->where('role', 'consumer')
            ->whereNotNull('email')
            ->update([
                'username' => DB::raw('LOWER(TRIM(email))'),
            ]);
    }

    public function down(): void
    {
        $usedUsernames = [];

        DB::table('users')
            ->select(['id', 'username'])
            ->orderBy('id')
            ->each(function (object $user) use (&$usedUsernames): void {
                $base = filled($user->username) ? (string) $user->username : 'user';
                $username = $base;
                $suffix = 2;

                while (isset($usedUsernames[$username])) {
                    $username = $base.$suffix;
                    $suffix++;
                }

                $usedUsernames[$username] = true;
                DB::table('users')->where('id', $user->id)->update(['username' => $username]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }
};
