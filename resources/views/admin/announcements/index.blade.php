@extends('layouts.app')

@php
    $activePage = 'announcements';
@endphp

@section('title', 'Announcements')

@push('styles')
<style>
    .announcement-card {
        min-width: 0;
        overflow: hidden;
    }

    .announcement-card > * {
        min-width: 0;
        max-width: 100%;
    }

    .announcement-safe-wrap,
    .announcement-safe-wrap * {
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: break-word;
        max-width: 100%;
    }

    .announcement-card .announcement-header,
    .announcement-card .announcement-meta {
        min-width: 0;
    }

    .announcement-card .announcement-date,
    .announcement-card .announcement-footer {
        min-width: 0;
        max-width: 100%;
    }

    .announcement-delete-button { background: #fff0f0; color: #a42222; border: 1px solid #f1c4c4; transition: background-color .2s, color .2s; }
    .announcement-delete-button:hover, .announcement-delete-button:focus-visible { background: #f7d5d5; color: #861919; border-color: #e6a7a7; }
    #announcementCreateModal .modal-body { padding: 1rem 1.35rem; }
    #announcementCreateModal .announcement-section { font-size: .95rem; font-weight: 800; color: var(--vf-navy); margin: .35rem 0 0; }
    #announcementCreateModal .announcement-preview { background: #f4fafb; border: 1px solid var(--vf-light); border-radius: .8rem; padding: .85rem; overflow-wrap: anywhere; }

</style>
@endpush

@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <div class="page-heading">
                <div>
                    <h1>Announcements</h1>
                    <p>Publish service advisories, billing reminders, and internal updates from one shared board.</p>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success d-flex align-items-start gap-2 panel-card border-0 mb-4" role="alert">
                    <i class="bi bi-check-circle mt-1"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger d-flex align-items-start gap-2 panel-card border-0 mb-4" role="alert">
                    <i class="bi bi-exclamation-circle mt-1"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            @unless ($announcementsTableExists)
                <div class="panel-card p-4 mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <span class="feature-icon"><i class="bi bi-database-exclamation"></i></span>
                        <div>
                            <h2 class="h5 fw-bold mb-2">Announcements table not active yet</h2>
                            <p class="text-secondary mb-0">Run <code>php artisan migrate</code> to activate the announcements module and start posting updates.</p>
                        </div>
                    </div>
                </div>
            @endunless

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="dashboard-card p-4 h-100">
                        <div class="text-secondary small">{{ $showDeleted ? 'Deleted Announcements' : 'Visible Announcements' }}</div>
                        <div class="fw-bold fs-2 mt-2">{{ number_format($stats['total']) }}</div>
                        <span class="badge badge-soft-primary mt-2">Current feed</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card p-4 h-100">
                        <div class="text-secondary small">Pinned Posts</div>
                        <div class="fw-bold fs-2 mt-2">{{ number_format($stats['pinned']) }}</div>
                        <span class="badge badge-soft-warning mt-2">Priority updates</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card p-4 h-100">
                        <div class="text-secondary small">Service Advisories</div>
                        <div class="fw-bold fs-2 mt-2">{{ number_format($stats['service_advisories']) }}</div>
                        <span class="badge badge-soft-info mt-2">Operations-heavy notices</span>
                    </div>
                </div>
            </div>

            @if ($canManageAnnouncements)
                <div class="d-flex justify-content-end flex-wrap gap-2 mb-4">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#announcementCreateModal">
                        <i class="bi bi-megaphone me-2"></i>Post Announcement
                    </button>
                    <a class="btn btn-light border" href="{{ $showDeleted ? route('announcements.index') : route('announcements.index', ['deleted' => 1]) }}">{{ $showDeleted ? 'Back to Announcements' : 'Deletion History' }}</a>
                </div>
            @endif
            <div class="panel-card p-3 p-md-4 mb-4" data-admin-natural-page-scroll>
                <div class="row g-3 align-items-stretch">
                    @forelse ($announcements as $announcement)
                        @php
                            $badgeClass = match ($announcement->category) {
                                'Service Advisory' => 'badge-soft-danger',
                                'Billing Notice' => 'badge-soft-warning',
                                'Loan Program' => 'badge-soft-primary',
                                'Operations' => 'badge-soft-info',
                                default => 'badge-soft-success',
                            };
                        @endphp
                        <div class="col-md-6 col-xl-4">
                            <div class="announcement-card p-4 h-100 d-flex flex-column">
                                <div class="announcement-header d-flex justify-content-between align-items-start gap-3 mb-3">
                                    <div class="d-flex flex-wrap gap-2 announcement-meta">
                                        <span class="badge {{ $badgeClass }}">{{ $announcement->category }}</span>
                                        <span class="badge badge-soft-primary">{{ $announcement->audience }}</span>
                                        <span class="badge badge-soft-primary">{{ $announcement->priority ?? 'Normal' }}</span>
                                        @if ($showDeleted)<span class="badge badge-soft-danger">Deleted</span>@elseif ($announcement->published_at?->isFuture())<span class="badge badge-soft-warning">Scheduled</span>@elseif ($announcement->expires_at?->lte(now()))<span class="badge badge-soft-neutral">Expired</span>@else<span class="badge badge-soft-success">Published</span>@endif
                                        @if ($announcement->edited_at)<span class="badge badge-soft-neutral">Edited</span>@endif
                                        @if ($announcement->is_pinned)
                                            <span class="badge badge-soft-warning">Pinned</span>
                                        @endif
                                    </div>
                                    <span class="small text-secondary text-end announcement-date announcement-safe-wrap">{{ $announcement->published_at?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</span>
                                </div>
                                <h2 class="h5 fw-bold announcement-safe-wrap">{{ $announcement->title }}</h2>
                                <p class="text-secondary mb-3 flex-grow-1 announcement-safe-wrap">{{ $announcement->body }}</p>
                                @unless ($showDeleted)
                                    @include('shared.announcements._image', ['announcement' => $announcement])
                                    <a class="btn btn-sm btn-outline-primary align-self-start mb-3" href="{{ route('announcements.show', $announcement) }}">View Details</a>
                                @else
                                    <p class="small text-secondary">Deleted {{ $announcement->deleted_at?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT by {{ $announcement->deletedBy?->name ?? 'Unknown administrator' }}</p>
                                @endunless
                                <div class="announcement-footer d-flex justify-content-between align-items-center gap-3 mt-auto">
                                    <div class="small text-secondary announcement-safe-wrap">
                                        Posted by {{ $announcement->author?->name ?? 'System Admin' }}
                                    </div>
                                    @if ($canManageAnnouncements && !$showDeleted)
                                        <div class="d-inline-flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-light border"
                                                data-bs-toggle="modal"
                                                data-bs-target="#announcementEditModal"
                                                data-announcement-id="{{ $announcement->id }}"
                                                data-announcement-update-url="{{ route('announcements.update', $announcement) }}"
                                                data-announcement-title="{{ $announcement->title }}"
                                                data-announcement-body="{{ $announcement->body }}"
                                                data-announcement-category="{{ $announcement->category }}"
                                                data-announcement-audience="{{ $announcement->audience }}"
                                                data-announcement-recipients="@json($announcement->recipient_user_ids ?? [])"
                                                data-announcement-photo="{{ $announcement->photo_path ? route('announcements.photo', $announcement) : '' }}"
                                                data-announcement-pinned="{{ $announcement->is_pinned ? '1' : '0' }}"
                                                data-announcement-priority="{{ $announcement->priority }}"
                                                data-announcement-published="{{ $announcement->published_at?->copy()->timezone('Asia/Manila')->format('Y-m-d\TH:i') }}"
                                                data-announcement-expires="{{ $announcement->expires_at?->copy()->timezone('Asia/Manila')->format('Y-m-d\TH:i') }}"
                                                data-announcement-area="{{ $announcement->affected_area }}"
                                                data-announcement-start="{{ $announcement->service_starts_at?->copy()->timezone('Asia/Manila')->format('Y-m-d\TH:i') }}"
                                                data-announcement-restore="{{ $announcement->service_restores_at?->copy()->timezone('Asia/Manila')->format('Y-m-d\TH:i') }}"
                                                data-announcement-photo-is-pdf="{{ strtolower(pathinfo($announcement->photo_path ?? '', PATHINFO_EXTENSION)) === 'pdf' ? '1' : '0' }}"
                                            >
                                                Edit
                                            </button>
                                            <button type="button" class="btn btn-sm announcement-delete-button" data-bs-toggle="modal" data-bs-target="#announcementDeleteModal" data-delete-url="{{ route('announcements.destroy', $announcement) }}" data-title="{{ $announcement->title }}"><i class="bi bi-trash me-1" aria-hidden="true"></i>Delete</button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="text-center py-5 text-secondary">
                                No announcements found for the selected filters.
                            </div>
                        </div>
                    @endforelse
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-4">
                    <p class="text-secondary mb-0">
                        @if ($announcements->total() > 0)
                            Showing {{ $announcements->firstItem() }} to {{ $announcements->lastItem() }} of {{ $announcements->total() }} announcements
                        @else
                            No announcements available
                        @endif
                    </p>
                    {{ $announcements->links() }}
                </div>
            </div>




        </div>
    </main>
</div>

@if ($canManageAnnouncements)
    <div class="modal fade" id="announcementCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Post Announcement</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('announcements.store') }}" enctype="multipart/form-data" id="announcementCreateForm">
                        @csrf
                        <input type="hidden" name="announcement_form" value="create">
                        <div class="row g-3">
                            <div class="col-12"><h3 class="announcement-section">Announcement Information</h3></div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="announcement_title">Announcement Title</label>
                                <input type="text" class="form-control" id="announcement_title" name="title" value="{{ old('title') }}" maxlength="255" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="announcement_category">Category</label>
                                <select class="form-select" id="announcement_category" name="category" required>
                                    @foreach (['Service Advisory', 'Water Interruption', 'Maintenance', 'Billing Reminder', 'Community Announcement', 'General Notice'] as $category)
                                        <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="announcement_priority">Priority</label>
                                <select class="form-select" id="announcement_priority" name="priority">
                                    @foreach (['Normal', 'Important', 'Urgent'] as $priority)
                                        <option value="{{ $priority }}" @selected(old('priority', 'Normal') === $priority)>{{ $priority }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="announcement_audience">Audience</label>
                                <select class="form-select" id="announcement_audience" name="audience" required>
                                    @foreach ($audiences as $audienceValue => $audienceLabel)
                                        <option value="{{ $audienceValue }}" @selected(old('audience', 'Everyone') === $audienceValue)>{{ $audienceLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 d-none" id="announcement_recipient_users_wrap">
                                <label class="form-label fw-semibold" for="announcement_recipient_users">Specific users</label>
                                <select class="form-select" id="announcement_recipient_users" name="recipient_user_ids[]" multiple size="5">
                                    @foreach ($recipientUsers as $recipientUser)
                                        <option value="{{ $recipientUser->id }}" @selected(in_array((string) $recipientUser->id, array_map('strval', (array) old('recipient_user_ids', [])), true))>{{ $recipientUser->name }} — {{ $recipientUser->email }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Hold Ctrl or Command to select multiple accounts.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="announcement_body">Details</label>
                                <textarea class="form-control" id="announcement_body" name="body" rows="3" maxlength="2000" required>{{ old('body') }}</textarea>
                            </div>
                            <div class="col-12"><h3 class="announcement-section">Announcement Schedule</h3><div class="small text-secondary">Times are in Philippine time (PHT).</div></div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="announcement_published_at">Publish Date and Time</label>
                                <input type="datetime-local" class="form-control" id="announcement_published_at" name="published_at" value="{{ old('published_at', now()->timezone('Asia/Manila')->format('Y-m-d\TH:i')) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="announcement_expires_at">Expiration Date and Time <span class="text-secondary fw-normal">(optional)</span></label>
                                <input type="datetime-local" class="form-control" id="announcement_expires_at" name="expires_at" value="{{ old('expires_at') }}">
                            </div>
                            <div class="col-12" id="announcement_service_fields" hidden>
                                <h3 class="announcement-section mb-2">Service Information <span class="text-secondary fw-normal">(optional)</span></h3>
                                <div class="row g-3">
                                    <div class="col-12"><label class="form-label fw-semibold" for="announcement_affected_area">Affected Area / Purok</label><input type="text" class="form-control" id="announcement_affected_area" name="affected_area" maxlength="255" value="{{ old('affected_area') }}"></div>
                                    <div class="col-md-6"><label class="form-label fw-semibold" for="announcement_service_start">Service Interruption Start</label><input type="datetime-local" class="form-control" id="announcement_service_start" name="service_starts_at" value="{{ old('service_starts_at') }}"></div>
                                    <div class="col-md-6"><label class="form-label fw-semibold" for="announcement_service_restore">Expected Restoration</label><input type="datetime-local" class="form-control" id="announcement_service_restore" name="service_restores_at" value="{{ old('service_restores_at') }}"><div class="form-text">Enter the interruption start when providing a restoration time.</div></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="announcement_photo">Attachment <span class="text-secondary fw-normal">(optional, JPG, PNG or PDF, up to 5 MB)</span></label>
                                <input class="form-control" type="file" id="announcement_photo" name="photo" accept="image/jpeg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf">
                                <img id="announcement_photo_preview" class="img-fluid rounded mt-2 d-none" alt="Announcement photo preview" style="max-height: 220px">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="announcement_is_pinned" name="is_pinned" @checked(old('is_pinned'))>
                                    <label class="form-check-label" for="announcement_is_pinned">
                                        Pin this announcement to the top of the feed
                                    </label>
                                </div>
                            </div>
                            <div class="col-12" id="announcement_preview" hidden aria-live="polite">
                                <div class="announcement-preview"><div class="small text-secondary mb-1">Announcement Preview</div><div class="fw-bold" id="announcement_preview_title"></div><div class="small text-secondary" id="announcement_preview_meta"></div><p class="mb-0 mt-2" id="announcement_preview_body" style="white-space:pre-wrap"></p><div class="small mt-2" id="announcement_preview_service"></div><div class="small mt-2" id="announcement_preview_schedule"></div></div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button type="button" class="btn btn-outline-primary" id="announcement_preview_button">Preview</button>
                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Publish</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="announcementDeleteModal" tabindex="-1" aria-labelledby="announcementDeleteTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header"><h2 class="modal-title fs-5" id="announcementDeleteTitle">Delete Announcement?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <form method="POST" id="announcementDeleteForm">
                @csrf
                @method('DELETE')
                <div class="modal-body"><p>Are you sure you want to delete this announcement? It will no longer be visible to consumers.</p><strong id="announcementDeleteName" class="announcement-safe-wrap"></strong></div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Delete Announcement</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="announcementEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Edit Announcement</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="announcementEditForm" enctype="multipart/form-data">
                        @csrf
                        @method('PATCH')
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="announcement_edit_title">Title</label>
                                <input type="text" class="form-control" id="announcement_edit_title" name="title" maxlength="255" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="announcement_edit_category">Category</label>
                                <select class="form-select" id="announcement_edit_category" name="category" required>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="announcement_edit_audience">Audience</label>
                                <select class="form-select" id="announcement_edit_audience" name="audience" required>
                                    @foreach ($audiences as $audienceValue => $audienceLabel)
                                        <option value="{{ $audienceValue }}">{{ $audienceLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 d-none" id="announcement_edit_recipient_users_wrap">
                                <label class="form-label fw-semibold" for="announcement_edit_recipient_users">Specific users</label>
                                <select class="form-select" id="announcement_edit_recipient_users" name="recipient_user_ids[]" multiple size="5">
                                    @foreach ($recipientUsers as $recipientUser)
                                        <option value="{{ $recipientUser->id }}">{{ $recipientUser->name }} — {{ $recipientUser->email }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="announcement_edit_body">Details</label>
                                <textarea class="form-control" id="announcement_edit_body" name="body" rows="5" maxlength="2000" required></textarea>
                            </div>
                            <div class="col-md-4"><label class="form-label fw-semibold" for="announcement_edit_priority">Priority</label><select class="form-select" id="announcement_edit_priority" name="priority"><option>Normal</option><option>Important</option><option>Urgent</option></select></div>
                            <div class="col-md-4"><label class="form-label fw-semibold" for="announcement_edit_published">Publish Date and Time (PHT)</label><input type="datetime-local" class="form-control" id="announcement_edit_published" name="published_at"></div>
                            <div class="col-md-4"><label class="form-label fw-semibold" for="announcement_edit_expires">Expiration (PHT)</label><input type="datetime-local" class="form-control" id="announcement_edit_expires" name="expires_at"></div>
                            <div class="col-12" id="announcement_edit_service_fields" hidden>
                                <div class="row g-3">
                                    <div class="col-12"><label class="form-label fw-semibold" for="announcement_edit_area">Affected Area / Purok</label><input type="text" class="form-control" id="announcement_edit_area" name="affected_area" maxlength="255"></div>
                                    <div class="col-md-6"><label class="form-label fw-semibold" for="announcement_edit_start">Service Interruption Start (PHT)</label><input type="datetime-local" class="form-control" id="announcement_edit_start" name="service_starts_at"></div>
                                    <div class="col-md-6"><label class="form-label fw-semibold" for="announcement_edit_restore">Expected Restoration (PHT)</label><input type="datetime-local" class="form-control" id="announcement_edit_restore" name="service_restores_at"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="announcement_edit_photo">Replace attachment (up to 5 MB)</label>
                                <input class="form-control" type="file" id="announcement_edit_photo" name="photo" accept="image/jpeg,image/png,image/webp,application/pdf,.pdf">
                                <a id="announcement_edit_current_attachment" class="d-none d-block mt-2" target="_blank" rel="noopener">View current attachment</a>
                                <img id="announcement_edit_photo_preview" class="img-fluid rounded mt-2 d-none" alt="Announcement photo preview" style="max-height: 220px">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" value="1" id="announcement_remove_photo" name="remove_photo">
                                    <label class="form-check-label" for="announcement_remove_photo">Remove current attachment</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="announcement_edit_is_pinned" name="is_pinned">
                                    <label class="form-check-label" for="announcement_edit_is_pinned">
                                        Keep this announcement pinned
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
@if ($canManageAnnouncements)
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @if ($errors->any() && old('announcement_form') === 'create')
            bootstrap.Modal.getOrCreateInstance(document.getElementById('announcementCreateModal')).show();
        @endif
        const editModal = document.getElementById('announcementEditModal');
        const deleteModal = document.getElementById('announcementDeleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            document.getElementById('announcementDeleteForm').action = event.relatedTarget.dataset.deleteUrl;
            document.getElementById('announcementDeleteName').textContent = event.relatedTarget.dataset.title;
        });
        document.getElementById('announcementDeleteForm').addEventListener('submit', function () {
            this.querySelector('[type=submit]').disabled = true;
        });
        const createAudience = document.getElementById('announcement_audience');
        const createRecipientWrap = document.getElementById('announcement_recipient_users_wrap');
        const editAudience = document.getElementById('announcement_edit_audience');
        const editRecipientWrap = document.getElementById('announcement_edit_recipient_users_wrap');

        function toggleRecipients(select, wrap) {
            if (select && wrap) wrap.classList.toggle('d-none', select.value !== 'Specific Users');
        }
        createAudience?.addEventListener('change', () => toggleRecipients(createAudience, createRecipientWrap));
        editAudience?.addEventListener('change', () => toggleRecipients(editAudience, editRecipientWrap));
        toggleRecipients(createAudience, createRecipientWrap);

        function toggleServiceFields(categoryId, fieldsId) {
            const category = document.getElementById(categoryId);
            const fields = document.getElementById(fieldsId);
            const show = ['Service Advisory', 'Water Interruption', 'Maintenance'].includes(category.value);
            fields.hidden = !show;
            fields.querySelectorAll('input').forEach(input => input.disabled = !show);
        }
        const createCategory = document.getElementById('announcement_category');
        const editCategory = document.getElementById('announcement_edit_category');
        createCategory.addEventListener('change', () => toggleServiceFields('announcement_category', 'announcement_service_fields'));
        editCategory.addEventListener('change', () => toggleServiceFields('announcement_edit_category', 'announcement_edit_service_fields'));
        toggleServiceFields('announcement_category', 'announcement_service_fields');

        document.getElementById('announcement_preview_button').addEventListener('click', function () {
            const value = id => document.getElementById(id).value.trim();
            const preview = document.getElementById('announcement_preview');
            document.getElementById('announcement_preview_title').textContent = value('announcement_title') || 'Untitled announcement';
            document.getElementById('announcement_preview_meta').textContent = [value('announcement_category'), createAudience.options[createAudience.selectedIndex].text, value('announcement_priority')].join(' · ');
            document.getElementById('announcement_preview_body').textContent = value('announcement_body');
            const serviceVisible = !document.getElementById('announcement_service_fields').hidden;
            document.getElementById('announcement_preview_service').textContent = serviceVisible ? [value('announcement_affected_area'), value('announcement_service_start'), value('announcement_service_restore')].filter(Boolean).join(' · ') : '';
            const attachment = document.getElementById('announcement_photo').files[0];
            document.getElementById('announcement_preview_schedule').textContent = 'Publish: ' + value('announcement_published_at').replace('T', ' ') + (value('announcement_expires_at') ? ' · Expires: ' + value('announcement_expires_at').replace('T', ' ') : '') + (attachment ? ' · Attachment: ' + attachment.name : '') + (document.getElementById('announcement_is_pinned').checked ? ' · Pinned' : '') + ' (PHT)';
            preview.hidden = false;
        });

        function previewUpload(inputId, imageId) {
            const input = document.getElementById(inputId);
            const image = document.getElementById(imageId);
            let objectUrl = null;
            input?.addEventListener('change', () => {
                if (objectUrl) URL.revokeObjectURL(objectUrl);
                const file = input.files?.[0];
                if (!file || !file.type.startsWith('image/')) {
                    image.removeAttribute('src');
                    image.classList.add('d-none');
                    return;
                }
                objectUrl = URL.createObjectURL(file);
                image.src = objectUrl;
                image.classList.remove('d-none');
            });
        }
        previewUpload('announcement_photo', 'announcement_photo_preview');
        previewUpload('announcement_edit_photo', 'announcement_edit_photo_preview');

        if (!editModal) {
            return;
        }

        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const form = document.getElementById('announcementEditForm');

            form.action = button.getAttribute('data-announcement-update-url');
            document.getElementById('announcement_edit_photo').value = '';
            document.getElementById('announcement_edit_title').value = button.getAttribute('data-announcement-title') || '';
            document.getElementById('announcement_edit_body').value = button.getAttribute('data-announcement-body') || '';
            document.getElementById('announcement_edit_category').value = button.getAttribute('data-announcement-category') || 'Community Update';
            document.getElementById('announcement_edit_audience').value = button.getAttribute('data-announcement-audience') || 'Everyone';
            document.getElementById('announcement_edit_priority').value = button.getAttribute('data-announcement-priority') || 'Normal';
            document.getElementById('announcement_edit_published').value = button.getAttribute('data-announcement-published') || '';
            document.getElementById('announcement_edit_expires').value = button.getAttribute('data-announcement-expires') || '';
            document.getElementById('announcement_edit_area').value = button.getAttribute('data-announcement-area') || '';
            document.getElementById('announcement_edit_start').value = button.getAttribute('data-announcement-start') || '';
            document.getElementById('announcement_edit_restore').value = button.getAttribute('data-announcement-restore') || '';
            toggleServiceFields('announcement_edit_category', 'announcement_edit_service_fields');
            const recipientIds = JSON.parse(button.getAttribute('data-announcement-recipients') || '[]').map(String);
            document.querySelectorAll('#announcement_edit_recipient_users option').forEach(option => option.selected = recipientIds.includes(option.value));
            toggleRecipients(editAudience, editRecipientWrap);
            const currentPhoto = button.getAttribute('data-announcement-photo') || '';
            const photoPreview = document.getElementById('announcement_edit_photo_preview');
            const currentAttachment = document.getElementById('announcement_edit_current_attachment');
            currentAttachment.href = currentPhoto || '#';
            currentAttachment.classList.toggle('d-none', !currentPhoto);
            photoPreview.src = currentPhoto;
            photoPreview.classList.toggle('d-none', !currentPhoto || button.getAttribute('data-announcement-photo-is-pdf') === '1');
            document.getElementById('announcement_remove_photo').checked = false;
            document.getElementById('announcement_edit_is_pinned').checked = button.getAttribute('data-announcement-pinned') === '1';
        });
    });
</script>
@endif
@endpush
