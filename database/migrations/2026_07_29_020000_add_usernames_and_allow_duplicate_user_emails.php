<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
            $table->dropUnique('users_email_unique');
        });

        $usedUsernames = [];

        DB::table('users')
            ->select(['id', 'name', 'email'])
            ->orderBy('id')
            ->each(function (object $user) use (&$usedUsernames): void {
                $firstName = preg_split('/\s+/', trim((string) $user->name))[0] ?? '';
                $base = Str::lower(Str::slug($firstName, ''));

                if ($base === '') {
                    $base = Str::lower(Str::slug(Str::before((string) $user->email, '@'), ''));
                }

                $base = $base !== '' ? $base : 'user';
                $username = $base;
                $suffix = 2;

                while (isset($usedUsernames[$username])) {
                    $username = $base.$suffix;
                    $suffix++;
                }

                $usedUsernames[$username] = true;

                DB::table('users')->where('id', $user->id)->update([
                    'username' => $username,
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
            $table->unique('email');
        });
    }
};
