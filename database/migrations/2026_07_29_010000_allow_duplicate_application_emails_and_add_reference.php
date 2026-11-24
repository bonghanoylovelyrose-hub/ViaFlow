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
            $table->dropUnique('consumers_email_unique');
            $table->string('application_reference')->nullable()->after('id');
        });

        DB::table('consumers')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->each(function (object $consumer): void {
                $date = $consumer->created_at
                    ? date('Ymd', strtotime((string) $consumer->created_at))
                    : now()->format('Ymd');

                DB::table('consumers')
                    ->where('id', $consumer->id)
                    ->update([
                        'application_reference' => sprintf('VVA-%s-%06d', $date, $consumer->id),
                    ]);
            });

        Schema::table('consumers', function (Blueprint $table) {
            $table->unique('application_reference');
        });
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->dropUnique(['application_reference']);
            $table->dropColumn('application_reference');
            $table->unique('email');
        });
    }
};
