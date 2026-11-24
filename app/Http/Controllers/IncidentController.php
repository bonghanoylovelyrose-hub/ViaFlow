<?php

namespace App\Http\Controllers;

use App\Models\Consumer;
use App\Models\Incident;
use App\Models\User;
use App\Notifications\IncidentStatusNotification;
use App\Rules\FileMimeType;
use App\Services\IncidentClassificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IncidentController extends Controller
{
    private const STATUSES = ['Pending', 'Resolved'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $status = trim((string) $request->string('status'));
        $category = trim((string) $request->string('category'));
        $search = trim((string) $request->string('search'));
        $priority = $request->query('priority');
        $priority = match ($priority) {
            'Critical' => Incident::PRIORITY_URGENT,
            'Normal' => Incident::PRIORITY_NON_URGENT,
            default => $priority,
        };
        $priority = $user->isAdmin() && in_array($priority, Incident::PRIORITIES, true) ? $priority : '';
        $incidentsTableExists = Schema::hasTable('incidents');

        $incidents = $incidentsTableExists
            ? Incident::query()
                ->with('consumer')
                ->when($user->isAdmin(), fn ($query) => $query->with('priorityReviewer')
                    ->orderByRaw('CASE priority WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 1 END', Incident::PRIORITY_ORDER))
                ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
                ->when($status === 'Pending', fn ($query) => $query->where('status', '!=', Incident::STATUS_RESOLVED))
                ->when($status === 'Resolved', fn ($query) => $query->where('status', Incident::STATUS_RESOLVED))
                ->when($category !== '', fn ($query) => $query->whereRaw('LOWER(TRIM(category)) = ?', [strtolower($category)]))
                ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($incidentQuery) use ($search) {
                        $incidentQuery
                            ->where('reported_by', 'like', "%{$search}%")
                            ->orWhere('location', 'like', "%{$search}%")
                            ->orWhere('purok', 'like', "%{$search}%")
                            ->orWhere('block', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhereHas('consumer', function ($consumerQuery) use ($search) {
                                $consumerQuery
                                    ->where('full_name', 'like', "%{$search}%")
                                    ->orWhere('account_number', 'like', "%{$search}%");
                            });
                    });
                })
                ->when(
                    $status === 'Resolved',
                    // Newly resolved reports surface first, newest resolution at the top.
                    fn ($query) => $query
                        ->orderByRaw('coalesce(resolved_at, confirmed_at) is null asc, coalesce(resolved_at, confirmed_at) desc'),
                )
                ->when(
                    $status !== 'Resolved',
                    // First-In, First-Out: the oldest unresolved report comes first.
                    fn ($query) => $query
                        ->orderByRaw('coalesce(resolved_at, confirmed_at) is null desc, reported_at asc, id asc'),
                )
                ->paginate(10)
                ->withQueryString()
            : $this->emptyPaginator($request);

        return view($this->roleViewPath($request, 'incidents.index'), [
            'incidents' => $incidents,
            'categories' => Incident::CATEGORIES,
            'priorities' => Incident::PRIORITIES,
            'statuses' => self::STATUSES,
            'puroks' => config('service_area.puroks', []),
            'filters' => [
                'status' => $status,
                'category' => $category,
                'search' => $search,
                'priority' => $priority,
            ],
            'stats' => $incidentsTableExists
                ? [
                    'total' => Incident::query()
                        ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
                        ->count(),
                    'pending' => Incident::query()
                        ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
                        ->where('status', '!=', 'Resolved')
                        ->count(),
                    'ongoing' => Incident::query()
                        ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
                        ->where('status', 'Ongoing')
                        ->count(),
                    'for_confirmation' => Incident::query()
                        ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
                        ->where('status', 'For Consumer Confirmation')
                        ->count(),
                    'resolved' => Incident::query()
                        ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
                        ->where('status', 'Resolved')
                        ->count(),
                ]
                : [
                    'total' => 0,
                    'pending' => 0,
                    'ongoing' => 0,
                    'for_confirmation' => 0,
                    'resolved' => 0,
                ],
            'consumers' => collect(),
            'selectedIncidentConsumer' => $user->isAdmin() && old('consumer_id') && old('meter_number')
                ? Consumer::query()->whereKey(old('consumer_id'))->where('meter_number', old('meter_number'))
                    ->first(['id', 'full_name', 'account_number', 'meter_number', 'service_address', 'service_purok', 'address_block'])
                : null,
            'canManageIncidents' => $user->isAdmin(),
            'incidentsTableExists' => $incidentsTableExists,
        ]);
    }

    public function searchMeters(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $validated = $request->validate(['q' => ['required', 'string', 'max:50']]);
        $term = preg_replace('/[%_\\\\]/u', '', trim($validated['q']));
        if ($term === '') {
            return response()->json([]);
        }

        $consumers = Consumer::query()->whereNotNull('meter_number')->where('meter_number', '<>', '')
            ->where('meter_number', 'like', '%'.$term.'%')
            ->orderBy('meter_number')->limit(10)
            ->get(['id', 'full_name', 'account_number', 'meter_number', 'service_address', 'service_purok', 'address_block']);

        return response()->json($consumers)->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, IncidentClassificationService $classifier): RedirectResponse
    {
        if (! Schema::hasTable('incidents')) {
            return redirect()
                ->route('incidents.index')
                ->withErrors([
                    'incidents' => 'The incidents module is not ready yet. Run php artisan migrate first.',
                ]);
        }

        $user = $request->user();
        if ($user->isConsumer()) {
            $request->merge(['location' => config('service_area.barangay_subdivision')]);
        }

        $submittedMeter = $request->input('meter_number');
        $submittedMeter = is_string($submittedMeter) ? $submittedMeter : '';

        $validated = $request->validate([
            'consumer_id' => $user->isAdmin()
                ? ['required', Rule::exists('consumers', 'id')->where('meter_number', $submittedMeter)]
                : ['sometimes', 'nullable', 'exists:consumers,id'],
            'meter_number' => [$user->isAdmin() ? 'required' : 'sometimes', 'string', 'max:50'],
            'reported_by' => [$user->isAdmin() ? 'required' : 'sometimes', 'string', 'max:255'],
            'category' => ['required', Rule::in(Incident::CATEGORIES)],
            'custom_incident_type' => ['required_if:category,Others', 'nullable', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'purok' => [
                $user->isAdmin() ? 'required' : 'nullable',
                'string',
                'max:100',
                Rule::in(config('service_area.puroks', [])),
            ],
            'block' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:1000'],
            'status' => ['sometimes', 'prohibited'],
            'resolution_notes' => ['sometimes', 'prohibited'],
            'photo' => [
                'nullable',
                'file',
                'extensions:jpg,jpeg,png,mp4,mov,avi,webm',
                'max:51200',
                new FileMimeType('jpg,jpeg,png,mp4,mov,avi,webm'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $this->validateIncidentEvidence($value, $fail);
                },
            ],
        ], [
            'consumer_id.required' => 'Select a consumer from the meter number search results.',
            'consumer_id.exists' => 'Select a valid current meter number from the search results.',
            'meter_number.required' => 'Select a meter number from the search results.',
            'photo.uploaded' => 'Please upload a supported photo or video file.',
            'photo.file' => 'Please upload a supported photo or video file.',
            'photo.mimes' => 'Please upload a supported photo or video file.',
            'photo.extensions' => 'Please upload a supported photo or video file.',
            'photo.max' => 'Videos must not exceed 50 MB.',
        ]);

        $consumerId = $user->isConsumer() ? $user->consumer?->id : ($validated['consumer_id'] ?? null);

        if ($user->isConsumer() && ! $consumerId) {
            abort(403);
        }

        $purok = $validated['purok'] ?? ($user->isConsumer() ? $user->consumer?->service_purok : null);
        $block = $validated['block'] ?? ($user->isConsumer() ? $user->consumer?->address_block : null);

        if (blank($purok)) {
            throw ValidationException::withMessages([
                'purok' => 'Please select the purok where the incident occurred.',
            ]);
        }

        if (! in_array($purok, config('service_area.puroks', []), true)) {
            throw ValidationException::withMessages([
                'purok' => 'Please select a valid Via Vida purok.',
            ]);
        }

        $block = $this->normalizeBlock($block);

        $status = 'Pending';
        $photoPath = null;
        $mediaType = null;

        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');

            if (! $photo->isValid()) {
                throw ValidationException::withMessages([
                    'photo' => 'Please upload a supported photo or video file.',
                ]);
            }

            $mediaType = $this->incidentEvidenceMediaType($photo);

            Storage::disk('public')->makeDirectory('incident-evidence');

            $photoPath = $photo->storeAs(
                'incident-evidence',
                Str::random(40).'.'.$photo->getClientOriginalExtension(),
                'public'
            );

            if (! $photoPath || ! Storage::disk('public')->exists($photoPath)) {
                throw ValidationException::withMessages([
                    'photo' => 'The incident photo could not be saved. Please try again.',
                ]);
            }
        }

        $specifiedType = $validated['category'] === 'Others' ? $validated['custom_incident_type'] : null;
        $incident = new Incident([
            'consumer_id' => $consumerId,
            'reported_by' => $user->isConsumer() ? $user->name : $validated['reported_by'],
            'category' => $validated['category'],
            'custom_incident_type' => $specifiedType,
            'location' => $validated['location'],
            'purok' => $purok,
            'block' => $block,
            'description' => $validated['description'],
            'status' => $status,
            'resolution_notes' => null,
            'photo_path' => $photoPath,
            'media_type' => $mediaType,
        ] + $classifier->classify($validated['category'], $validated['description'], $specifiedType));
        $submittedAt = now();
        $incident->created_at = $submittedAt;
        $incident->reported_at = $submittedAt;
        $incident->save();

        if (Schema::hasTable('notifications')) {
            if ($user->isConsumer()) {
                $this->notifyAdminsOfNewIncident($incident->load('consumer'));
            } elseif ($incident->consumer?->user) {
                $this->notifyConsumerOfIncidentUpdate(
                    $incident->load('consumer.user'),
                    'Incident logged',
                    sprintf(
                        'A %s report was logged for your account and is currently marked as %s.',
                        $incident->category,
                        $incident->status,
                    ),
                );
            }
        }

        return redirect()
            ->route('incidents.index')
            ->with('success', 'Incident report submitted successfully.');
    }

    public function update(Request $request, Incident $incident): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless(Schema::hasTable('incidents'), 404);

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:Pending,Ongoing,For Consumer Confirmation,Resolved'],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('incidents.index', array_filter(['status' => $request->string('status')]))
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $isResolved = $validated['status'] === Incident::STATUS_RESOLVED;

        if ($isResolved && blank($validated['resolution_notes'] ?? null)) {
            return redirect()
                ->route('incidents.index', array_filter(['status' => $request->string('status')]))
                ->withErrors(['resolution_notes' => 'Resolution notes are required when resolving an incident report.'])
                ->withInput();
        }

        $wasResolved = $incident->isResolved();

        DB::transaction(function () use ($incident, $validated, $isResolved, $wasResolved): void {
            $incident->forceFill([
                'status' => $validated['status'],
                'resolution_notes' => $validated['resolution_notes'] ?? $incident->resolution_notes,
                // Stamp the resolution time the first time it is resolved so the
                // Resolved list can be ordered by when it was actually resolved.
                'resolved_at' => $isResolved
                    ? ($incident->resolved_at ?? now())
                    : null,
            ])->save();
        });

        if ($isResolved && ! $wasResolved && Schema::hasTable('notifications')) {
            $this->notifyConsumerOfIncidentUpdate(
                $incident->load('consumer.user'),
                'Incident report resolved',
                sprintf(
                    'Your %s report at %s has been marked as resolved by the administrator.%s',
                    $incident->category,
                    $incident->location,
                    $validated['resolution_notes'] ? ' Resolution notes: '.$validated['resolution_notes'] : '',
                ),
            );
        }

        return redirect()
            ->route('incidents.index', array_filter(['status' => $request->string('status')]))
            ->with('success', 'Incident report updated successfully.');
    }

    public function reviewPriority(Request $request, Incident $incident): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $validated = $request->validate([
            'priority' => ['required', Rule::in(Incident::PRIORITIES)],
            'priority_review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $incident, $validated): void {
            $locked = Incident::query()->lockForUpdate()->findOrFail($incident->id);
            abort_if($locked->isResolved(), 409, Incident::PRIORITY_REVIEW_LOCKED_MESSAGE);

            $note = $validated['priority_review_note'] ?? null;
            if (($validated['priority'] !== $locked->priority || $validated['priority'] !== $locked->automatic_priority) && blank($note)) {
                throw ValidationException::withMessages(['priority_review_note' => 'Enter a reason for changing the priority or overriding the automatic classification.']);
            }

            Incident::query()->whereKey($locked->id)->update([
                'priority' => $validated['priority'],
                'priority_review_note' => $note,
                'priority_reviewed_by' => $request->user()->id,
                'priority_reviewed_at' => now(),
                'reported_at' => DB::raw('reported_at'),
            ]);
        });

        return back()->with('success', 'Incident priority reviewed.');
    }

    public function confirm(Request $request, Incident $incident): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isConsumer(), 403);

        if ($incident->consumer?->user_id !== $user->id || ! $incident->consumer?->user_id) {
            abort(403);
        }

        if ($incident->status !== 'Pending') {
            return $request->expectsJson()
                ? response()->json(['message' => 'Only pending incident reports can be marked as resolved.'], 422)
                : redirect()
                    ->route('incidents.index')
                    ->withErrors(['incident' => 'Only pending incident reports can be marked as resolved.']);
        }

        $incident->update([
            'status' => Incident::STATUS_RESOLVED,
            'confirmed_at' => now(),
            'resolved_at' => $incident->resolved_at ?? now(),
        ]);

        if (Schema::hasTable('notifications')) {
            User::query()
                ->where('role', 'admin')
                ->get()
                ->each(fn (User $admin) => $admin->notify(new IncidentStatusNotification(
                    incident: $incident,
                    title: 'Incident confirmed',
                    message: sprintf(
                        '%s has confirmed that the %s report at %s has been resolved.',
                        $incident->reported_by,
                        $incident->category,
                        $incident->location,
                    ),
                )));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Incident report marked as resolved.',
                'status' => $incident->status,
                'confirmed_at' => $incident->confirmed_at?->format('M d, Y h:i A'),
            ]);
        }

        return redirect()
            ->route('incidents.index')
            ->with('success', 'Incident report marked as resolved.');
    }

    public function dispute(Request $request, Incident $incident): RedirectResponse
    {
        abort(403);
    }

    public function photo(Request $request, Incident $incident): BinaryFileResponse
    {
        abort_unless(filled($incident->photo_path), 404);

        if ($request->user()->isConsumer() && $incident->consumer?->user_id !== $request->user()->id) {
            abort(403);
        }

        abort_unless(Storage::disk('public')->exists($incident->photo_path), 404);

        $path = Storage::disk('public')->path($incident->photo_path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'webm' => 'video/webm',
            default => 'application/octet-stream',
        };

        return response()->file($path, ['Content-Type' => $contentType]);
    }

    private function normalizeBlock(?string $block): ?string
    {
        $block = trim((string) $block);

        if ($block === '') {
            return null;
        }

        if (preg_match('/\b(?:block|blk)\.?\s*([A-Za-z0-9-]+)/i', $block, $matches) === 1) {
            return $matches[1];
        }

        return $block;
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: collect(),
            total: 0,
            perPage: 10,
            currentPage: LengthAwarePaginator::resolveCurrentPage(),
            options: [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    protected function roleViewPath(Request $request, string $view): string
    {
        return $request->user()->isAdmin()
            ? 'admin.' . $view
            : 'consumer.' . $view;
    }

    private function notifyAdminsOfNewIncident(Incident $incident): void
    {
        User::query()
            ->where('role', 'admin')
            ->get()
            ->each(fn (User $admin) => $admin->notify(new IncidentStatusNotification(
                incident: $incident,
                title: 'New incident reported',
                message: sprintf(
                    '%s submitted a %s report for %s.',
                    $incident->reported_by,
                    $incident->category,
                    $incident->location,
                ),
            )));
    }

    private function notifyConsumerOfIncidentUpdate(Incident $incident, string $title, string $message): void
    {
        $incident->consumer?->user?->notify(new IncidentStatusNotification(
            incident: $incident,
            title: $title,
            message: $message,
        ));
    }

    private function validateIncidentEvidence(mixed $file, \Closure $fail): void
    {
        if (! $file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile || ! $file->isValid()) {
            return;
        }

        $mediaType = $this->incidentEvidenceMediaType($file);

        if (! $mediaType) {
            $fail('Please upload a supported photo or video file.');
            return;
        }

        $sizeInKilobytes = $file->getSize() / 1024;

        if ($mediaType === 'image' && $sizeInKilobytes > 4096) {
            $fail('Images must not exceed 4 MB.');
        }

        if ($mediaType === 'video' && $sizeInKilobytes > 51200) {
            $fail('Videos must not exceed 50 MB.');
        }
    }

    private function incidentEvidenceMediaType(\Symfony\Component\HttpFoundation\File\UploadedFile $file): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match (true) {
            in_array($extension, ['jpg', 'jpeg', 'png'], true) => 'image',
            in_array($extension, ['mp4', 'mov', 'avi', 'webm'], true) => 'video',
            default => null,
        };
    }
}
