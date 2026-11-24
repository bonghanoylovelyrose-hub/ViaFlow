<?php

namespace App\Http\Controllers;

use App\Models\Consumer;
use App\Models\MeterAssignment;
use App\Models\MeterReading;
use App\Models\User;
use App\Notifications\MeterReadingNotification;
use App\Services\BillingComputationService;
use App\Services\ConsumerBalanceService;
use App\Services\MeterAssignmentService;
use App\Services\MeterReadingBaselineService;
use App\Services\MeterReadingQueueService;
use App\Services\WaterBillingLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MeterReadingController extends Controller
{
    public function index(Request $request, MeterReadingQueueService $queueService): View
    {
        $user = $request->user();
        $hasBillingFixtures = app()->isLocal() && MeterReading::query()->onlyBillingFixtures()->exists();
        $billingFixture = $hasBillingFixtures && $request->query('ledger') === 'demo';
        $status = trim((string) $request->string('status'));
        $search = trim((string) $request->string('search'));
        $requestedMonth = trim((string) $request->string('month'));
        $requestedYear = (string) $request->string('year');
        if ($user->isAdmin() && preg_match('/^\d{4}$/', $requestedYear) && preg_match('/^(0?[1-9]|1[0-2])$/', $requestedMonth)) {
            $requestedMonth = sprintf('%s-%02d', $requestedYear, (int) $requestedMonth);
        }
        $period = $this->resolveReadingMonth($requestedMonth);
        $periodStart = $period->toDateString();
        $periodEnd = $period->copy()->endOfMonth()->toDateString();

        $readings = MeterReading::query()
            ->when($billingFixture, fn ($query) => $query->onlyBillingFixtures()->whereBetween('reading_date', [$periodStart, $periodEnd]))
            ->with(['consumer', 'reader'])
            ->when($user->isMeterReader(), fn ($query) => $query->where('reader_id', $user->id))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('consumer', function ($consumerQuery) use ($search) {
                    $consumerQuery
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhere('meter_number', 'like', "%{$search}%");
                });
            })
            // Completion order, not reading_date: a late encoded reading is the
            // newest completed work even when its reading_date is back-dated.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $assignedAccounts = Consumer::query()
            ->readingQueueEligible()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($consumerQuery) use ($search) {
                    $consumerQuery
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhere('meter_number', 'like', "%{$search}%");
                });
            })
            ->count();
        $completedReads = app(WaterBillingLedger::class)->withoutArchivedNovemberReadings(MeterReading::query())
            ->when($user->isMeterReader(), fn ($query) => $query->where('reader_id', $user->id))
            ->where('status', MeterReading::STATUS_ENCODED)
            ->whereBetween('reading_date', [$periodStart, $periodEnd])
            ->distinct()
            ->count('consumer_id');
        $pendingUploads = MeterReading::query()
            ->when($user->isMeterReader(), fn ($query) => $query->where('reader_id', $user->id))
            ->whereIn('status', [MeterReading::STATUS_PENDING, MeterReading::STATUS_NEEDS_REVISIT])
            ->whereBetween('reading_date', [$periodStart, $periodEnd])
            ->count();

        $completedReadings = $queueService->completedReadings($user, $search, $period, $billingFixture);
        $pendingConsumers = $billingFixture ? collect() : $queueService->pendingConsumers($search, $period);

        return view($user->isAdmin()
            ? $this->adminViewPath('meter-readings.index')
            : 'meter-reading.meter-readings.index', [
                'readings' => $readings,
            'hasBillingFixtures' => $hasBillingFixtures,
            'billingFixture' => $billingFixture,
                'readers' => User::query()->whereIn('role', ['staff', 'meter_reader'])->orderBy('name')->get(),
                'statuses' => [
                    MeterReading::STATUS_PENDING,
                    MeterReading::STATUS_ENCODED,
                    MeterReading::STATUS_NEEDS_REVISIT,
                ],
                'filters' => [
                    'status' => $status,
                    'search' => $search,
                    'month' => $period->format('Y-m'),
                    'month_label' => $period->format('F Y'),
                ],
                'stats' => [
                'assigned_accounts' => $billingFixture ? $completedReadings->count() : $assignedAccounts,
                'completed_reads' => $billingFixture ? $completedReadings->count() : $completedReads,
                'pending_uploads' => $billingFixture ? 0 : $pendingUploads,
                ],
                'completedReadings' => $completedReadings,
                'pendingConsumers' => $pendingConsumers,
                'readingYears' => $user->isAdmin()
                    ? MeterReading::query()->where('reading_date', '>=', now()->startOfYear()->addYear()->toDateString())
                        ->toBase()->distinct()->pluck('reading_date')->map(fn (string $date) => (int) substr($date, 0, 4))
                    : [],
                'canManageAssignments' => $user->isAdmin(),
            ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Validated against the meter currently assigned to the account,
            // not just any historical meter number on file.
            'meter_number' => ['required', 'string'],
            'reader_id' => ['nullable', 'exists:users,id'],
            'reading_date' => ['required', 'date'],
            'current_reading' => ['required', 'numeric', 'min:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $readerId = $request->user()->isMeterReader()
            ? $request->user()->id
            : ($validated['reader_id'] ?? null);

        if (! $readerId) {
            return back()
                ->withInput()
                ->withErrors([
                    'reader_id' => 'A staff member must be assigned to this reading.',
                ]);
        }

        $meters = app(MeterAssignmentService::class);
        $consumer = $meters->findConsumerByActiveMeter($validated['meter_number']);

        if (! $consumer) {
            return back()
                ->withInput()
                ->withErrors([
                    'meter_number' => $this->inactiveMeterMessage($validated['meter_number']),
                ]);
        }

        // Unverified accounts can never submit a reading, even via a direct POST.
        if (! $consumer->isVerified()) {
            return back()
                ->withInput()
                ->withErrors([
                    'meter_number' => 'Meter readings can only be submitted for verified consumer accounts. This account has not been verified yet.',
                ]);
        }

        $readingDate = Carbon::parse($validated['reading_date']);
        $readingPeriod = $readingDate->copy()->startOfMonth();
        if (WaterBillingLedger::isScenarioNovember($consumer, $readingPeriod->toDateString())
            && $readingDate->toDateString() > now('Asia/Manila')->toDateString()) {
            throw ValidationException::withMessages(['reading_date' => 'The meter reading date cannot be in the future.']);
        }

        $meterReading = DB::transaction(function () use ($consumer, $validated, $readerId, $readingDate, $readingPeriod): MeterReading {
            Consumer::query()->whereKey($consumer->id)->lockForUpdate()->firstOrFail();
            $existingReading = app(WaterBillingLedger::class)->withoutArchivedNovemberReadings(MeterReading::query())
                ->where('consumer_id', $consumer->id)
                ->whereYear('reading_date', $readingDate->year)
                ->whereMonth('reading_date', $readingDate->month)
                ->first();

            // Complete an existing pending or revisit capture without inserting a duplicate.
            if ($existingReading && ($existingReading->status === MeterReading::STATUS_ENCODED || $existingReading->bill()->exists())) {
                throw ValidationException::withMessages([
                    'duplicate_reading' => 'This account has been read already for the selected month.',
                ]);
            }

            $previousReading = app(MeterReadingBaselineService::class)->previousReadingFor($consumer, $readingPeriod);
            $currentReading = (float) $validated['current_reading'];

            if ($currentReading <= $previousReading) {
                throw ValidationException::withMessages([
                    'current_reading' => 'Current reading must be greater than the previous reading.',
                ]);
            }

            $meterReading = $existingReading ?? new MeterReading;
            $meterReading->fill([
                'consumer_id' => $consumer->id,
                // Snapshot the active meter so a later reassignment preserves the reading history.
                'meter_number' => $consumer->meter_number,
                'reader_id' => $readerId,
                'reading_date' => $validated['reading_date'],
                'previous_reading' => $previousReading,
                'current_reading' => $currentReading,
                'consumption' => round($currentReading - $previousReading, 2),
                'status' => MeterReading::STATUS_ENCODED,
                'submitted_at' => now(),
                'remarks' => $validated['remarks'] ?? null,
            ])->save();

            return $meterReading;
        });

        User::query()
            ->where('role', 'admin')
            ->get()
            ->each(fn (User $admin) => $admin->notify(new MeterReadingNotification(
                meterReading: $meterReading,
                title: 'New meter reading submitted',
                message: sprintf(
                    '%s submitted a reading of %s for meter %s.',
                    $request->user()->name,
                    number_format((float) $meterReading->current_reading, 2),
                    $consumer->meter_number,
                ),
            )));

        return redirect()
            ->route('meter-readings.index')
            ->with('success', 'Meter reading captured successfully.');
    }

    public function lookupByMeter(Request $request): JsonResponse
    {
        $request->validate([
            'meter_number' => ['required', 'string'],
            'reading_date' => ['nullable', 'date'],
        ]);

        $meterNumber = $request->input('meter_number');

        // Lookup always follows the meter currently assigned to the account.
        $consumer = app(MeterAssignmentService::class)->findConsumerByActiveMeter($meterNumber);

        if (! $consumer) {
            return response()->json([
                'found' => false,
                'verified' => false,
                'message' => $this->inactiveMeterMessage($meterNumber),
            ], 422);
        }

        if (! $consumer->isVerified()) {
            return response()->json([
                'found' => false,
                'verified' => false,
                'message' => 'This account has not been verified by the administrator. Meter readings are only available for verified accounts.',
            ], 422);
        }

        $readingDate = $request->filled('reading_date')
            ? Carbon::parse($request->input('reading_date'))
            : now();
        $readingPeriod = $readingDate->copy()->startOfMonth();
        $previousReading = app(MeterReadingBaselineService::class)->previousReadingFor($consumer, $readingPeriod);
        $ledger = app(WaterBillingLedger::class);
        $alreadyRead = $ledger->withoutArchivedNovemberReadings(MeterReading::query())
            ->where('consumer_id', $consumer->id)
            ->where('status', MeterReading::STATUS_ENCODED)
            ->whereYear('reading_date', $readingDate->year)
            ->whereMonth('reading_date', $readingDate->month)
            ->exists();

        $consumerData = [
            'id' => $consumer->id,
            'full_name' => $consumer->full_name,
            'account_number' => $consumer->account_number,
            'meter_number' => $consumer->meter_number,
            'previous_reading' => number_format($previousReading, 2, '.', ''),
        ];

        if ($request->user()->isAdmin()) {
            $unitRate = $consumer->hasVerifiedMembership()
                ? BillingComputationService::MEMBER_UNIT_RATE
                : BillingComputationService::NON_MEMBER_UNIT_RATE;
            $previousBills = WaterBillingLedger::isScenarioAccount($consumer)
                ? $ledger->outstandingBefore($consumer, $readingPeriod->toDateString())
                : app(ConsumerBalanceService::class)->outstandingBefore($consumer->id, $readingPeriod->toDateString());
            $consumerData += [
                'unit_rate' => number_format($unitRate, 2, '.', ''),
                'base_charge' => number_format(BillingComputationService::DEFAULT_BASE_CHARGE, 2, '.', ''),
                'outstanding_period' => $previousBills->last()?->billing_period?->format('F Y'),
                'previous_balance' => number_format($previousBills->sum('balance_due'), 2, '.', ''),
            ];
        }

        return response()->json([
            'found' => true,
            'verified' => true,
            'already_read' => $alreadyRead,
            'consumer' => $consumerData,
        ]);
    }

    /**
     * Resolves the selected billing month, falling back to the current month
     * when the filter is empty or malformed.
     */
    private function resolveReadingMonth(string $month): Carbon
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            return now()->startOfMonth();
        }

        return Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
    }

    /**
     * Explains why a meter number cannot be used, pointing the reader at the
     * replacement meter when the old one has been retired.
     */
    private function inactiveMeterMessage(string $meterNumber): string
    {
        $retired = MeterAssignment::query()
            ->whereRaw('LOWER(meter_number) = ?', [mb_strtolower(trim($meterNumber))])
            ->whereNotNull('retired_at')
            ->orderByDesc('retired_at')
            ->first();

        if ($retired?->consumer?->meter_number) {
            return sprintf(
                'Meter %s is no longer active. This account was issued meter %s. Please use the current meter number.',
                $meterNumber,
                $retired->consumer->meter_number,
            );
        }

        return 'Invalid Meter ID.';
    }
}
