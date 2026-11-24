<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanInstallationCompletionService
{
    public function complete(Loan $loan, User $admin): void
    {
        DB::transaction(function () use ($loan, $admin): void {
            Consumer::query()->lockForUpdate()->findOrFail($loan->consumer_id);
            $locked = Loan::query()->with('latestDssEvaluation')->lockForUpdate()->findOrFail($loan->id);
            abort_unless($locked->isApprovedForInstallation(), 403);

            if ($locked->installation_status === 'Completed') {
                return;
            }

            if (! $locked->installation_date || ! $locked->installation_time) {
                throw ValidationException::withMessages(['installation_status' => 'Schedule the installation before marking it complete.']);
            }

            if ($locked->installationRescheduleRequests()->pending()->exists()) {
                throw ValidationException::withMessages(['installation_status' => 'Resolve the pending rescheduling request first.']);
            }

            $completedAt = now();
            $locked->forceFill([
                'installation_status' => 'Completed',
                'installation_completed_at' => $completedAt,
                'installation_completed_by' => $admin->id,
                'released_at' => $locked->released_at ?? $completedAt,
            ])->save();
        });
    }
}
