<?php

namespace App\Services;

use App\Models\Consumer;

/**
 * Presents the consumer's service state as one of the four UI-facing statuses.
 *
 * The stored status is only one input: activation records (account and meter
 * numbers) and the disconnection/reconnection timestamps decide whether the
 * water service is actually connected. That keeps legacy or in-flight records
 * from showing an application label that no longer matches the service.
 */
class ConsumerStatusService
{
    public const ACTIVE = 'active';

    public const RECONNECTION_PENDING = 'reconnection_pending';

    public const INACTIVE = 'inactive';

    public const PENDING = 'pending';

    public const REJECTED = 'rejected';

    private const CONNECTED_STATUSES = ['Active', 'For Review'];

    private const PRESENTATIONS = [
        self::ACTIVE => [
            'key' => self::ACTIVE,
            'label' => 'Active',
            'sub_label' => 'Connected',
            'badge_class' => 'badge-soft-success',
            'sub_class' => 'consumer-status-sub-success',
            'icon' => 'bi-check-circle-fill',
            'description' => 'The account is approved and the water service is currently connected.',
        ],
        self::RECONNECTION_PENDING => [
            'key' => self::RECONNECTION_PENDING,
            'label' => 'Active',
            'sub_label' => 'Pending Reconnection',
            'badge_class' => 'badge-soft-success',
            'sub_class' => 'consumer-status-sub-warning',
            'icon' => 'bi-clock-history',
            'description' => 'The account is active and water service is awaiting physical reconnection.',
        ],
        self::INACTIVE => [
            'key' => self::INACTIVE,
            'label' => 'Inactive',
            'sub_label' => 'Disconnected',
            'badge_class' => 'badge-soft-neutral',
            'sub_class' => 'consumer-status-sub-neutral',
            'icon' => 'bi-slash-circle-fill',
            'description' => 'The water service is disconnected or still awaiting an approved reconnection.',
        ],
        self::PENDING => [
            'key' => self::PENDING,
            'label' => 'Pending',
            'sub_label' => 'For Approval',
            'badge_class' => 'badge-soft-warning',
            'sub_class' => 'consumer-status-sub-warning',
            'icon' => 'bi-clock-fill',
            'description' => 'The application is awaiting administrator verification and approval.',
        ],
        self::REJECTED => [
            'key' => self::REJECTED,
            'label' => 'Rejected',
            'sub_label' => 'Not Activated',
            'badge_class' => 'badge-soft-danger',
            'sub_class' => 'consumer-status-sub-danger',
            'icon' => 'bi-x-circle-fill',
            'description' => 'The application was rejected and has not been activated.',
        ],
    ];

    /**
     * @return array{key: string, label: string, sub_label: string, badge_class: string, sub_class: string, icon: string, description: string}
     */
    public function present(Consumer $consumer): array
    {
        if ($consumer->status === Consumer::STATUS_PENDING_REQUIREMENTS) {
            return [
                'sub_label' => Consumer::STATUS_PENDING_REQUIREMENTS,
                'description' => 'The application is awaiting required documents.',
            ] + self::PRESENTATIONS[self::PENDING];
        }

        return self::PRESENTATIONS[$this->key($consumer)];
    }

    public function key(Consumer $consumer): string
    {
        $status = trim((string) $consumer->status);

        // Rejected applications have no service to report, even if a later
        // reactivation left old rejection timestamps behind.
        if ($status === 'Rejected') {
            return self::REJECTED;
        }

        if ($consumer->reconnection_pending) {
            return self::RECONNECTION_PENDING;
        }

        // A completed reconnection is the strongest record that supply was
        // restored. The workflow status may still read "For Reconnection".
        if ($this->isProvisioned($consumer) && $this->wasReconnected($consumer)) {
            return self::ACTIVE;
        }

        // Disconnection records and disconnected workflow statuses both mean
        // the service is currently off.
        if (in_array($status, Consumer::INACTIVE_STATUSES, true)
            || $consumer->disconnected_at !== null) {
            return self::INACTIVE;
        }

        // An "Active" label without an account and meter number is an
        // approval that never completed, so it is not connected service yet.
        if (in_array($status, self::CONNECTED_STATUSES, true)) {
            return $this->isProvisioned($consumer) ? self::ACTIVE : self::PENDING;
        }

        return self::PENDING;
    }

    private function isProvisioned(Consumer $consumer): bool
    {
        return filled($consumer->account_number) && filled($consumer->meter_number);
    }

    /**
     * The service counts as reconnected only when the reconnection timestamp is
     * at least as new as the latest disconnection timestamp.
     */
    private function wasReconnected(Consumer $consumer): bool
    {
        if ($consumer->reconnected_at === null) {
            return false;
        }

        if ($consumer->disconnected_at === null) {
            return true;
        }

        return $consumer->reconnected_at->greaterThanOrEqualTo($consumer->disconnected_at);
    }
}
