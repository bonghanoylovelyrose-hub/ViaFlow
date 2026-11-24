<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Single-admin enforcement requires a MySQL/MariaDB or SQLite database.');
        }

        // Test databases start empty; a populated system must be reconciled
        // explicitly before adding the constraint.
        $admins = DB::table('users')->where('role', 'admin')->whereNull('deleted_at')->get(['id', 'email']);
        if ($admins->count() > 1 || ($admins->count() === 1 && strcasecmp($admins->first()->email, 'admin@example.com') !== 0)) {
            throw new RuntimeException('Deactivate the other administrator accounts before enabling single-admin enforcement.');
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX users_single_active_admin ON users (role) WHERE role = 'admin' AND deleted_at IS NULL");
            foreach (['INSERT', 'UPDATE'] as $event) {
                $name = strtolower($event);
                DB::unprepared("CREATE TRIGGER users_admin_{$name}_guard BEFORE {$event} ON users WHEN NEW.role = 'admin' AND NEW.deleted_at IS NULL AND lower(NEW.email) <> 'admin@example.com' BEGIN SELECT RAISE(ABORT, 'Only admin@example.com may be an active administrator'); END");
            }
        } else {
            DB::statement("ALTER TABLE users ADD COLUMN active_admin_guard TINYINT GENERATED ALWAYS AS (CASE WHEN role = 'admin' AND deleted_at IS NULL THEN 1 ELSE NULL END) STORED, ADD UNIQUE INDEX users_single_active_admin (active_admin_guard)");
            foreach (['INSERT', 'UPDATE'] as $event) {
                $name = strtolower($event);
                DB::unprepared("CREATE TRIGGER users_admin_{$name}_guard BEFORE {$event} ON users FOR EACH ROW BEGIN IF NEW.role = 'admin' AND NEW.deleted_at IS NULL AND LOWER(NEW.email) <> 'admin@example.com' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only admin@example.com may be an active administrator'; END IF; END");
            }
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS users_admin_insert_guard');
        DB::unprepared('DROP TRIGGER IF EXISTS users_admin_update_guard');
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS users_single_active_admin');
        } elseif (Schema::hasColumn('users', 'active_admin_guard')) {
            DB::statement('ALTER TABLE users DROP INDEX users_single_active_admin, DROP COLUMN active_admin_guard');
        }
    }
};
