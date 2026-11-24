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
                @if ($canManageAnnouncements)
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#announcementCreateModal">
                        <i class="bi bi-megaphone me-2"></i>Post Announcement
                    </button>
                @endif
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
                        <div class="text-secondary small">Visible Announcements</div>
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

            <div class="panel-card p-4 mb-4">
                <div class="row g-4">
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
                                        @if ($announcement->is_pinned)
                                            <span class="badge badge-soft-warning">Pinned</span>
                                        @endif
                                    </div>
                                    <span class="small text-secondary text-end announcement-date announcement-safe-wrap">{{ optional($announcement->published_at)->format('M d, Y') }}</span>
                                </div>
                                <h2 class="h5 fw-bold announcement-safe-wrap">{{ $announcement->title }}</h2>
                                <p class="text-secondary mb-3 flex-grow-1 announcement-safe-wrap">{{ $announcement->body }}</p>
                                @include('shared.announcements._image', ['announcement' => $announcement])
                                <a class="btn btn-sm btn-outline-primary align-self-start mb-3" href="{{ route('announcements.show', $announcement) }}">View Details</a>
                                <div class="announcement-footer d-flex justify-content-between align-items-center gap-3 mt-auto">
                                    <div class="small text-secondary announcement-safe-wrap">
                                        Posted by {{ $announcement->author?->name ?? 'System Admin' }}
                                    </div>
                                    @if ($canManageAnnouncements)
                                        <div class="d-inline-flex gap-2">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-light border"
                                                data-bs-toggle="modal"
                                                data-bs-target="#announcementEditModal"
                                                data-announcement-id="{{ $announcement->id }}"
                                                data-announcement-title="{{ $announcement->title }}"
                                                data-announcement-body="{{ $announcement->body }}"
                                                data-announcement-category="{{ $announcement->category }}"
                                                data-announcement-audience="{{ $announcement->audience }}"
                                                data-announcement-pinned="{{ $announcement->is_pinned ? '1' : '0' }}"
                                            >
                                                Edit
                                            </button>
                                            <form method="POST" action="{{ route('announcements.destroy', $announcement) }}" onsubmit="return confirm('Delete this announcement?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
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

                <div class="d-flex justify-content-between align-items-center mt-4">
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
                    <form method="POST" action="{{ route('announcements.store') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="announcement_title">Title</label>
                                <input type="text" class="form-control" id="announcement_title" name="title" value="{{ old('title') }}" maxlength="255" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="announcement_category">Category</label>
                                <select class="form-select" id="announcement_category" name="category" required>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="announcement_audience">Audience</label>
                                <select class="form-select" id="announcement_audience" name="audience" required>
                                    @foreach ($audiences as $audience)
                                        <option value="{{ $audience }}" @selected(old('audience', 'Everyone') === $audience)>{{ $audience }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="announcement_body">Details</label>
                                <textarea class="form-control" id="announcement_body" name="body" rows="5" maxlength="2000" required>{{ old('body') }}</textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="announcement_is_pinned" name="is_pinned" @checked(old('is_pinned'))>
                                    <label class="form-check-label" for="announcement_is_pinned">
                                        Pin this announcement to the top of the feed
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Publish</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="announcementEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Edit Announcement</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="announcementEditForm">
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
                                    @foreach ($audiences as $audience)
                                        <option value="{{ $audience }}">{{ $audience }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="announcement_edit_body">Details</label>
                                <textarea class="form-control" id="announcement_edit_body" name="body" rows="5" maxlength="2000" required></textarea>
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
        const editModal = document.getElementById('announcementEditModal');

        if (!editModal) {
            return;
        }

        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const form = document.getElementById('announcementEditForm');

            form.action = '/announcements/' + button.getAttribute('data-announcement-id');
            document.getElementById('announcement_edit_title').value = button.getAttribute('data-announcement-title') || '';
            document.getElementById('announcement_edit_body').value = button.getAttribute('data-announcement-body') || '';
            document.getElementById('announcement_edit_category').value = button.getAttribute('data-announcement-category') || 'Community Update';
            document.getElementById('announcement_edit_audience').value = button.getAttribute('data-announcement-audience') || 'Everyone';
            document.getElementById('announcement_edit_is_pinned').checked = button.getAttribute('data-announcement-pinned') === '1';
        });
    });
</script>
@endif
@endpush
