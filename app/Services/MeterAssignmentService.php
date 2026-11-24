<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\MeterAssignment;
use App\Models\MeterReading;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Meter re-assignment.
 *
 * The consumer record always carries the currently active meter so the meter
 * reader module looks up the right meter, while every previous meter and its
 * readings are retained for auditing.
 */
class MeterAssignmentService
{
    /**
     * Assigns a new physical meter to a consumer.
     *
     * The previous meter is retired but never deleted, and all future readings
     * are recorded against the new number.
     *
     * @throws ValidationException when the number is invalid or already active elsewhere
     */
    public function assign(
        Consumer $consumer,
        string $newMeterNumber,
        User $actor,
        ?string $reason = null,
    ): MeterAssignment {
        $newMeterNumber = trim($newMeterNumber);

        if ($newMeterNumber === '') {
            throw ValidationException::withMessages([
                'meter_number' => 'A new meter number is required.',
            ]);
        }

        if (mb_strtolower($newMeterNumber) === mb_strtolower((string) $consumer->meter_number)) {
            throw ValidationException::withMessages([
                'meter_number' => 'The new meter number must be different from the current one.',
            ]);
        }

        // An active meter number may belong to only one active consumer account.
        $normalized = mb_strtolower($newMeterNumber);

        $conflicting = Consumer::query()
            ->whereRaw('LOWER(meter_number) = ?', [$normalized])
            ->whereKeyNot($consumer->id)
            ->whereNotIn('status', ['Rejected'])
            ->first();

        if ($conflicting) {
            throw ValidationException::withMessages([
                'meter_number' => 'That meter number is already assigned to an active consumer account.',
            ]);
        }

        // Belt and braces: no other account may hold it as an active assignment.
        $conflictingAssignment = MeterAssignment::query()
            ->whereRaw('LOWER(meter_number) = ?', [$normalized])
            ->whereNull('retired_at')
            ->where('consumer_id', '!=', $consumer->id)
            ->whereHas('consumer', fn ($query) => $query->whereNotIn('status', ['Rejected']))
            ->exists();

        if ($conflictingAssignment) {
            throw ValidationException::withMessages([
                'meter_number' => 'That meter number is already assigned to an active consumer account.',
            ]);
        }

        return DB::transaction(function () use ($consumer, $newMeterNumber, $actor, $reason): MeterAssignment {
            // Make sure the meter currently on the account is on record, so the
            // retired number can never be lost.
            $current = $this->recordCurrentMeter($consumer);

            // Create the replacement first so it can be linked from the old row.
            $assignment = MeterAssignment::query()->create([
                'consumer_id' => $consumer->id,
                'meter_number' => $newMeterNumber,
                'assigned_at' => now(),
                'retired_at' => null,
                'reason' => $reason,
                'assigned_by_user_id' => $actor->id,
            ]);

            if ($current && $current->id !== $assignment->id) {
                $current->forceFill([
                    'retired_at' => now(),
                    'replaced_by_assignment_id' => $assignment->id,
                    'reason' => $reason ?: 'Replaced by meter '.$newMeterNumber,
                ])->save();
            }

            // The consumer record now shows the active meter immediately.
            // Saved quietly so the model hook does not add a duplicate row.
            $consumer->forceFill(['meter_number' => $newMeterNumber])->saveQuietly();

            // saveQuietly() skips the model event that stamps first
            // verification, so record it here when this assignment is what
            // completes the account's provisioning.
            if (blank($consumer->verified_at) && $consumer->isVerified()) {
                $consumer->forceFill(['verified_at' => now()])->saveQuietly();
            }

            // saveQuietly() skips model events, so keep the login username in
            // step with the replacement meter explicitly.
            app(ConsumerCredentialService::class)->syncUsername($consumer);

            return $assignment;
        });
    }

    /**
     * Makes sure an active assignment row exists for the consumer's current
     * meter number.
     *
     * Every write path (creating an account, verifying it, editing it) funnels
     * through here so the history is complete no matter how the meter was set.
     */
    public function recordCurrentMeter(Consumer $consumer): ?MeterAssignment
    {
        $number = trim((string) $consumer->meter_number);

        if ($number === '') {
            // The meter was cleared: nothing stays active.
            MeterAssignment::query()
                ->where('consumer_id', $consumer->id)
                ->whereNull('retired_at')
                ->update(['retired_at' => now(), 'reason' => 'Meter number removed from the account']);

            return null;
        }

        $existing = MeterAssignment::query()
            ->where('consumer_id', $consumer->id)
            ->whereRaw('LOWER(meter_number) = ?', [mb_strtolower($number)])
            ->whereNull('retired_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        // The meter on the account changed through a direct edit, so retire the
        // one it replaced: only one meter may ever be active.
        MeterAssignment::query()
            ->where('consumer_id', $consumer->id)
            ->whereNull('retired_at')
            ->get()
            ->each(fn (MeterAssignment $assignment) => $assignment->forceFill([
                'retired_at' => now(),
                'reason' => 'Replaced by meter '.$number.' on the consumer account',
            ])->save());

        return MeterAssignment::query()->create([
            'consumer_id' => $consumer->id,
            'meter_number' => $number,
            'assigned_at' => now(),
            'reason' => $consumer->wasRecentlyCreated
                ? 'Meter assigned with the account'
                : 'Meter recorded on the account',
        ]);
    }

    public function activeAssignmentFor(Consumer $consumer): ?MeterAssignment
    {
        return MeterAssignment::query()
            ->where('consumer_id', $consumer->id)
            ->whereNull('retired_at')
            ->orderByDesc('id')
            ->first();
    }

    public function historyFor(Consumer $consumer)
    {
        return MeterAssignment::query()
            ->where('consumer_id', $consumer->id)
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Finds the consumer by the meter number currently assigned to them.
     */
    public function findConsumerByActiveMeter(string $meterNumber): ?Consumer
    {
        $meterNumber = trim($meterNumber);

        if ($meterNumber === '') {
            return null;
        }

        $consumer = Consumer::query()
            ->whereRaw('LOWER(meter_number) = ?', [mb_strtolower($meterNumber)])
            ->first();

        if ($consumer?->isVerified()) {
            return $consumer;
        }

        // Fall back to an active assignment in case the consumer record lags.
        return MeterAssignment::query()
            ->whereRaw('LOWER(meter_number) = ?', [mb_strtolower($meterNumber)])
            ->whereNull('retired_at')
            ->first()?->consumer ?? $consumer;
    }

    /**
     * Records which meter a reading was taken from, so a later re-assignment
     * never rewrites the history of the old meter.
     */
    public function stampReading(MeterReading $reading): void
    {
        if (filled($reading->meter_number)) {
            return;
        }

        $reading->forceFill(['meter_number' => $reading->consumer?->meter_number])->save();
    }
}
