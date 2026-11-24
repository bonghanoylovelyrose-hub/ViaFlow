<nav class="navbar navbar-expand-lg topbar px-3 px-lg-4 sticky-top">
    <div class="container-fluid p-0">
        <button class="btn btn-outline-secondary d-lg-none me-2" type="button" id="sidebarMobileToggle" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Open sidebar" title="Open sidebar">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <div>
            <span class="text-uppercase small fw-bold text-secondary"><?php echo e(auth()->user()->isConsumer() ? 'Consumer Portal' : 'System Overview'); ?></span>
            <div class="fw-semibold"><?php echo e(auth()->user()->isConsumer() ? 'Water Billing and Account Access' : 'Water Billing and Loan Management'); ?></div>
        </div>

        <div class="d-flex align-items-center gap-3 ms-auto">
            <div class="d-none d-md-flex align-items-center gap-2 text-secondary">
                <i class="bi bi-calendar3"></i>
                <span><?php echo e($layoutMeta['current_date']); ?></span>
            </div>
            <div class="dropdown">
                <button class="btn btn-light border position-relative" data-bs-toggle="dropdown" type="button">
                    <i class="bi bi-bell"></i>
                    <?php if($layoutMeta['unread_notifications'] > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo e($layoutMeta['unread_notifications']); ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-0 notification-menu">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <strong>Notifications</strong>
                        <?php if($layoutMeta['unread_notifications'] > 0): ?>
                            <form method="POST" action="<?php echo e(route('notifications.read-all')); ?>">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="btn btn-link btn-sm text-decoration-none p-0">Mark all read</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="py-2" style="max-height: 340px; overflow-y: auto;">
                        <?php $__empty_1 = true; $__currentLoopData = $layoutMeta['recent_notifications']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <a href="<?php echo e(route('notifications.open', $notification)); ?>" class="dropdown-item py-2 px-3 notification-item">
                                <div class="d-flex justify-content-between align-items-start gap-3 notification-item-body">
                                    <div class="notification-item-body">
                                        <div class="fw-semibold text-dark notification-item-title"><?php echo e($notification->data['title'] ?? 'Notification'); ?></div>
                                        <div class="small text-secondary notification-item-message"><?php echo e($notification->data['message'] ?? ''); ?></div>
                                    </div>
                                    <?php if(is_null($notification->read_at)): ?>
                                        <span class="badge badge-soft-primary">New</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <div class="px-3 py-3 text-secondary small">No notifications yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="dropdown">
                <button class="btn btn-light border dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" type="button">
                    <span class="rounded-circle bg-info-subtle text-info fw-bold d-inline-flex align-items-center justify-content-center avatar-badge"><?php echo e(strtoupper(substr(auth()->user()->name, 0, 2))); ?></span>
                    <span class="text-start d-none d-md-block">
                        <span class="d-block fw-semibold text-dark"><?php echo e(auth()->user()->name); ?></span>
                        <span class="small text-secondary"><?php echo e(auth()->user()->isConsumer() ? (auth()->user()->consumer?->membership_type ?: 'Not available') : str(auth()->user()->role)->replace('_', ' ')->title()); ?></span>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-2 profile-dropdown-menu">
                    <li><a class="dropdown-item px-3 py-2 rounded-3" href="<?php echo e(route('consumer.profile.show')); ?>">Profile</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <form method="POST" action="<?php echo e(route('logout')); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="dropdown-item px-3 py-2 rounded-3 text-danger">Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/layouts/navbar.blade.php ENDPATH**/ ?>