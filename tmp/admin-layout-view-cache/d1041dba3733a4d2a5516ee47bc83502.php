<?php
    $activePage = $activePage ?? 'dashboard';
    $user = auth()->user();
    $menuItems = $user->isConsumer()
        ? [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill', 'href' => '/dashboard'],
            ['key' => 'billing', 'label' => 'My Bills', 'icon' => 'bi-droplet-half', 'href' => '/billing'],
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
            ['key' => 'staff', 'label' => 'Staff', 'icon' => 'bi-person-badge-fill', 'href' => '/staff'],
            ['key' => 'consumers', 'label' => 'Consumers', 'icon' => 'bi-people-fill', 'href' => '/consumers'],
            ['key' => 'billing', 'label' => 'Billing', 'icon' => 'bi-droplet-half', 'href' => '/billing'],
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
            ['key' => 'billing', 'label' => 'My Bills', 'icon' => 'bi-droplet-half', 'href' => route('billing.index')],
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
?>

<aside
    class="sidebar p-3"
    id="sidebarMenu"
    aria-label="Main navigation"
>
    <a href="<?php echo e(($disconnectedAccountAccess ?? false) ? route('account.inactive') : route('dashboard')); ?>" class="sidebar-brand d-flex align-items-center gap-3 mb-4">
        <span class="feature-icon bg-white text-primary rounded-4">
            <i class="bi bi-droplet-fill"></i>
        </span>
        <span>
            <span class="d-block fw-bold fs-5">ViaFlow System</span>
            <span class="small opacity-75">Billing and Loan Suite</span>
        </span>
    </a>

    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-uppercase opacity-75 fw-semibold">Main Navigation</div>
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
        <?php $__currentLoopData = $menuItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li class="nav-item">
                <?php if($item['key'] === 'consumers'): ?>
                    <div class="sidebar-nav-row <?php echo e($activePage === 'consumers' ? 'active' : ''); ?>">
                        <a href="<?php echo e(route('consumers.index')); ?>" class="nav-link <?php echo e($activePage === 'consumers' ? 'active' : ''); ?>">
                            <i class="bi <?php echo e($item['icon']); ?>"></i>
                            <span><?php echo e($item['label']); ?></span>
                        </a>
                        <button
                            type="button"
                            class="sidebar-submenu-toggle"
                            data-bs-toggle="collapse"
                            data-bs-target="#consumerSubmenu"
                            aria-expanded="<?php echo e($activePage === 'consumers' ? 'true' : 'false'); ?>"
                            aria-controls="consumerSubmenu"
                            aria-label="Toggle consumer categories"
                        >
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>

                    <div class="collapse <?php echo e($activePage === 'consumers' ? 'show' : ''); ?>" id="consumerSubmenu">
                        <ul class="nav flex-column sidebar-subnav">
                            <?php $__currentLoopData = $consumerCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryKey => $categoryLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $categoryQuery = $categoryKey === ''
                                        ? $consumerFilterContext
                                        : array_merge($consumerFilterContext, ['account_category' => $categoryKey]);
                                    $categoryQuery = array_filter(
                                        $categoryQuery,
                                        fn ($value) => $value !== null && $value !== '',
                                    );
                                ?>
                                <li class="nav-item">
                                    <a
                                        href="<?php echo e(route('consumers.index', $categoryQuery)); ?>"
                                        class="nav-link sidebar-subnav-link d-flex justify-content-between align-items-center <?php echo e($selectedConsumerCategory === $categoryKey ? 'active' : ''); ?>"
                                        data-consumer-category="<?php echo e($categoryKey === '' ? 'all' : $categoryKey); ?>" <?php if($selectedConsumerCategory === $categoryKey): ?> aria-current="page" <?php endif; ?>
                                    >
                                        <span><?php echo e($categoryLabel); ?></span>
                                        <?php if($categoryKey === 'pending' && ($layoutMeta['pending_reschedules_count'] ?? 0) > 0): ?>
                                            <span class="badge rounded-pill bg-warning text-dark ms-2" title="<?php echo e($layoutMeta['pending_reschedules_count']); ?> pending reschedule request(s)"><?php echo e($layoutMeta['pending_reschedules_count']); ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    </div>
                <?php elseif($item['key'] === 'dss'): ?>
                    <div class="sidebar-nav-row <?php echo e($dssActive ? 'active' : ''); ?>">
                        <a href="<?php echo e(route('dss.incidents')); ?>" class="nav-link <?php echo e($dssActive ? 'active' : ''); ?>">
                            <i class="bi <?php echo e($item['icon']); ?>"></i>
                            <span><?php echo e($item['label']); ?></span>
                        </a>
                        <button
                            type="button"
                            class="sidebar-submenu-toggle"
                            data-bs-toggle="collapse"
                            data-bs-target="#dssSubmenu"
                            aria-expanded="<?php echo e($dssActive ? 'true' : 'false'); ?>"
                            aria-controls="dssSubmenu"
                            aria-label="Toggle DSS modules"
                        >
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>

                    <div class="collapse <?php echo e($dssActive ? 'show' : ''); ?>" id="dssSubmenu">
                        <ul class="nav flex-column sidebar-subnav">
                            <li class="nav-item">
                                <a
                                    href="<?php echo e(route('dss.incidents')); ?>"
                                    class="nav-link sidebar-subnav-link <?php echo e($activePage === 'dss-incidents' ? 'active' : ''); ?>"
                                    <?php if($activePage === 'dss-incidents'): ?> aria-current="page" <?php endif; ?>
                                >Incident DSS</a>
                            </li>
                            <li class="nav-item">
                                <a
                                    href="<?php echo e(route('dss.loans')); ?>"
                                    class="nav-link sidebar-subnav-link <?php echo e($activePage === 'dss-loans' ? 'active' : ''); ?>"
                                    <?php if($activePage === 'dss-loans'): ?> aria-current="page" <?php endif; ?>
                                >Loan DSS</a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?php echo e($item['href']); ?>" class="nav-link <?php echo e($activePage === $item['key'] ? 'active' : ''); ?>">
                        <i class="bi <?php echo e($item['icon']); ?>"></i>
                        <span><?php echo e($item['label']); ?></span>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
</aside>

<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

<button
    type="button"
    class="sidebar-reopen-toggle"
    id="sidebarReopenToggle"
    aria-controls="sidebarMenu"
    aria-expanded="false"
    aria-label="Open sidebar"
    title="Open sidebar"
>
    <i class="bi bi-chevron-right" aria-hidden="true"></i>
</button>

<?php $__env->startPush('scripts'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const appShell = document.querySelector('.app-shell');
        const sidebar = document.getElementById('sidebarMenu');
        const collapseToggle = document.getElementById('sidebarCollapseToggle');
        const reopenToggle = document.getElementById('sidebarReopenToggle');
        const mobileToggle = document.getElementById('sidebarMobileToggle');
        const backdrop = document.getElementById('sidebarBackdrop');
        const mainContent = document.querySelector('.main-content');

        if (!appShell || !sidebar) {
            return;
        }

        const mobileQuery = window.matchMedia('(max-width: 991.98px)');
        const toggles = [collapseToggle, reopenToggle, mobileToggle].filter(Boolean);

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

            if (reopenToggle) {
                const label = open ? 'Collapse sidebar' : 'Open sidebar';

                reopenToggle.setAttribute('aria-label', label);
                reopenToggle.setAttribute('title', label);
            }

            if (mobileToggle) {
                const label = open ? 'Close sidebar' : 'Open sidebar';

                mobileToggle.setAttribute('aria-label', label);
                mobileToggle.setAttribute('title', label);
            }

            sidebar.toggleAttribute('inert', !open);

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

            if (firstLink) {
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

            const focusTarget = isMobile() && mobileToggle ? mobileToggle : reopenToggle;

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
<?php $__env->stopPush(); ?>
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/layouts/sidebar.blade.php ENDPATH**/ ?>