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
            $table->json('rejection_fields')->nullable()->after('rejection_reason');
            $table->unsignedSmallInteger('rejection_count')->default(0)->after('rejection_fields');
            $table->timestamp('resubmitted_at')->nullable()->after('rejection_count');
            $table->string('service_purok', 100)->nullable()->after('service_address');
        });

        // Existing applications only stored the formatted service address, so
        // recover the Purok from it. The resubmission form needs the Purok to
        // rebuild the address when Block or Street is corrected.
        $puroks = config('service_area.puroks', []);

        DB::table('consumers')
            ->whereNull('service_purok')
            ->whereNotNull('service_address')
            ->orderBy('id')
            ->chunk(100, function ($consumers) use ($puroks): void {
                foreach ($consumers as $consumer) {
                    foreach ($puroks as $purok) {
                        if (str_contains((string) $consumer->service_address, $purok)) {
                            DB::table('consumers')
                                ->where('id', $consumer->id)
                                ->update(['service_purok' => $purok]);

                            break;
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->dropColumn(['rejection_fields', 'rejection_count', 'resubmitted_at', 'service_purok']);
        });
    }
};
