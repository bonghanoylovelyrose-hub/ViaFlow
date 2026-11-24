<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            // An explicit default removes the implicit ON UPDATE on older MariaDB installations.
            Schema::table('incidents', function (Blueprint $table): void {
                $table->timestamp('reported_at')->useCurrent()->change();
            });
        }
    }

    public function down(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('incidents', function (Blueprint $table): void {
                $table->timestamp('reported_at')->change();
            });
        }
    }
};
