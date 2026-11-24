<nav class="navbar navbar-expand-lg topbar px-3 px-lg-4 sticky-top">
    <div class="container-fluid p-0">
        <button class="btn btn-outline-secondary d-lg-none me-2" type="button" id="sidebarMobileToggle" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Open sidebar" title="Open sidebar">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <div>
            <span class="text-uppercase small fw-bold text-secondary">{{ auth()->user()->isConsumer() ? 'Consumer Portal' : 'System Overview' }}</span>
            <div class="fw-semibold">{{ auth()->user()->isConsumer() ? 'Water Billing and Account Access' : 'Water Billing and Loan Management' }}</div>
        </div>

        <div class="d-flex align-items-center gap-3 ms-auto">
            <div class="d-none d-md-flex align-items-center gap-2 text-secondary">
                <i class="bi bi-calendar3"></i>
                <span>{{ $layoutMeta['current_date'] }}</span>
            </div>
            <div class="dropdown">
                <button class="btn btn-light border position-relative" data-bs-toggle="dropdown" type="button">
                    <i class="bi bi-bell"></i>
                    @if ($layoutMeta['unread_notifications'] > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $layoutMeta['unread_notifications'] }}</span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-0 notification-menu">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <strong>Notifications</strong>
                        @if ($layoutMeta['unread_notifications'] > 0)
                            <form method="POST" action="{{ route('notifications.read-all') }}">
                                @csrf
                                <button type="submit" class="btn btn-link btn-sm text-decoration-none p-0">Mark all read</button>
                            </form>
                        @endif
                    </div>
                    <div class="py-2" style="max-height: 340px; overflow-y: auto;">
                        @forelse ($layoutMeta['recent_notifications'] as $notification)
                            <a href="{{ route('notifications.open', $notification) }}" class="dropdown-item py-2 px-3 notification-item">
                                <div class="d-flex justify-content-between align-items-start gap-3 notification-item-body">
                                    <div class="notification-item-body">
                                        <div class="fw-semibold text-dark notification-item-title">{{ $notification->data['title'] ?? 'Notification' }}</div>
                                        <div class="small text-secondary notification-item-message">{{ $notification->data['message'] ?? '' }}</div>
                                    </div>
                                    @if (is_null($notification->read_at))
                                        <span class="badge badge-soft-primary">New</span>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <div class="px-3 py-3 text-secondary small">No notifications yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="dropdown">
                <button class="btn btn-light border dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" type="button">
                    <span class="rounded-circle bg-info-subtle text-info fw-bold d-inline-flex align-items-center justify-content-center avatar-badge">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
                    <span class="text-start d-none d-md-block">
                        <span class="d-block fw-semibold text-dark">{{ auth()->user()->name }}</span>
                        <span class="small text-secondary">{{ auth()->user()->isConsumer() ? (auth()->user()->consumer?->membership_type ?: 'Not available') : str(auth()->user()->role)->replace('_', ' ')->title() }}</span>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-2 profile-dropdown-menu">
                    <li><a class="dropdown-item px-3 py-2 rounded-3" href="{{ route('consumer.profile.show') }}">Profile</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item px-3 py-2 rounded-3 text-danger">Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
