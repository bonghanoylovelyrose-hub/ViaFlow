<?php

namespace App\Http\Controllers;

use App\Models\Consumer;
use App\Models\User;
use App\Notifications\ConnectionApplicationResubmitted;
use App\Services\ConnectionApplicationCorrectionService;
use App\Services\ConnectionApplicationService;
use App\Services\ConnectionRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Handles the correction of a rejected water connection application.
 *
 * The applicant has no login yet, so the workflow is reached through the
 * temporary signed link in the rejection email. Each request revalidates the
 * application status, and only the requirements the administrator identified
 * can be changed.
 */
class ConnectionResubmissionController extends Controller
{
    public function __construct(
        private readonly ConnectionApplicationCorrectionService $corrections,
    ) {}

    public function edit(Request $request, Consumer $consumer, ConnectionApplicationService $applications): Response
    {
        if ($request->routeIs('connection.requirements.edit')) {
            return response()->view('consumer.connection.resubmit', [
                'consumer' => $consumer,
                'missingRequirements' => $applications->missingRequirements($consumer),
                'requirementsUrl' => url(URL::temporarySignedRoute('connection.requirements.store', Carbon::createFromTimestamp($request->integer('expires')), [
                    'consumer' => $consumer->id,
                ], absolute: false)),
                'correctionFields' => [],
                'documentUrls' => [],
            ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
        }

        $this->ensureCurrentRejection($request, $consumer);
        $correctionFields = $this->corrections->normalize(
            $consumer->rejection_fields,
            $consumer->rejection_reason,
        );

        return response()->view('consumer.connection.resubmit', [
            'consumer' => $consumer,
            'correctionFields' => $correctionFields,
            'correctionLabels' => $this->corrections->labelsFor($correctionFields),
            'serviceArea' => config('service_area'),
            'servicePurok' => $consumer->service_purok ?: $this->corrections->purokFromAddress($consumer->service_address),
            'submitted' => $consumer->status !== 'Rejected',
            'documentUrls' => $this->documentUrls($request, $consumer),
            'linkExpiresAt' => $this->linkExpiresAt($request),
            'correctionAction' => $this->correctionUrl($request, 'connection.resubmit.store', $consumer),
        ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, Consumer $consumer, ConnectionApplicationService $applications): RedirectResponse
    {
        if ($request->routeIs('connection.requirements.store')) {
            $completed = $applications->completeRequirements($request, $consumer);

            return redirect()->to(URL::temporarySignedRoute('connection.requirements.edit', Carbon::createFromTimestamp($request->integer('expires')), [
                'consumer' => $consumer->id,
            ], absolute: false))->with('success', $completed
                ? 'Your requirements were submitted. The application is pending verification.'
                : 'This application is no longer awaiting requirements.');
        }

        $documentPaths = [];
        $membershipPaths = [];
        try {
            $resubmitted = DB::transaction(function () use ($request, $consumer, $applications, &$documentPaths, &$membershipPaths): bool {
                $consumer = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
                $this->ensureCurrentRejection($request, $consumer);
                if ($consumer->status !== 'Rejected') {
                    return false;
                }

                $fields = $this->corrections->normalize($consumer->rejection_fields, $consumer->rejection_reason);
                $correctMembershipDocuments = in_array(ConnectionApplicationCorrectionService::FIELD_MEMBERSHIP_DOCUMENTS, $fields, true);
                $rules = $this->corrections->validationRules($fields);
                if ($correctMembershipDocuments) {
                    $rules = $applications->membershipDocumentRules() + $rules;
                }
                $validated = $request->validate($rules, $this->corrections->validationMessages());
                $this->storeCorrectedDocuments($request, $fields, $documentPaths);
                $updates = $this->corrections->applyCorrections($consumer, $fields, $validated, $documentPaths);
                if ($correctMembershipDocuments) {
                    $documents = $applications->storeMembershipDocuments($validated);
                    $membershipPaths = $documents['membership_document_paths'];
                    $updates += $documents;
                }

                $consumer->update($updates + $this->reopenedMembershipAttributes($consumer) + [
                    'status' => 'Pending Verification',
                    'resubmitted_at' => now(),
                ]);
                app(ConnectionRequestService::class)->reopenForVerification($consumer);

                return true;
            });
        } catch (Throwable $exception) {
            if ($documentPaths || $membershipPaths) {
                Storage::disk('local')->delete([...array_values($documentPaths), ...$membershipPaths]);
            }
            throw $exception;
        }

        $response = redirect()->to($this->correctionUrl($request, 'connection.resubmit.edit', $consumer));
        if (! $resubmitted) {
            return $response->with('info', 'This application has already been resubmitted or is no longer awaiting corrections.');
        }

        Notification::send(
            User::query()->where('role', 'admin')->get(),
            new ConnectionApplicationResubmitted($consumer->fresh()),
        );

        return $response->with(
            'success',
            'Your corrections have been submitted. Application '.$consumer->application_reference
            .' is now pending verification by the administrator.',
        );
    }

    public function document(Request $request, Consumer $consumer, string $field): StreamedResponse
    {
        $this->ensureCurrentRejection($request, $consumer);
        abort_unless($this->corrections->isDocumentField($field), 404);

        abort_unless(
            in_array($consumer->status, ['Rejected', 'Pending Verification', 'Active'], true),
            403,
        );

        $path = $consumer->{$this->corrections->documentColumn($field)};

        abort_unless(filled($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    /**
     * @param  list<string>  $correctionFields
     * @param  array<string, string>  $paths
     */
    private function storeCorrectedDocuments(Request $request, array $correctionFields, array &$paths): void
    {
        foreach ($this->corrections->documentFields() as $field) {
            if (! in_array($field, $correctionFields, true) || ! $request->hasFile($field)) {
                continue;
            }

            $file = $request->file($field);

            $path = $file->storeAs(
                $this->corrections->documentDirectory($field),
                time().'_'.Str::random(12).'.'.$file->getClientOriginalExtension(),
                'local',
            );
            throw_unless($path, new RuntimeException('Could not store the corrected requirement.'));
            $paths[$field] = $path;
        }
    }

    /**
     * A membership claim rejected as part of the connection rejection returns
     * to Pending, so the administrator reviews it again with the corrected
     * application. A membership that was verified before the rejection is
     * preserved.
     *
     * @return array<string, mixed>
     */
    private function reopenedMembershipAttributes(Consumer $consumer): array
    {
        if ($consumer->requested_membership_type !== Consumer::MEMBERSHIP_REQUEST_MEMBER) {
            return [];
        }

        if ($consumer->membership_verification_status !== Consumer::MEMBERSHIP_VERIFICATION_REJECTED) {
            return [];
        }

        if ($consumer->membership_verification_notes !== $consumer->rejection_reason) {
            return [];
        }

        return [
            'membership_type' => Consumer::MEMBERSHIP_REQUEST_MEMBER,
            'membership_verification_status' => Consumer::MEMBERSHIP_VERIFICATION_PENDING,
            'membership_reviewed_at' => null,
            'membership_reviewed_by' => null,
            'membership_verification_notes' => null,
        ];
    }

    /**
     * Short-lived signed URLs so the applicant can look at documents already
     * on file while deciding whether a replacement is needed.
     *
     * @return array<string, string>
     */
    private function documentUrls(Request $request, Consumer $consumer): array
    {
        $urls = [];

        foreach ($this->corrections->documentFields() as $field) {
            $path = $consumer->{$this->corrections->documentColumn($field)};

            if (blank($path) || ! Storage::disk('local')->exists($path)) {
                continue;
            }

            $urls[$field] = $this->correctionUrl($request, 'connection.resubmit.document', $consumer, ['field' => $field]);
        }

        return $urls;
    }

    private function ensureCurrentRejection(Request $request, Consumer $consumer): void
    {
        abort_if($request->query('rejection') !== null && $request->integer('rejection') !== (int) $consumer->rejection_count, 403);
    }

    private function correctionUrl(Request $request, string $route, Consumer $consumer, array $parameters = []): string
    {
        $parameters += ['consumer' => $consumer->id];
        if ($request->query('rejection') !== null) {
            $parameters['rejection'] = $request->integer('rejection');
        }

        $expires = Carbon::createFromTimestamp($request->integer('expires'));
        if ($request->query('expires') === null || ($route === 'connection.resubmit.document' && $expires->gt(now()->addHour()))) {
            $expires = now()->addHour();
        }

        return URL::temporarySignedRoute($route, $expires, $parameters);
    }

    private function linkExpiresAt(Request $request): ?string
    {
        $expires = $request->query('expires');

        return is_numeric($expires)
            ? Carbon::createFromTimestamp((int) $expires)->format('F d, Y g:i A')
            : null;
    }
}
