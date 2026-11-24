<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use App\Services\AnnouncementAudienceService;
use App\Services\AnnouncementNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index(Request $request, AnnouncementAudienceService $audiencesService): View
    {
        $user = $request->user();
        $search = trim((string) $request->string('search'));
        $category = trim((string) $request->string('category'));
        $audience = trim((string) $request->string('audience'));
        $announcementsTableExists = Schema::hasTable('announcements');
        $showDeleted = $user->isAdmin() && $request->boolean('deleted');
        $categories = ['Service Advisory', 'Water Interruption', 'Maintenance', 'Billing Reminder', 'Community Announcement', 'General Notice', 'Community Update', 'Loan Program', 'Billing Notice', 'Operations'];
        $audiences = $audiencesService->audienceOptions() + ['Staff' => 'Staff'];

        $announcements = $announcementsTableExists
            ? $audiencesService->applyVisibility($showDeleted ? Announcement::onlyTrashed() : Announcement::query(), $user)
                ->with(['author', 'deletedBy'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($announcementQuery) use ($search) {
                        $announcementQuery
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('body', 'like', "%{$search}%");
                    });
                })
                ->when($category !== '', fn ($query) => $query->where('category', $category))
                ->when($user->isAdmin() && $audience !== '', fn ($query) => $query->where('audience', $audience))
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(9)
                ->withQueryString()
            : $this->emptyPaginator($request);

        $collection = collect($announcements->items());

        return view($this->roleViewPath($request, 'announcements.index'), [
            'announcements' => $announcements,
            'showDeleted' => $showDeleted,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'audience' => $audience,
            ],
            'categories' => $categories,
            'audiences' => $audiences,
            'recipientUsers' => $user->isAdmin() ? User::query()->whereNotNull('email')->orderBy('name')->get(['id', 'name', 'email']) : collect(),
            'stats' => [
                'total' => $announcements->total(),
                'pinned' => $collection->where('is_pinned', true)->count(),
                'service_advisories' => $collection->where('category', 'Service Advisory')->count(),
            ],
            'canManageAnnouncements' => $user->isAdmin(),
            'announcementsTableExists' => $announcementsTableExists,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        if (! Schema::hasTable('announcements')) {
            return redirect()
                ->route('announcements.index')
                ->withErrors([
                    'announcements' => 'The announcements module is not ready yet. Run php artisan migrate first.',
                ]);
        }

        $validated = $this->validateAnnouncement($request);
        $recipientIds = $validated['recipient_user_ids'] ?? [];
        $photo = $validated['photo'] ?? null;
        unset($validated['recipient_user_ids'], $validated['photo'], $validated['remove_photo']);
        $validated['recipient_user_ids'] = $validated['audience'] === 'Specific Users' ? array_map('intval', $recipientIds) : null;
        $validated['photo_path'] = $photo?->store('announcements', 'local');

        $announcement = DB::transaction(function () use ($validated, $recipientIds, $request) {
            $announcement = Announcement::create([
                ...$validated,
                'user_id' => $request->user()->id,
                'is_pinned' => $request->boolean('is_pinned'),
                'published_at' => $validated['published_at'] ?? now(),
            ]);
            $announcement->recipientUsers()->sync($announcement->recipient_user_ids ?? []);

            return $announcement;
        });

        app(AnnouncementNotificationService::class)->queueIfDue($announcement);

        return redirect()
            ->route('announcements.index')
            ->with('success', $announcement->published_at->isFuture() ? 'Announcement scheduled successfully.' : 'Announcement posted successfully.');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $validated = $this->validateAnnouncement($request);
        $recipientIds = $validated['recipient_user_ids'] ?? [];
        $photo = $validated['photo'] ?? null;
        unset($validated['recipient_user_ids'], $validated['photo'], $validated['remove_photo']);
        $previousAudience = $announcement->audience;
        $previousRecipientIds = array_map('intval', $announcement->recipient_user_ids ?? []);
        $oldPhoto = $announcement->photo_path;
        $removePhoto = $request->boolean('remove_photo');
        if ($photo) {
            $validated['photo_path'] = $photo->store('announcements', 'local');
        } elseif ($removePhoto) {
            $validated['photo_path'] = null;
        }
        $validated['recipient_user_ids'] = $validated['audience'] === 'Specific Users' ? array_map('intval', $recipientIds) : null;

        DB::transaction(function () use ($announcement, $validated, $recipientIds, $request) {
            $announcement->update([
                ...$validated,
                'is_pinned' => $request->boolean('is_pinned'),
                'edited_at' => now(),
            ]);
            $announcement->recipientUsers()->sync($announcement->recipient_user_ids ?? []);
        });

        if (($photo || $removePhoto) && $oldPhoto) {
            Storage::disk('local')->delete($oldPhoto);
        }
        $current = $announcement->fresh();
        $currentRecipientIds = array_map('intval', $current->recipient_user_ids ?? []);
        if (! $current->notifications_queued_at) {
            app(AnnouncementNotificationService::class)->queueIfDue($current);
        } elseif ($previousAudience !== $current->audience || $previousRecipientIds !== $currentRecipientIds) {
            app(AnnouncementNotificationService::class)->queueForPublishedAnnouncement($current);
        }

        return redirect()
            ->route('announcements.index')
            ->with('success', 'Announcement updated successfully.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        DB::transaction(function () use ($announcement, $request) {
            $announcement->update(['deleted_by' => $request->user()->id]);
            $announcement->delete();
            $announcement->deliveries()->whereIn('status', ['Queued', 'Retrying'])->update([
                'status' => 'Cancelled',
                'last_error' => 'Announcement was deleted.',
            ]);
        });

        return redirect()
            ->route('announcements.index')
            ->with('success', 'Announcement deleted successfully.');
    }

    public function show(Request $request, Announcement $announcement, AnnouncementAudienceService $audiencesService): View
    {
        abort_unless($audiencesService->visibleTo($announcement, $request->user()), 403);

        return view('shared.announcements.show', [
            'announcement' => $announcement->load('author'),
            'activePage' => 'announcements',
        ]);
    }

    public function photo(Request $request, Announcement $announcement, AnnouncementAudienceService $audiencesService)
    {
        abort_unless($audiencesService->visibleTo($announcement, $request->user()), 403);
        abort_unless($announcement->photo_path && Storage::disk('local')->exists($announcement->photo_path), 404);

        return Storage::disk('local')->response($announcement->photo_path);
    }

    private function validateAnnouncement(Request $request): array
    {
        $audiences = array_keys(app(AnnouncementAudienceService::class)->audienceOptions());
        $audiences[] = 'Staff';

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'category' => ['required', Rule::in(['Service Advisory', 'Water Interruption', 'Maintenance', 'Billing Reminder', 'Community Announcement', 'General Notice', 'Community Update', 'Loan Program', 'Billing Notice', 'Operations'])],
            'audience' => ['required', 'in:'.implode(',', $audiences)],
            'recipient_user_ids' => ['nullable', 'array'],
            'recipient_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'remove_photo' => ['nullable', 'boolean'],
            'priority' => ['sometimes', 'required', Rule::in(['Normal', 'Important', 'Urgent'])],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
            'affected_area' => ['nullable', 'string', 'max:255'],
            'service_starts_at' => ['nullable', 'required_with:service_restores_at', 'date'],
            'service_restores_at' => ['nullable', 'date', 'after_or_equal:service_starts_at'],
        ]);

        if ($validated['audience'] === 'Specific Users' && empty($validated['recipient_user_ids'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['recipient_user_ids' => 'Select at least one announcement recipient.']);
        }
        if ($validated['audience'] === 'Specific Users') {
            $ids = array_map('intval', $validated['recipient_user_ids']);
            $eligibleCount = User::query()->whereIn('id', $ids)->whereNotNull('email')->where('email', '<>', '')->count();
            if ($eligibleCount !== count($ids)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['recipient_user_ids' => 'Every selected user must have a registered email address.']);
            }
        }

        foreach (['published_at', 'expires_at', 'service_starts_at', 'service_restores_at'] as $field) {
            if (! empty($validated[$field])) {
                $validated[$field] = Carbon::parse($validated[$field], 'Asia/Manila')->utc();
            }
        }

        if (! in_array($validated['category'], ['Service Advisory', 'Water Interruption', 'Maintenance'], true)) {
            $validated['affected_area'] = null;
            $validated['service_starts_at'] = null;
            $validated['service_restores_at'] = null;
        }

        return $validated;
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: collect(),
            total: 0,
            perPage: 9,
            currentPage: LengthAwarePaginator::resolveCurrentPage(),
            options: [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }
}
