<?php ($activePage = 'staff'); ?>

<?php $__env->startSection('title', 'Staff Management'); ?>

<?php $__env->startSection('body'); ?>
<div class="app-shell d-lg-flex">
    <?php echo $__env->make('layouts.sidebar', ['activePage' => $activePage], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <main class="main-content flex-grow-1">
        <?php echo $__env->make('layouts.navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="content-wrapper">
            <div class="page-heading">
                <div>
                    <h1>Staff Management</h1>
                    <p>Create and manage admin and staff accounts with the correct access level.</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#staffModal">
                    <i class="bi bi-person-badge me-2"></i>Add Staff Account
                </button>
            </div>

            <div class="panel-card p-4" data-admin-records-root>
                <?php if(session('success')): ?>
                    <div class="alert alert-success d-flex align-items-start gap-2" role="alert" data-admin-freeze>
                        <i class="bi bi-check-circle mt-1"></i>
                        <div><?php echo e(session('success')); ?></div>
                    </div>
                <?php endif; ?>

                <?php if($errors->any()): ?>
                    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert" data-admin-freeze>
                        <i class="bi bi-exclamation-circle mt-1"></i>
                        <div><?php echo e($errors->first()); ?></div>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Access Scope</th></tr></thead>
                        <tbody>
                            <?php $__empty_1 = true; $__currentLoopData = $staffMembers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $staffMember): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo e($staffMember->name); ?></td>
                                    <td><?php echo e($staffMember->email); ?></td>
                                    <td>
                                        <span class="badge <?php echo e($staffMember->role === 'admin' ? 'badge-soft-primary' : 'badge-soft-warning'); ?>">
                                            <?php echo e($staffMember->role === 'admin' ? 'Admin' : 'Staff'); ?>

                                        </span>
                                    </td>
                                    <td class="text-secondary">
                                        <?php echo e($staffMember->role === 'admin' ? 'Full management access' : 'Staff field encoding access'); ?>

                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr><td colspan="4" class="text-center py-5 text-secondary">No staff accounts yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="staffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <h2 class="modal-title fs-5">Create Staff Account</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="<?php echo e(route('staff.store')); ?>" id="staffCreateForm">
                    <?php echo csrf_field(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="name">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo e(old('name')); ?>" placeholder="Juan Dela Cruz" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo e(old('email')); ?>" placeholder="reader@example.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="role">Staff Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="staff" <?php if(old('role', 'staff') === 'staff'): echo 'selected'; endif; ?>>Staff</option>
                                <option value="admin" <?php if(old('role') === 'admin'): echo 'selected'; endif; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="password">Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Create password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="password_confirmation">Confirm Password</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="Repeat password" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="staffCreateForm" class="btn btn-primary">Save Staff Account</button>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <?php if($errors->any()): ?>
        <script>
            const staffModalElement = document.getElementById('staffModal');
            if (staffModalElement) {
                const staffModal = new bootstrap.Modal(staffModalElement);
                staffModal.show();
            }
        </script>
    <?php endif; ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/admin/staff/index.blade.php ENDPATH**/ ?>