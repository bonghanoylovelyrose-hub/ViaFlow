<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\User;
use App\Notifications\ConnectionApplicationResubmitted;
use App\Notifications\NewConnectionRequest;
use App\Rules\FileMimeType;
use App\Rules\ValidEmail;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ConnectionApplicationService
{
    public const MEMBER_DOCUMENT_TYPES = ['Housing Loan Contract (Pag-IBIG)', 'Proof of Residency'];

    public function __construct(
        private ConnectionRequestService $requests,
        private ConnectionApplicationCorrectionService $corrections,
        private ConsumerEmailUrl $emailUrls,
    ) {}

    public function applicantRules(bool $member, bool $allowMissingDocuments = false): array
    {
        $area = config('service_area');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', new ValidEmail],
            'service_country' => ['required', Rule::in([$area['country']])],
            'service_city' => ['required', Rule::in([$area['city']])],
            'service_barangay_subdivision' => ['required', Rule::in([$area['barangay_subdivision']])],
            'service_purok' => ['required', Rule::in($area['puroks'])],
            'address_block' => ['required', 'string', 'max:100'],
            'address_street' => ['required', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'regex:'.ConnectionApplicationCorrectionService::CONTACT_NUMBER_PATTERN],
            'property_type' => ['required', 'in:Residential,Commercial'],
            'ownership_status' => ['required', 'in:Owner,Tenant'],
            'acknowledgment_accepted' => [$member ? 'nullable' : 'accepted'],
        ] + $this->idRules($allowMissingDocuments);
    }

    public function memberRules(bool $allowMissingDocuments = false): array
    {
        return [
            'housing_rights_documented' => ['required', 'in:yes,no'],
            'resident_of_community' => ['required', 'in:yes,no'],
            'association_member' => ['required', 'in:yes,no'],
            'association_registered' => ['required', 'in:yes,no'],
            'membership_renewal_date' => ['nullable', 'date'],
            'other_document_details' => ['nullable', 'string', 'max:255'],
            'agreement_accepted' => ['accepted'],
        ] + $this->membershipDocumentRules($allowMissingDocuments);
    }

    private function idRules(bool $optional = false): array
    {
        return [
            'valid_id_presented' => $optional
                ? ['nullable', 'required_with:valid_id_file', 'string', 'max:100']
                : ['required', 'string', 'max:100'],
            'valid_id_file' => [$optional ? 'nullable' : 'required', 'file', 'extensions:jpg,jpeg,png,pdf', new FileMimeType('jpg,jpeg,png,pdf')],
        ];
    }

    public function membershipDocumentRules(bool $optional = false): array
    {
        return [
            'membership_document_type' => $optional
                ? ['nullable', 'required_with:membership_document_photos', Rule::in(self::MEMBER_DOCUMENT_TYPES)]
                : ['required', Rule::in(self::MEMBER_DOCUMENT_TYPES)],
            'membership_document_photos' => [$optional ? 'nullable' : 'required', 'array', 'min:1'],
            'membership_document_photos.*' => ['required', 'file', 'extensions:jpg,jpeg,png', new FileMimeType('jpg,jpeg,png')],
        ];
    }

    public function storeMemberDetails(array $validated): array
    {
        return [
            'housing_rights_documented' => $validated['housing_rights_documented'] === 'yes',
            'resident_of_community' => $validated['resident_of_community'] === 'yes',
            'association_member' => $validated['association_member'] === 'yes',
            'association_registered' => $validated['association_registered'] === 'yes',
            'membership_renewal_date' => $validated['membership_renewal_date'] ?? null,
            'other_document_details' => $validated['other_document_details'] ?? null,
            'agreement_accepted' => true,
        ] + $this->storeMembershipDocuments($validated);
    }

    public function submit(array $validated, bool $member, array $memberDetails = [], bool $allowMissingDocuments = false): Consumer
    {
        $membership = $member ? Consumer::MEMBERSHIP_REQUEST_MEMBER : Consumer::MEMBERSHIP_REQUEST_NON_MEMBER;
        $validId = null;
        $newMemberFiles = [];

        try {
            if ($member && $allowMissingDocuments) {
                $memberDetails = $this->storeMemberDetails($validated);
                $newMemberFiles = $memberDetails['membership_document_paths'];
            }
            if (isset($validated['valid_id_file'])) {
                $validId = $validated['valid_id_file']->store('requirements/valid-id', 'local');
                throw_unless($validId, new RuntimeException('Could not store the valid ID.'));
            }

            $missingDocuments = ! $validId || ($member && empty($memberDetails['membership_document_paths']));

            $consumer = DB::transaction(function () use ($validated, $member, $membership, $memberDetails, $validId, $missingDocuments, $allowMissingDocuments): Consumer {
                $consumer = Consumer::create([
                    'full_name' => trim($validated['first_name'].' '.$validated['last_name']),
                    'email' => strtolower(trim($validated['email'])),
                    'status' => $allowMissingDocuments && $missingDocuments ? Consumer::STATUS_PENDING_REQUIREMENTS : 'Pending Verification',
                    'membership_type' => $membership,
                    'requested_membership_type' => $membership,
                    'membership_verification_status' => $member ? Consumer::MEMBERSHIP_VERIFICATION_PENDING : Consumer::MEMBERSHIP_VERIFICATION_NOT_REQUIRED,
                    'property_type' => $validated['property_type'],
                    'ownership_status' => $validated['ownership_status'],
                    'valid_id_presented' => $validated['valid_id_presented'] ?? null,
                    'valid_id_path' => $validId,
                    'valid_id_submitted' => $validId !== null,
                    'address_block' => $validated['address_block'],
                    'address_street' => $validated['address_street'],
                    'service_purok' => $validated['service_purok'],
                    'service_address' => $this->corrections->formatServiceAddress($validated['service_purok'], $validated['address_block'], $validated['address_street']),
                    'contact_number' => $validated['contact_number'] ?? null,
                    'acknowledgment_accepted' => ! $member,
                ] + $memberDetails + ['association_member' => $member, 'membership_document_paths' => []]);

                $this->requests->createForApplication($consumer);

                return $consumer;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(array_filter([$validId, ...$newMemberFiles]));
            throw $exception;
        }

        Notification::send(User::query()->where('role', 'admin')->get(), new NewConnectionRequest($consumer));

        return $consumer;
    }

    public function missingRequirements(Consumer $consumer): array
    {
        return [
            'validId' => blank($consumer->valid_id_path),
            'membershipDocument' => $consumer->isMemberApplicant() && empty($consumer->membership_document_paths),
        ];
    }

    public function requiredCorrections(Consumer $consumer): array
    {
        return $consumer->status === Consumer::STATUS_PENDING_REQUIREMENTS
            ? $this->missingRequirementFields($consumer)
            : [];
    }

    public function missingRequirementFields(Consumer $consumer): array
    {
        $missing = $this->missingRequirements($consumer);
        $fields = [];
        if ($missing['validId']) {
            $fields[] = ConnectionApplicationCorrectionService::FIELD_VALID_ID_FILE;
            if (blank($consumer->valid_id_presented)) {
                $fields[] = ConnectionApplicationCorrectionService::FIELD_VALID_ID_PRESENTED;
            }
        }
        if ($missing['membershipDocument']) {
            $fields[] = ConnectionApplicationCorrectionService::FIELD_MEMBERSHIP_DOCUMENTS;
        }

        return $fields;
    }

    public function completeRequirements(Request $request, Consumer $consumer): bool
    {
        $paths = [];

        try {
            $completed = DB::transaction(function () use ($request, $consumer, &$paths): bool {
                $locked = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
                if ($locked->status !== Consumer::STATUS_PENDING_REQUIREMENTS) {
                    return false;
                }

                $missing = $this->missingRequirements($locked);
                $rules = $missing['validId'] ? $this->idRules() : [];
                if ($missing['validId'] && filled($locked->valid_id_presented)) {
                    unset($rules['valid_id_presented']);
                }
                if ($missing['membershipDocument']) {
                    $rules += $this->membershipDocumentRules();
                }
                $validated = $request->validate($rules);

                $attributes = ['status' => 'Pending Verification', 'resubmitted_at' => now()];
                if ($missing['validId']) {
                    $validId = $validated['valid_id_file']->store('requirements/valid-id', 'local');
                    throw_unless($validId, new RuntimeException('Could not store the valid ID.'));
                    $paths[] = $validId;
                    $attributes += [
                        'valid_id_path' => $validId,
                        'valid_id_presented' => $validated['valid_id_presented'] ?? $locked->valid_id_presented,
                        'valid_id_submitted' => true,
                    ];
                }
                if ($missing['membershipDocument']) {
                    $documents = $this->storeMembershipDocuments($validated);
                    $paths = array_merge($paths, $documents['membership_document_paths']);
                    $attributes += $documents;
                }
                $locked->update($attributes);
                $this->requests->reopenForVerification($locked);

                return true;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        if ($completed) {
            Notification::send(User::query()->where('role', 'admin')->get(), new ConnectionApplicationResubmitted($consumer->fresh()));
        }

        return $completed;
    }

    public function storeMembershipDocuments(array $validated): array
    {
        $paths = [];
        try {
            foreach ($validated['membership_document_photos'] ?? [] as $photo) {
                $path = $photo->store('requirements/membership-documents', 'local');
                throw_unless($path, new RuntimeException('Could not store a membership document.'));
                $paths[] = $path;
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
        $type = $validated['membership_document_type'] ?? null;

        return [
            'membership_document_type' => $type,
            'membership_document_paths' => $paths,
            'housing_loan_contract_submitted' => $paths !== [] && $type === self::MEMBER_DOCUMENT_TYPES[0],
            'proof_of_residency_submitted' => $paths !== [] && $type === self::MEMBER_DOCUMENT_TYPES[1],
        ];
    }

    public function sendRequirementsLink(Consumer $consumer, bool $force = false): string
    {
        $recipient = trim((string) $consumer->email);

        return ConsumerNotificationLog::sendOnce(
            consumerId: $consumer->id,
            event: ConsumerNotificationLog::EVENT_CONNECTION_REQUIREMENTS_REQUESTED,
            recipient: $recipient,
            send: function () use ($consumer, $recipient): void {
                if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('A valid registered email address is required to send the requirements link.');
                }

                $path = URL::temporarySignedRoute('connection.requirements.edit', now()->addDays(30), [
                    'consumer' => $consumer->id,
                ], absolute: false);
                $url = $this->emailUrls->forConnectionApplication($path, request());

                if (! $this->usesDeliveryTransport((string) config('mail.default'))) {
                    throw new RuntimeException('The requirements email needs a mail transport that submits messages to a mail server.');
                }

                $message = Mail::send('emails.consumer.connection-requirements', [
                    'consumer' => $consumer,
                    'url' => $url,
                    'missing' => $this->missingRequirements($consumer),
                ], fn ($message) => $message->to($recipient)->subject('Water connection application requires documents'));
                throw_unless($message, new RuntimeException('The mailer did not submit the requirements email.'));
            },
            force: $force,
        );
    }

    public function sendRejectionEmail(Consumer $consumer, bool $force = false): string
    {
        $recipient = trim((string) $consumer->email);

        return ConsumerNotificationLog::sendOnce(
            consumerId: $consumer->id,
            event: ConsumerNotificationLog::EVENT_CONNECTION_REJECTED.'_'.$consumer->rejection_count,
            recipient: $recipient,
            send: function () use ($consumer, $recipient): void {
                if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('A valid registered email address is required to send the rejection email.');
                }
                if (! $this->usesDeliveryTransport((string) config('mail.default'))) {
                    throw new RuntimeException('The rejection email needs a mail transport that submits messages to a mail server.');
                }

                $linkExpiresAt = now()->addDays(30);
                $resubmitUrl = $this->publicCorrectionUrl($consumer, $linkExpiresAt);
                $message = Mail::send('emails.consumer.connection-rejected', [
                    'consumer' => $consumer,
                    'reason' => $consumer->rejection_reason,
                    'correctionLabels' => $this->corrections->labelsFor($this->corrections->normalize($consumer->rejection_fields, $consumer->rejection_reason)),
                    'resubmitUrl' => $resubmitUrl,
                    'linkExpiresAt' => $linkExpiresAt,
                ], fn ($message) => $message->to($recipient)->subject(config('app.name', 'ViaFlow System').' Connection Request Status'));
                throw_unless($message, new RuntimeException('The mailer did not submit the rejection email.'));
            },
            force: $force,
        );
    }

    private function publicCorrectionUrl(Consumer $consumer, DateTimeInterface $expiration): string
    {
        $origin = $this->emailUrls->forConnectionApplication('', request());
        $urls = clone app('url');
        $urls->setRequest(request());
        $urls->useOrigin($origin);
        $urls->forceScheme(parse_url($origin, PHP_URL_SCHEME));

        return $urls->temporarySignedRoute('connection.resubmit.edit', $expiration, [
            'consumer' => $consumer->id,
            'rejection' => $consumer->rejection_count,
        ]);
    }

    private function usesDeliveryTransport(string $mailer): bool
    {
        $config = config('mail.mailers.'.$mailer, []);

        return match ($config['transport'] ?? null) {
            'array' => app()->runningUnitTests(),
            'log', null => false,
            'failover', 'roundrobin' => ! empty($config['mailers'])
                && collect($config['mailers'])->every(fn (string $member) => $this->usesDeliveryTransport($member)),
            default => true,
        };
    }
}
