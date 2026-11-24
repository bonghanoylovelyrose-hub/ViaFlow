<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            if (! Schema::hasColumn('incidents', 'purok')) {
                $table->string('purok', 100)->nullable()->after('location');
            }

            if (! Schema::hasColumn('incidents', 'block')) {
                $table->string('block', 50)->nullable()->after('purok');
            }
        });

        Schema::table('incidents', function (Blueprint $table): void {
            $table->index(['purok', 'block'], 'incidents_location_group_index');
        });

        $this->backfillLocations();
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropIndex('incidents_location_group_index');
        });

        Schema::table('incidents', function (Blueprint $table): void {
            if (Schema::hasColumn('incidents', 'purok')) {
                $table->dropColumn('purok');
            }

            if (Schema::hasColumn('incidents', 'block')) {
                $table->dropColumn('block');
            }
        });
    }

    private function backfillLocations(): void
    {
        $puroks = collect(config('service_area.puroks', []))
            ->filter(fn (mixed $purok): bool => filled($purok))
            ->values()
            ->all();

        $rows = DB::table('incidents')
            ->leftJoin('consumers', 'consumers.id', '=', 'incidents.consumer_id')
            ->orderBy('incidents.id')
            ->get([
                'incidents.id',
                'incidents.location',
                'incidents.purok',
                'incidents.block',
                'consumers.service_purok as consumer_purok',
                'consumers.address_block as consumer_block',
            ]);

        foreach ($rows as $row) {
            $purok = $this->normalizePurok($row->purok, $puroks)
                ?? $this->purokFromLocation($row->location, $puroks)
                ?? $this->normalizePurok($row->consumer_purok, $puroks);

            $block = $this->normalizeBlock($row->block)
                ?? $this->blockFromLocation($row->location)
                ?? $this->normalizeBlock($row->consumer_block);

            $updates = [];

            if ($purok !== null && $purok !== $row->purok) {
                $updates['purok'] = $purok;
            }

            if ($block !== null && $block !== $row->block) {
                $updates['block'] = $block;
            }

            if ($updates !== []) {
                DB::table('incidents')->where('id', $row->id)->update($updates);
            }
        }
    }

    /**
     * @param  list<string>  $puroks
     */
    private function normalizePurok(?string $value, array $puroks): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach ($puroks as $purok) {
            if (strcasecmp($value, $purok) === 0) {
                return $purok;
            }
        }

        return $value;
    }

    /**
     * @param  list<string>  $puroks
     */
    private function purokFromLocation(?string $location, array $puroks): ?string
    {
        $location = trim((string) $location);

        if ($location === '') {
            return null;
        }

        foreach ($puroks as $purok) {
            if (stripos($location, $purok) !== false) {
                return $purok;
            }
        }

        return null;
    }

    private function normalizeBlock(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/\b(?:block|blk)\.?\s*([A-Za-z0-9-]+)/i', $value, $matches) === 1) {
            return $matches[1];
        }

        return $value;
    }

    private function blockFromLocation(?string $location): ?string
    {
        $location = trim((string) $location);

        if ($location === '') {
            return null;
        }

        if (preg_match('/\b(?:block|blk)\.?\s*([A-Za-z0-9-]+)/i', $location, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
};
