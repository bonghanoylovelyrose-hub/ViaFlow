@if ($announcement->photo_path)
    @if (strtolower(pathinfo($announcement->photo_path, PATHINFO_EXTENSION)) === 'pdf')
    <a href="{{ route('announcements.photo', $announcement) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mb-3">Open PDF attachment</a>
    @else
    <a href="{{ route('announcements.photo', $announcement) }}" target="_blank" rel="noopener" class="d-block mb-3">
        <img src="{{ route('announcements.photo', $announcement) }}" alt="Photo for {{ $announcement->title }}" class="img-fluid rounded-3 w-100" style="max-height: 300px; object-fit: contain; background: #f4f7f8">
    </a>
    @endif
@endif
