<?php
    $activePage = 'announcements';
?>

<?php $__env->startSection('title', 'Announcements'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .announcement-feed-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        background: #fff !important;
    }

    .announcement-filter-form {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-left: auto;
    }

    .announcement-filter-form .form-control,
    .announcement-filter-form .form-select {
        width: auto;
        min-width: 160px;
        background: #fff !important;
    }

    .announcement-filter-form .form-control[name="search"] {
        min-width: 240px;
    }

    .announcement-filter-form .btn {
        background: #fff !important;
    }

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

    @media (max-width: 767.98px) {
        .announcement-feed-toolbar {
            align-items: stretch;
        }

        .announcement-filter-form {
            width: 100%;
            margin-left: 0;
            justify-content: stretch;
        }

        .announcement-filter-form .form-control,
        .announcement-filter-form .form-select,
        .announcement-filter-form .btn {
            width: 100%;
            min-width: 0;
        }
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('body'); ?>
<div class="app-shell d-lg-flex">
    <?php echo $__env->make('layouts.sidebar', ['activePage' => $activePage], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <main class="main-content flex-grow-1">
        <?php echo $__env->make('layouts.navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="content-wrapper">
            <div class="page-heading">
                <div>
                    <h1>Announcements</h1>
                    <p>Publish service advisories, billing reminders, and internal updates from one shared board.</p>
                </div>
                <?php if($canManageAnnouncements): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#announcementCreateModal">
                        <i class="bi bi-megaphone me-2"></i>Post Announcement
                    </button>
                <?php endif; ?>
            </div>

            <?php if(session('success')): ?>
                <div class="alert alert-success d-flex align-items-start gap-2 panel-card border-0 mb-4" role="alert">
                    <i class="bi bi-check-circle mt-1"></i>
                    <div><?php echo e(session('success')); ?></div>
                </div>
            <?php endif; ?>

            <?php if($errors->any()): ?>
                <div class="alert alert-danger d-flex align-items-start gap-2 panel-card border-0 mb-4" role="alert">
                    <i class="bi bi-exclamation-circle mt-1"></i>
                    <div><?php echo e($errors->first()); ?></div>
                </div>
            <?php endif; ?>

            <?php if (! ($announcementsTableExists)): ?>
                <div class="panel-card p-4 mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <span class="feature-icon"><i class="bi bi-database-exclamation"></i></span>
                        <div>
                            <h2 class="h5 fw-bold mb-2">Announcements table not active yet</h2>
                            <p class="text-secondary mb-0">Run <code>php artisan migrate</code> to activate the announcements module and start posting updates.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="dashboard-card p-4 h-100">
                        <div class="text-secondary small">Visible Announcements</div>
                        <div class="fw-bold fs-2 mt-2"><?php echo e(number_format($stats['total'])); ?></div>
                        <span class="badge badge-soft-primary mt-2">Current feed</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card p-4 h-100">
                        <div class="text-secondary small">Pinned Posts</div>
                        <div class="fw-bold fs-2 mt-2"><?php echo e(number_format($stats['pinned'])); ?></div>
                        <span class="badge badge-soft-warning mt-2">Priority updates</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card p-4 h-100">
                        <div class="text-secondary small">Service Advisories</div>
                        <div class="fw-bold fs-2 mt-2"><?php echo e(number_format($stats['service_advisories'])); ?></div>
                        <span class="badge badge-soft-info mt-2">Operations-heavy notices</span>
                    </div>
                </div>
            </div>

            <div class="panel-card p-4 mb-4" data-admin-records-root data-admin-freeze-section>
                <div class="announcement-feed-toolbar mb-3" data-admin-freeze>
                    <div class="section-title mb-0">Announcement Feed</div>
                    <form method="GET" action="<?php echo e(route('announcements.index')); ?>" class="announcement-filter-form" data-auto-filter data-auto-filter-debounce="450">
                        <input type="text" class="form-control form-control-sm" id="announcement_filter_search" name="search" value="<?php echo e($filters['search']); ?>" placeholder="Search title or details">
                        <select class="form-select form-select-sm" id="announcement_filter_category" name="category">
                            <option value="">All categories</option>
                            <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($category); ?>" <?php if($filters['category'] === $category): echo 'selected'; endif; ?>><?php echo e($category); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <select class="form-select form-select-sm" id="announcement_filter_audience" name="audience">
                            <option value="">All audiences</option>
                            <?php $__currentLoopData = $audiences; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $audience): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($audience); ?>" <?php if($filters['audience'] === $audience): echo 'selected'; endif; ?>><?php echo e($audience); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <noscript>
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Apply filters</button>
                        </noscript>
                        <a href="<?php echo e(route('announcements.index')); ?>" class="btn btn-outline-secondary btn-sm">Clear</a>
                    </form>
                </div>

                <div class="row g-4">
                    <?php $__empty_1 = true; $__currentLoopData = $announcements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $announcement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                            $badgeClass = match ($announcement->category) {
                                'Service Advisory' => 'badge-soft-danger',
                                'Billing Notice' => 'badge-soft-warning',
                                'Loan Program' => 'badge-soft-primary',
                                'Operations' => 'badge-soft-info',
                                default => 'badge-soft-success',
                            };
                        ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="announcement-card p-4 h-100 d-flex flex-column">
                                <div class="announcement-header d-flex justify-content-between align-items-start gap-3 mb-3">
                                    <div class="d-flex flex-wrap gap-2 announcement-meta">
                                        <span class="badge <?php echo e($badgeClass); ?>"><?php echo e($announcement->category); ?></span>
                                        <span class="badge badge-soft-primary"><?php echo e($announcement->audience); ?></span>
                                        <?php if($announcement->is_pinned): ?>
                                            <span class="badge badge-soft-warning">Pinned</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="small text-secondary text-end announcement-date announcement-safe-wrap"><?php echo e(optional($announcement->published_at)->format('M d, Y')); ?></span>
                                </div>
                                <h2 class="h5 fw-bold announcement-safe-wrap"><?php echo e($announcement->title); ?></h2>
                                <p class="text-secondary mb-3 flex-grow-1 announcement-safe-wrap"><?php echo e($announcement->body); ?></p>
                                <div class="announcement-footer d-flex justify-content-between align-items-center gap-3 mt-auto">
                                    <div class="small text-secondary announcement-safe-wrap">
                                        Posted by <?php echo e($announcement->author?->name ?? 'System Admin'); ?>

                                    </div>
                                    <?php if($canManageAnnouncements): ?>
                                        <div class="d-inline-flex gap-2">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-light border"
                                                data-bs-toggle="modal"
                                                data-bs-target="#announcementEditModal"
                                                data-announcement-id="<?php echo e($announcement->id); ?>"
                                                data-announcement-title="<?php echo e($announcement->title); ?>"
                                                data-announcement-body="<?php echo e($announcement->body); ?>"
                                                data-announcement-category="<?php echo e($announcement->category); ?>"
                                                data-announcement-audience="<?php echo e($announcement->audience); ?>"
                                                data-announcement-pinned="<?php echo e($announcement->is_pinned ? '1' : '0'); ?>"
                                            >
                                                Edit
                                            </button>
                                            <form method="POST" action="<?php echo e(route('announcements.destroy', $announcement)); ?>" onsubmit="return confirm('Delete this announcement?');">
                                                <?php echo csrf_field(); ?>
                                                <?php echo method_field('DELETE'); ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="col-12">
                            <div class="text-center py-5 text-secondary">
                                No announcements found for the selected filters.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-4">
                    <p class="text-secondary mb-0">
                        <?php if($announcements->total() > 0): ?>
                            Showing <?php echo e($announcements->firstItem()); ?> to <?php echo e($announcements->lastItem()); ?> of <?php echo e($announcements->total()); ?> announcements
                        <?php else: ?>
                            No announcements available
                        <?php endif; ?>
                    </p>
                    <?php echo e($announcements->links()); ?>

                </div>
            </div>
        </div>
    </main>
</div>

<?php if($canManageAnnouncements): ?>
    <div class="modal fade" id="announcementCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Post Announcement</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="<?php echo e(route('announcements.store')); ?>">
                        <?php echo csrf_field(); ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="announcement_title">Title</label>
                                <input type="text" class="form-control" id="announcement_title" name="title" value="<?php echo e(old('title')); ?>" maxlength="255" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="announcement_category">Category</label>
                                <select class="form-select" id="announcement_category" name="category" required>
                                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($category); ?>" <?php if(old('category') === $category): echo 'selected'; endif; ?>><?php echo e($category); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="announcement_audience">Audience</label>
                                <select class="form-select" id="announcement_audience" name="audience" required>
                                    <?php $__currentLoopData = $audiences; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $audience): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($audience); ?>" <?php if(old('audience', 'Everyone') === $audience): echo 'selected'; endif; ?>><?php echo e($audience); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="announcement_body">Details</label>
                                <textarea class="form-control" id="announcement_body" name="body" rows="5" maxlength="2000" required><?php echo e(old('body')); ?></textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="announcement_is_pinned" name="is_pinned" <?php if(old('is_pinned')): echo 'checked'; endif; ?>>
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
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="announcement_edit_title">Title</label>
                                <input type="text" class="form-control" id="announcement_edit_title" name="title" maxlength="255" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="announcement_edit_category">Category</label>
                                <select class="form-select" id="announcement_edit_category" name="category" required>
                                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($category); ?>"><?php echo e($category); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="announcement_edit_audience">Audience</label>
                                <select class="form-select" id="announcement_edit_audience" name="audience" required>
                                    <?php $__currentLoopData = $audiences; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $audience): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($audience); ?>"><?php echo e($audience); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<?php if($canManageAnnouncements): ?>
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
<?php endif; ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/admin/announcements/index.blade.php ENDPATH**/ ?>