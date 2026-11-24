<?php

namespace App\Models;

use App\Services\ConsumerBalanceService;
use App\Services\ConsumerCredentialService;
use App\Services\MeterAssignmentService;
use App\Services\OverdueMonitoringService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Consumer extends Model
{
    /**
     * Statuses that represent an account the association has verified and
     * activated. Accounts that later fall into arrears or get disconnected
     * remain verified - only Pending Verification and Rejected are excluded,
     * because those have never cleared admin verification.
     */
    public const VERIFIED_STATUSES = ['Active', 'For Review', 'Inactive', 'Disconnected', 'For Reconnection'];

    /**
     * Statuses whose water service is currently connected. The meter reading
     * queue only includes these accounts; Disconnected and For Reconnection
     * accounts are still verified historically but cannot be read.
     */
    public const READING_QUEUE_STATUSES = ['Active', 'For Review'];

    /**
     * Account categories used by the Consumer Management sidebar. For Review
     * accounts still have active service but need follow-up, while For
     * Reconnection accounts are inactive until reconnection is approved.
     */
    public const INACTIVE_STATUSES = ['Inactive', 'Disconnected', 'For Reconnection'];

    public const STATUS_PENDING_REQUIREMENTS = 'Pending Requirements';

    public const ACCOUNT_CATEGORY_STATUSES = [
        'pending' => ['Pending', 'Pending Verification', self::STATUS_PENDING_REQUIREMENTS],
        'rejected' => ['Rejected'],
        'active' => ['Active', 'For Review'],
        'inactive' => self::INACTIVE_STATUSES,
    ];

    public const DISCONNECTION_ELIGIBLE_STATUSES = self::INACTIVE_STATUSES;

    public const MEMBERSHIP_REQUEST_MEMBER = 'Member';

    public const MEMBERSHIP_REQUEST_NON_MEMBER = 'Non-Member';

    public const MEMBERSHIP_VERIFICATION_PENDING = 'Pending';

    public const MEMBERSHIP_VERIFICATION_VERIFIED = 'Verified';

    public const MEMBERSHIP_VERIFICATION_REJECTED = 'Rejected';

    public const MEMBERSHIP_VERIFICATION_NOT_REQUIRED = 'Not Required';

    public const MEMBERSHIP_VERIFICATION_STATUSES = [
        self::MEMBERSHIP_VERIFICATION_PENDING,
        self::MEMBERSHIP_VERIFICATION_VERIFIED,
        self::MEMBERSHIP_VERIFICATION_REJECTED,
        self::MEMBERSHIP_VERIFICATION_NOT_REQUIRED,
    ];

    protected $fillable = [
        'reconnection_fee',
        'reconnection_fee_bill_id',
        'reconnection_date',
        'installation_time',
        'reconnection_scheduled_at',
        'application_reference',
        'user_id',
        'full_name',
        'email',
        'account_number',
        'meter_number',
        'status',
        'membership_type',
        'requested_membership_type',
        'membership_verification_status',
        'membership_reviewed_at',
        'membership_reviewed_by',
        'membership_verification_notes',
        'verified_at',
        'membership_started_at',
        'property_type',
        'ownership_status',
        'valid_id_presented',
        'valid_id_path',
        'housing_rights_documented',
        'resident_of_community',
        'association_member',
        'association_registered',
        'housing_loan_contract_submitted',
        'valid_id_submitted',
        'proof_of_residency_submitted',
        'membership_document_type',
        'membership_document_paths',
        'proof_of_residency_path',
        'other_document_details',
        'other_document_path',
        'membership_renewal_date',
        'installation_date',
        'installation_completed_at',
        'installation_completed_by',
        'rejection_reason',
        'rejection_fields',
        'rejection_count',
        'rejected_at',
        'resubmitted_at',
        'full_payment_verified_at',
        'disconnected_at',
        'reconnected_at',
        'service_address',
        'service_purok',
        'contact_number',
        'address_block',
        'address_street',
        'acknowledgment_accepted',
        'agreement_accepted',
    ];

    public function installationCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installation_completed_by')->withTrashed();
    }

    protected $hidden = ['connection_approval_credentials'];

    protected function casts(): array
    {
        return [
            'housing_rights_documented' => 'boolean',
            'resident_of_community' => 'boolean',
            'association_member' => 'boolean',
            'association_registered' => 'boolean',
            'housing_loan_contract_submitted' => 'boolean',
            'valid_id_submitted' => 'boolean',
            'proof_of_residency_submitted' => 'boolean',
            'membership_document_paths' => 'array',
            'membership_renewal_date' => 'date',
            'membership_started_at' => 'date',
            'membership_reviewed_at' => 'datetime',
            'verified_at' => 'datetime',
            'installation_date' => 'date',
            'installation_completed_at' => 'datetime',
            'connection_approval_credentials' => 'encrypted:array',
            'rejection_fields' => 'array',
            'rejection_count' => 'integer',
            'rejected_at' => 'datetime',
            'resubmitted_at' => 'datetime',
            // Set when an administrator verifies the payment that cleared the
            // balance; it is what unlocks the reconnection request.
            'full_payment_verified_at' => 'datetime',
            'reconnection_fee' => 'decimal:2',
            'reconnection_date' => 'date',
            'reconnection_scheduled_at' => 'datetime',
            'reconnection_pending' => 'boolean',
            'disconnected_at' => 'datetime',
            'reconnected_at' => 'datetime',
            'acknowledgment_accepted' => 'boolean',
            'agreement_accepted' => 'boolean',
        ];
    }

    /**
     * Match the service states shown by Payment Monitoring and Consumer
     * Management, including legacy rows whose status was not updated when the
     * physical disconnection was recorded.
     */
    public function scopeInactiveAccounts(Builder $query): Builder
    {
        return $query
            ->where('reconnection_pending', false)
            ->where(function (Builder $inactiveQuery): void {
                $inactiveQuery
                    ->whereIn('status', self::INACTIVE_STATUSES)
                    ->orWhereNotNull('disconnected_at');
            })
            // A successful reconnection is authoritative even if an older row
            // still carries an inactive/disconnected status label.
            ->where(function (Builder $notReconnectedQuery): void {
                $notReconnectedQuery
                    ->whereNull('reconnected_at')
                    ->orWhere(function (Builder $olderDisconnectionQuery): void {
                        $olderDisconnectionQuery
                            ->whereNotNull('disconnected_at')
                            ->whereColumn('reconnected_at', '<', 'disconnected_at');
                    });
            });
    }

    public function scopeActiveAccounts(Builder $query): Builder
    {
        return $query
            ->where('status', 'Active')
            ->where(function (Builder $connectedQuery): void {
                $connectedQuery
                    ->where('reconnection_pending', true)
                    ->orWhereNull('disconnected_at')
                    ->orWhere(function (Builder $reconnectedQuery): void {
                        $reconnectedQuery
                            ->whereNotNull('reconnected_at')
                            ->whereColumn('reconnected_at', '>=', 'disconnected_at');
                    });
            });
    }

    protected static function booted(): void
    {
        static::creating(function (Consumer $consumer): void {
            if (blank($consumer->application_reference)) {
                do {
                    $reference = 'VVA-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
                } while (self::query()->where('application_reference', $reference)->exists());

                $consumer->application_reference = $reference;
            }

            if (blank($consumer->requested_membership_type)) {
                $consumer->requested_membership_type = $consumer->membership_type === self::MEMBERSHIP_REQUEST_MEMBER
                    ? self::MEMBERSHIP_REQUEST_MEMBER
                    : self::MEMBERSHIP_REQUEST_NON_MEMBER;
            }

            if (blank($consumer->membership_verification_status)) {
                $consumer->membership_verification_status = match (true) {
                    $consumer->membership_type === self::MEMBERSHIP_REQUEST_MEMBER => self::MEMBERSHIP_VERIFICATION_VERIFIED,
                    $consumer->requested_membership_type === self::MEMBERSHIP_REQUEST_MEMBER => self::MEMBERSHIP_VERIFICATION_PENDING,
                    default => self::MEMBERSHIP_VERIFICATION_NOT_REQUIRED,
                };
            }

            if (in_array($consumer->membership_verification_status, [
                self::MEMBERSHIP_VERIFICATION_VERIFIED,
                self::MEMBERSHIP_VERIFICATION_REJECTED,
            ], true)) {
                $consumer->membership_reviewed_at ??= now();
                $consumer->membership_reviewed_by ??= auth()->id();
            }
        });

        static::saving(function (Consumer $consumer): void {
            if ($consumer->isDirty('status')) {
                $previousStatus = $consumer->getOriginal('status');
                $newStatus = $consumer->status;

                if ($newStatus === 'Disconnected' && $previousStatus !== 'Disconnected') {
                    if (! $consumer->isDirty('disconnected_at')) {
                        $consumer->disconnected_at = now();
                    }
                    $consumer->reconnected_at = null;
                }

                if ($newStatus === 'Active'
                    && in_array($previousStatus, ['Disconnected', 'For Reconnection'], true)
                    && ! $consumer->reconnection_pending
                    && ! $consumer->isDirty('reconnected_at')) {
                    $consumer->reconnected_at = now();
                }
            }

            // The queue is ordered by the moment the account became readable.
            // Stamp it once on any path that unlocks meter readings (Verify and
            // Activate, a direct edit, or an already-active import) and never
            // move it afterwards, so later edits and new billing months cannot
            // change the account's real verification timestamp.
            if (blank($consumer->verified_at) && $consumer->isVerified()) {
                $consumer->verified_at = now();
            }

            if ($consumer->membership_type !== 'Member') {
                $consumer->membership_started_at = null;

                return;
            }

            $becameMember = $consumer->isDirty('membership_type')
                && $consumer->getOriginal('membership_type') !== 'Member';
            $becameActive = $consumer->isDirty('status')
                && $consumer->status === 'Active'
                && $consumer->getOriginal('status') !== 'Active';
            $becameVerifiedMember = $consumer->isDirty('membership_verification_status')
                && $consumer->membership_verification_status === self::MEMBERSHIP_VERIFICATION_VERIFIED;

            if ($consumer->status === 'Active'
                && blank($consumer->membership_started_at)
                && ($consumer->exists ? ($becameMember || $becameActive || $becameVerifiedMember) : true)) {
                $consumer->membership_started_at = today();
            }
        });

        // Whatever path sets the meter number, the assignment history follows it
        // so a replacement is always traceable, and the consumer's login
        // username stays aligned with the meter assigned to the account.
        static::saved(function (Consumer $consumer): void {
            if (! $consumer->isDirty('meter_number')) {
                return;
            }

            app(ConsumerCredentialService::class)->syncUsername($consumer);
            app(MeterAssignmentService::class)->recordCurrentMeter($consumer);
        });
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function reconnectionFeeBill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'reconnection_fee_bill_id');
    }

    public function reconnections(): HasMany
    {
        return $this->hasMany(ConsumerReconnection::class);
    }

    public function latestReconnection(): HasOne
    {
        return $this->hasOne(ConsumerReconnection::class)->latestOfMany();
    }

    public function disconnectionNotices(): HasMany
    {
        return $this->hasMany(DisconnectionNotice::class);
    }

    public function activeDisconnectionNotice(): ?DisconnectionNotice
    {
        return $this->disconnectionNotices()
            ->active()
            ->latest('issued_at')
            ->first();
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function installationRescheduleRequests(): HasMany
    {
        return $this->hasMany(InstallationRescheduleRequest::class);
    }

    public function pendingInstallationRescheduleRequest(): ?InstallationRescheduleRequest
    {
        return $this->installationRescheduleRequests()
            ->where('status', InstallationRescheduleRequest::STATUS_PENDING)
            ->where('reschedule_type', InstallationRescheduleRequest::TYPE_CONNECTION)
            ->latest('id')
            ->first();
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function syncAccountStanding(bool $notify = false): void
    {
        app(OverdueMonitoringService::class)->evaluate($this, $notify);
    }

    /**
     * An account is verified once the association has activated it: it is
     * Active and has been issued both an account and a meter number.
     */
    public function isVerified(): bool
    {
        return in_array($this->status, self::VERIFIED_STATUSES, true)
            && filled($this->account_number)
            && filled($this->meter_number);
    }

    public function hasSubmittedConnectionDocuments(): bool
    {
        return filled($this->valid_id_path) || filled($this->proof_of_residency_path)
            || filled($this->other_document_path) || ! empty($this->membership_document_paths);
    }

    public function isAwaitingConnectionReview(): bool
    {
        return in_array($this->status, ['Pending', 'Pending Verification', self::STATUS_PENDING_REQUIREMENTS, 'For Review'], true)
            && ! $this->isVerified();
    }

    /**
     * Member benefits follow verification, not the classification label alone.
     * A Member whose claim is still Pending therefore remains classified as a
     * Member without receiving the member rate or member-only loan access.
     */
    public function hasVerifiedMembership(): bool
    {
        return $this->membership_type === self::MEMBERSHIP_REQUEST_MEMBER
            && in_array($this->membership_verification_status, [
                self::MEMBERSHIP_VERIFICATION_VERIFIED,
                self::MEMBERSHIP_VERIFICATION_NOT_REQUIRED,
            ], true);
    }

    public function isVerifiedViohoaMember(): bool
    {
        return $this->isVerified() && $this->hasVerifiedMembership();
    }

    public function isMemberApplicant(): bool
    {
        return $this->requested_membership_type === self::MEMBERSHIP_REQUEST_MEMBER;
    }

    public function hasPendingMembershipVerification(): bool
    {
        return $this->isMemberApplicant()
            && $this->membership_verification_status === self::MEMBERSHIP_VERIFICATION_PENDING;
    }

    public function hasOutstandingBalance(): bool
    {
        return app(ConsumerBalanceService::class)->totalOutstandingFor($this->id) > 0;
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query
            ->whereIn('status', self::VERIFIED_STATUSES)
            ->whereNotNull('account_number')
            ->where('account_number', '<>', '')
            ->whereNotNull('meter_number')
            ->where('meter_number', '<>', '');
    }

    /**
     * Accounts that may appear in the meter reader's pending queue: verified,
     * provisioned, and currently connected. A disconnected account keeps the
     * verified flag for its history, but it has no active water service to
     * read until an approved reconnection restores it.
     */
    public function scopeReadingQueueEligible(Builder $query): Builder
    {
        return $query
            ->whereIn('status', self::READING_QUEUE_STATUSES)
            ->where('reconnection_pending', false)
            ->whereNotNull('account_number')
            ->where('account_number', '<>', '')
            ->whereNotNull('meter_number')
            ->where('meter_number', '<>', '')
            ->where(function (Builder $query): void {
                $query->whereNull('disconnected_at')
                    ->orWhere(function (Builder $query): void {
                        $query->whereNotNull('reconnected_at')
                            ->whereColumn('reconnected_at', '>=', 'disconnected_at');
                    });
            });
    }
}



