<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AnnouncementAudienceService
{
    public function visibleTo(Announcement $announcement, User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $announcement->isActive()) return false;
        if ($announcement->audience === 'Everyone') return true;

        return match ($announcement->audience) {
            'Consumers', 'consumer', 'Role:consumer' => $user->role === 'consumer',
            'Member Consumers' => $user->isConsumer() && $user->consumer?->hasVerifiedMembership(),
            'Non-Member Consumers' => $user->isConsumer() && $user->consumer?->membership_type === 'Non-Member',
            'Meter Readers', 'Staff', 'staff', 'meter_reader', 'Role:staff', 'Role:meter_reader' => $user->isMeterReader(),
            'Admins', 'admin', 'Role:admin' => $user->isAdmin(),
            'Specific Users' => in_array((int) $user->id, array_map('intval', $announcement->recipient_user_ids ?? []), true),
            default => false,
        };
    }

    public function applyVisibility(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user) {
            $visible->where('audience', 'Everyone');

            if ($user->isConsumer()) {
                $visible->orWhere('audience', 'Consumers')
                    ->orWhere('audience', 'consumer')
                    ->orWhere('audience', 'Role:consumer');
                if ($user->consumer?->hasVerifiedMembership()) $visible->orWhere('audience', 'Member Consumers');
                if ($user->consumer?->membership_type === 'Non-Member') $visible->orWhere('audience', 'Non-Member Consumers');
            }

            if ($user->isMeterReader()) {
                $visible->orWhereIn('audience', ['Meter Readers', 'Staff', 'staff', 'meter_reader', 'Role:staff', 'Role:meter_reader']);
            }

            if ($user->role === 'admin') {
                $visible->orWhereIn('audience', ['Admins', 'admin', 'Role:admin']);
            }

            $visible->orWhereHas('recipientUsers', fn (Builder $recipients) => $recipients->whereKey($user->id));
        })->active();
    }

    public function recipients(Announcement $announcement): Builder
    {
        $query = User::query()->whereNotNull('email')->where('email', '<>', '');

        return match ($announcement->audience) {
            'Everyone' => $query,
            'Consumers', 'consumer', 'Role:consumer' => $query->where('role', 'consumer'),
            'Member Consumers' => $query->where('role', 'consumer')->whereHas('consumer', fn (Builder $q) => $q->where('membership_type', 'Member')->whereIn('membership_verification_status', ['Verified', 'Not Required'])),
            'Non-Member Consumers' => $query->where('role', 'consumer')->whereHas('consumer', fn (Builder $q) => $q->where('membership_type', 'Non-Member')),
            'Meter Readers', 'Staff', 'staff', 'meter_reader', 'Role:staff', 'Role:meter_reader' => $query->whereIn('role', ['staff', 'meter_reader']),
            'Admins', 'admin', 'Role:admin' => $query->where('role', 'admin'),
            'Specific Users' => $query->whereIn('id', $announcement->recipient_user_ids ?? []),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function audienceOptions(): array
    {
        return [
            'Everyone' => 'Everyone',
            'Consumers' => 'Consumers',
            'Member Consumers' => 'Verified Member Consumers',
            'Non-Member Consumers' => 'Non-Member Consumers',
            'Meter Readers' => 'Meter Readers',
            'Admins' => 'Admins',
            'Specific Users' => 'Specific Users',
        ];
    }
}
