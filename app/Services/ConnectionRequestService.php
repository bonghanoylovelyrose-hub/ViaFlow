<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\ServiceRequest;

/**
 * Keeps the water connection request record in step with the consumer
 * application.
 *
 * Every online application creates one New Connection request so the
 * administrator has a single reviewable entry in Billing -> Connection
 * Requests. Membership verification and account activation remain separate
 * from the request status: the request only mirrors the connection decision.
 */
class ConnectionRequestService
{
    /**
     * Creates the connection request for a newly submitted application.
     *
     * The method is idempotent: if a New Connection request already exists for
     * the consumer it is returned instead of inserting a duplicate.
     */
    public function createForApplication(Consumer $consumer): ServiceRequest
    {
        $existing = $consumer->serviceRequests()
            ->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return $consumer->serviceRequests()->create([
            'request_type' => ServiceRequest::TYPE_NEW_CONNECTION,
            'status' => ServiceRequest::STATUS_PENDING,
            'notes' => $this->applicationNotes($consumer),
            'current_balance_snapshot' => 0,
            'requested_at' => now(),
        ]);
    }

    /**
     * Records the connection approval once the account is activated.
     */
    public function approve(Consumer $consumer, ?int $reviewerId, ?string $notes = null): ServiceRequest
    {
        return $this->settle(
            $consumer,
            ServiceRequest::STATUS_APPROVED,
            $reviewerId,
            $notes,
        );
    }

    /**
     * Records the connection rejection.
     */
    public function reject(Consumer $consumer, ?int $reviewerId, ?string $notes = null): ServiceRequest
    {
        return $this->settle(
            $consumer,
            ServiceRequest::STATUS_REJECTED,
            $reviewerId,
            $notes,
        );
    }

    /**
     * Returns a rejected application to the administrator after the applicant
     * corrects the requirements.
     *
     * The original request is reopened instead of inserting a new one, so the
     * administrator keeps reviewing the same application reference. The
     * previous rejection note is retained as context until the next decision.
     */
    public function reopenForVerification(Consumer $consumer): ServiceRequest
    {
        $request = $consumer->serviceRequests()
            ->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)
            ->latest('id')
            ->first();

        if (! $request) {
            $request = $consumer->serviceRequests()->create([
                'request_type' => ServiceRequest::TYPE_NEW_CONNECTION,
                'status' => ServiceRequest::STATUS_PENDING,
                'notes' => $this->applicationNotes($consumer),
                'current_balance_snapshot' => 0,
                'requested_at' => now(),
            ]);

            return $request->refresh();
        }

        if ($request->status !== ServiceRequest::STATUS_REJECTED) {
            return $request;
        }

        $request->update([
            'status' => ServiceRequest::STATUS_PENDING,
            'requested_at' => now(),
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
            'notes' => trim((string) $request->notes
                ."\nCorrections resubmitted for verification on ".now()->format('F d, Y g:i A').'.'),
        ]);

        return $request->refresh();
    }

    /**
     * Applies a final decision without ever overwriting an earlier one.
     */
    private function settle(
        Consumer $consumer,
        string $status,
        ?int $reviewerId,
        ?string $notes,
    ): ServiceRequest {
        $request = $consumer->serviceRequests()
            ->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)
            ->latest('id')
            ->first();

        if ($request && $request->status !== ServiceRequest::STATUS_PENDING) {
            return $request;
        }

        if (! $request) {
            $request = $consumer->serviceRequests()->create([
                'request_type' => ServiceRequest::TYPE_NEW_CONNECTION,
                'status' => ServiceRequest::STATUS_PENDING,
                'notes' => $this->applicationNotes($consumer),
                'current_balance_snapshot' => 0,
                'requested_at' => now(),
            ]);
        }

        $request->update([
            'status' => $status,
            'admin_notes' => $notes,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $reviewerId,
        ]);

        return $request->refresh();
    }

    private function applicationNotes(Consumer $consumer): string
    {
        $declared = $consumer->requested_membership_type ?: $consumer->membership_type;

        return 'Water connection application '.$consumer->application_reference
            .'. Declared VVTHOA membership: '.($declared ?: Consumer::MEMBERSHIP_REQUEST_NON_MEMBER).'.';
    }
}
