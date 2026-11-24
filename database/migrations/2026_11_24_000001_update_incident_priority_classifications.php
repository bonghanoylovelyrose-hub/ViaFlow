<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->replacePriority('Critical', 'Urgent');
            $this->replacePriority('Normal', 'Non-Urgent');
        });
    }

    public function down(): void
    {
        // The merged Urgent values cannot be split back into their former priorities.
        DB::transaction(fn () => $this->replacePriority('Non-Urgent', 'Normal'));
    }

    private function replacePriority(string $oldPriority, string $newPriority): void
    {
        foreach (['priority', 'automatic_priority'] as $column) {
            DB::table('incidents')->where($column, $oldPriority)->update([
                $column => $newPriority,
                'reported_at' => DB::raw('reported_at'),
            ]);
        }
    }
};
