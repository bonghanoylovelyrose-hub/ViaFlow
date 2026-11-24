@extends('layouts.app')

@section('title', $announcement->title)

@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <div class="page-heading">
                <div>
                    <h1>Announcement</h1>
                    <p>Published {{ $announcement->published_at?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') ?? '—' }} PHT · {{ $announcement->category }}</p>
                </div>
                <a href="{{ route('announcements.index') }}" class="btn btn-light border">Back to Announcements</a>
            </div>
            <article class="panel-card p-4 p-lg-5">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge badge-soft-primary">{{ $announcement->audience }}</span>
                    @if ($announcement->edited_at)<span class="badge badge-soft-neutral">Edited</span>@endif
                                @if ($announcement->is_pinned)<span class="badge badge-soft-warning">Pinned</span>@endif
                    @if ($announcement->priority !== 'Normal')<span class="badge badge-soft-primary">{{ $announcement->priority }}</span>@endif
                </div>
                <h2 class="h3 fw-bold">{{ $announcement->title }}</h2>
                <p class="text-secondary">Posted by {{ $announcement->author?->name ?? 'System Admin' }}</p>
                @include('shared.announcements._image', ['announcement' => $announcement])
                <div class="announcement-safe-wrap" style="white-space: pre-line">{{ $announcement->body }}</div>
                @if ($announcement->affected_area || $announcement->service_starts_at || $announcement->service_restores_at)
                    <div class="border rounded-3 p-3 mt-3">
                        @if ($announcement->affected_area)<div><strong>Affected area:</strong> {{ $announcement->affected_area }}</div>@endif
                        @if ($announcement->service_starts_at)<div><strong>Interruption starts:</strong> {{ $announcement->service_starts_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</div>@endif
                        @if ($announcement->service_restores_at)<div><strong>Expected restoration:</strong> {{ $announcement->service_restores_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</div>@endif
                    </div>
                @endif
            </article>
        </div>
    </main>
</div>
@endsection
