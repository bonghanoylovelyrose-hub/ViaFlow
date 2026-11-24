@php
    $activePage = $activePage ?? 'dashboard';
    $user = auth()->user();
    $menuItems = $user->isConsumer()
        ? [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill', 'href' => '/dashboard'],
            ['key' => 'billing', 'label' => 'Billing', 'icon' => 'bi-droplet-half', 'href' => route('billing.index')],
            ['key' => 'payments', 'label' => 'My Payments', 'icon' => 'bi-cash-coin', 'href' => '/payments'],
            ...($user->consumer?->hasVerifiedMembership()
                ? [['key' => 'loans', 'label' => 'My Loans', 'icon' => 'bi-bank', 'href' => '/loans']]
                : []),
            ['key' => 'incidents', 'label' => 'My Incidents', 'icon' => 'bi-exclamation-triangle-fill', 'href' => '/incidents'],
            ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'bi-megaphone-fill', 'href' => '/announcements'],
        ]
        : ($user->isMeterReader()
            ? [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill', 'href' => '/dashboard'],
                ['key' => 'meter-readings', 'label' => 'Meter Readings', 'icon' => 'bi-speedometer2', 'href' => '/meter-readings'],
                ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'bi-megaphone-fill', 'href' => '/announcements'],
            ]
        : [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill', 'href' => '/dashboard'],
            ['key' => 'staff', 'label' => 'Staff', 'icon' => 'bi-people', 'href' => '/staff'],
            ['key' => 'consumers', 'label' => 'Consumers', 'icon' => 'bi-people-fill', 'href' => '/consumers'],
            ['key' => 'billing', 'label' => 'Billing', 'icon' => 'bi-droplet-half', 'href' => route('billing.index')],
            ['key' => 'payments', 'label' => 'Payments', 'icon' => 'bi-cash-coin', 'href' => '/payments'],
            ['key' => 'loans', 'label' => 'Loans', 'icon' => 'bi-bank', 'href' => '/loans'],
            ['key' => 'incidents', 'label' => 'Incidents', 'icon' => 'bi-exclamation-triangle-fill', 'href' => '/incidents'],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'bi-bar-chart-fill', 'href' => '/reports'],
            ['key' => 'dss', 'label' => 'DSS', 'icon' => 'bi-diagram-3-fill', 'href' => '/dss/incidents'],
            ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'bi-megaphone-fill', 'href' => '/announcements'],
        ]);

    if ($disconnectedAccountAccess ?? false) {
        $menuItems = [
            ['key' => 'account', 'label' => 'Account Summary', 'icon' => 'bi-person-vcard', 'href' => route('account.inactive')],
            ['key' => 'billing', 'label' => 'Billing', 'icon' => 'bi-droplet-half', 'href' => route('billing.index')],
            ['key' => 'payments', 'label' => 'My Payments', 'icon' => 'bi-cash-coin', 'href' => route('payments.index')],
            ['key' => 'connection', 'label' => 'Reconnection Status', 'icon' => 'bi-tools', 'href' => route('connection.status')],
        ];
    }

    $consumerCategories = [
        '' => 'All Consumers',
        'pending' => 'Pending Accounts',
        'rejected' => 'Rejected Accounts',
        'active' => 'Active Accounts',
        'inactive' => 'Inactive (Disconnected)',
    ];

    $consumerFilters = $activePage === 'consumers' ? ($filters ?? []) : [];
    $selectedConsumerCategory = $activePage === 'consumers'
        ? ($consumerFilters['account_category'] ?? '')
        : null;
    $consumerFilterContext = array_filter([
        'search' => $consumerFilters['search'] ?? null,
        'membership' => $consumerFilters['membership'] ?? null,
        'membership_verification' => $consumerFilters['membership_verification'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
    $dssActive = in_array($activePage, ['dss-incidents', 'dss-loans'], true);
    $billingActive = in_array($activePage, ['billing', 'billing-water', 'billing-loan'], true);
@endphp

<aside
    class="sidebar p-3"
    id="sidebarMenu"
    aria-label="Main navigation"
>
    <a href="{{ ($disconnectedAccountAccess ?? false) ? route('account.inactive') : route('dashboard') }}" class="sidebar-brand d-flex align-items-center gap-3 mb-4">
        <x-organization-logo size="sidebar" />
        <span>
            <span class="d-block fw-bold fs-5">ViaFlow System</span>
            <span class="small">Billing and Loan Suite</span>
        </span>
    </a>

    <div class="sidebar-nav-header d-flex justify-content-between align-items-center gap-2 mb-3">
        <div class="sidebar-nav-heading small text-uppercase fw-semibold">Main Navigation</div>
        <button
            type="button"
            class="sidebar-nav-toggle"
            id="sidebarCollapseToggle"
            aria-expanded="true"
            aria-controls="sidebarMenu"
            aria-label="Collapse sidebar"
            title="Collapse sidebar"
        >
            <i class="bi bi-chevron-left" aria-hidden="true"></i>
        </button>
    </div>

    <ul class="nav flex-column gap-2">
        @foreach ($menuItems as $item)
            <li class="nav-item">
                @if ($item['key'] === 'billing')
                    <div class="sidebar-nav-row {{ $billingActive ? 'active' : '' }}">
                        <a href="{{ route('billing.index') }}" class="nav-link {{ $billingActive ? 'active' : '' }}" aria-label="{{ $item['label'] }}" title="{{ $item['label'] }}">
                            <i class="bi {{ $item['icon'] }}"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                        <button
                            type="button"
                            class="sidebar-submenu-toggle"
                            data-bs-toggle="collapse"
                            data-bs-target="#billingSubmenu"
                            aria-expanded="{{ $billingActive ? 'true' : 'false' }}"
                            aria-controls="billingSubmenu"
                            aria-label="Toggle billing submodules"
                        ><i class="bi bi-chevron-down"></i></button>
                    </div>
                    <div class="collapse {{ $billingActive ? 'show' : '' }}" id="billingSubmenu">
                        <ul class="nav flex-column sidebar-subnav">
                            <li class="nav-item">
                                <a href="{{ route('billing.index') }}" class="nav-link sidebar-subnav-link {{ $activePage === 'billing-water' ? 'active' : '' }}" @if ($activePage === 'billing-water') aria-current="page" @endif>Water Billing</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('billing.loan') }}" class="nav-link sidebar-subnav-link {{ $activePage === 'billing-loan' ? 'active' : '' }}" @if ($activePage === 'billing-loan') aria-current="page" @endif>Loan Billing</a>
                            </li>
                        </ul>
                    </div>
                @elseif ($item['key'] === 'consumers')
                    <div class="sidebar-nav-row {{ $activePage === 'consumers' ? 'active' : '' }}">
                        <a href="{{ route('consumers.index') }}" class="nav-link {{ $activePage === 'consumers' ? 'active' : '' }}" aria-label="{{ $item['label'] }}" title="{{ $item['label'] }}">
                            <i class="bi {{ $item['icon'] }}"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                        <button
                            type="button"
                            class="sidebar-submenu-toggle"
                            data-bs-toggle="collapse"
                            data-bs-target="#consumerSubmenu"
                            aria-expanded="{{ $activePage === 'consumers' ? 'true' : 'false' }}"
                            aria-controls="consumerSubmenu"
                            aria-label="Toggle consumer categories"
                        >
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>

                    <div class="collapse {{ $activePage === 'consumers' ? 'show' : '' }}" id="consumerSubmenu">
                        <ul class="nav flex-column sidebar-subnav">
                            @foreach ($consumerCategories as $categoryKey => $categoryLabel)
                                @php
                                    $categoryQuery = $categoryKey === ''
                                        ? $consumerFilterContext
                                        : array_merge($consumerFilterContext, ['account_category' => $categoryKey]);
                                    $categoryQuery = array_filter(
                                        $categoryQuery,
                                        fn ($value) => $value !== null && $value !== '',
                                    );
                                @endphp
                                <li class="nav-item">
                                    <a
                                        href="{{ route('consumers.index', $categoryQuery) }}"
                                        class="nav-link sidebar-subnav-link d-flex justify-content-between align-items-center {{ $selectedConsumerCategory === $categoryKey ? 'active' : '' }}"
                                        data-consumer-category="{{ $categoryKey === '' ? 'all' : $categoryKey }}" @if ($selectedConsumerCategory === $categoryKey) aria-current="page" @endif
                                    >
                                        <span>{{ $categoryLabel }}</span>
                                        @if ($categoryKey === 'pending' && ($layoutMeta['pending_reschedules_count'] ?? 0) > 0)
                                            <span class="badge rounded-pill bg-warning text-dark ms-2" title="{{ $layoutMeta['pending_reschedules_count'] }} pending reschedule request(s)">{{ $layoutMeta['pending_reschedules_count'] }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @elseif ($item['key'] === 'dss')
                    <div class="sidebar-nav-row {{ $dssActive ? 'active' : '' }}">
                        <a href="{{ route('dss.incidents') }}" class="nav-link {{ $dssActive ? 'active' : '' }}" aria-label="{{ $item['label'] }}" title="{{ $item['label'] }}">
                            <i class="bi {{ $item['icon'] }}"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                        <button
                            type="button"
                            class="sidebar-submenu-toggle"
                            data-bs-toggle="collapse"
                            data-bs-target="#dssSubmenu"
                            aria-expanded="{{ $dssActive ? 'true' : 'false' }}"
                            aria-controls="dssSubmenu"
                            aria-label="Toggle DSS modules"
                        >
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>

                    <div class="collapse {{ $dssActive ? 'show' : '' }}" id="dssSubmenu">
                        <ul class="nav flex-column sidebar-subnav">
                            <li class="nav-item">
                                <a
                                    href="{{ route('dss.incidents') }}"
                                    class="nav-link sidebar-subnav-link {{ $activePage === 'dss-incidents' ? 'active' : '' }}"
                                    @if ($activePage === 'dss-incidents') aria-current="page" @endif
                                >Incident DSS</a>
                            </li>
                            <li class="nav-item">
                                <a
                                    href="{{ route('dss.loans') }}"
                                    class="nav-link sidebar-subnav-link {{ $activePage === 'dss-loans' ? 'active' : '' }}"
                                    @if ($activePage === 'dss-loans') aria-current="page" @endif
                                >Loan DSS</a>
                            </li>
                        </ul>
                    </div>
                @else
                    <a href="{{ $item['href'] }}" class="nav-link {{ $activePage === $item['key'] ? 'active' : '' }}" aria-label="{{ $item['label'] }}" title="{{ $item['label'] }}">
                        <i class="bi {{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</aside>

<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const appShell = document.querySelector('.app-shell');
        const sidebar = document.getElementById('sidebarMenu');
        const collapseToggle = document.getElementById('sidebarCollapseToggle');
        const mobileToggle = document.getElementById('sidebarMobileToggle');
        const backdrop = document.getElementById('sidebarBackdrop');
        const mainContent = document.querySelector('.main-content');

        if (!appShell || !sidebar) {
            return;
        }

        const mobileQuery = window.matchMedia('(max-width: 991.98px)');
        const toggles = [collapseToggle, mobileToggle].filter(Boolean);

        const isMobile = function () {
            return mobileQuery.matches;
        };

        const isSidebarOpen = function () {
            return isMobile()
                ? appShell.classList.contains('sidebar-mobile-open')
                : !appShell.classList.contains('sidebar-collapsed');
        };

        const syncState = function () {
            const open = isSidebarOpen();

            toggles.forEach(function (toggle) {
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });

            if (collapseToggle) {
                const label = open ? 'Collapse sidebar' : 'Open sidebar';

                collapseToggle.setAttribute('aria-label', label);
                collapseToggle.setAttribute('title', label);
            }

            if (mobileToggle) {
                const label = open ? 'Close sidebar' : 'Open sidebar';

                mobileToggle.setAttribute('aria-label', label);
                mobileToggle.setAttribute('title', label);
            }

            sidebar.toggleAttribute('inert', isMobile() && !open);

            if (mainContent) {
                mainContent.toggleAttribute('inert', isMobile() && open);
            }
        };

        const openSidebar = function () {
            if (isMobile()) {
                appShell.classList.add('sidebar-mobile-open');
            } else {
                appShell.classList.remove('sidebar-collapsed');
            }

            syncState();

            const firstLink = sidebar.querySelector('.nav-link:not(.sidebar-subnav-link)')
                || sidebar.querySelector('.nav-link');

            if (isMobile() && firstLink) {
                window.setTimeout(function () {
                    firstLink.focus();
                }, 0);
            }
        };

        const closeSidebar = function () {
            if (isMobile()) {
                appShell.classList.remove('sidebar-mobile-open');
            } else {
                appShell.classList.add('sidebar-collapsed');
            }

            syncState();

            const focusTarget = isMobile() && mobileToggle ? mobileToggle : collapseToggle;

            if (focusTarget) {
                window.setTimeout(function () {
                    focusTarget.focus();
                }, 0);
            }
        };

        const toggleSidebar = function () {
            if (isSidebarOpen()) {
                closeSidebar();
            } else {
                openSidebar();
            }
        };

        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', toggleSidebar);
        });

        sidebar.querySelectorAll('.sidebar-submenu-toggle').forEach(function (toggle) {
            toggle.addEventListener('click', function (event) {
                if (!isMobile() && appShell.classList.contains('sidebar-collapsed')) {
                    const submenu = document.getElementById(toggle.getAttribute('aria-controls'));
                    openSidebar();
                    // An already-open submenu becomes visible as the rail expands.
                    if (submenu && submenu.classList.contains('show')) event.stopPropagation();
                }
            });
        });

        if (backdrop) {
            backdrop.addEventListener('click', closeSidebar);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && isMobile() && isSidebarOpen()) {
                closeSidebar();
            }
        });

        const handleBreakpointChange = function () {
            appShell.classList.remove('sidebar-mobile-open', 'sidebar-collapsed');
            syncState();
        };

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', handleBreakpointChange);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(handleBreakpointChange);
        }

        syncState();
    });
</script>
@endpush
