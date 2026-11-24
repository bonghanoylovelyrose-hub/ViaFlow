<?php
    $activePage = 'consumers';
    $consumerStatusService = app(\App\Services\ConsumerStatusService::class);
?>

<?php $__env->startSection('title', 'Consumers'); ?>

<?php $__env->startPush('styles'); ?>
    <style>
        .consumer-section-controls { display: flow-root; }

        #disconnection-records .table-danger > td {
            --bs-table-bg: #f8d7da;
            --bs-table-color: #842029;
            background-color: #f8d7da !important;
            color: #842029 !important;
        }

        #disconnection-records .table-danger .bg-danger {
            background-color: #b02a37 !important;
            color: #fff !important;
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
                    <h1>Consumer Management</h1>
                    <p><?php echo e($filters['account_category'] === 'active' ? 'Manage disconnection notices and active accounts in separate, independently filtered tables.' : 'Browse, search, and maintain consumer account records with a simple table workflow.'); ?></p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#consumerModal">
                        <i class="bi bi-person-plus me-2"></i>Add Consumer
                    </button>
                </div>
            </div>

            <div class="panel-card p-4" data-admin-records-root data-admin-page-scroll data-admin-freeze-section <?php if($filters['account_category'] === 'active'): ?> data-admin-section-scroll <?php endif; ?>>
                <?php if(session('success')): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2" role="alert" data-admin-freeze>
                        <i class="bi bi-check-circle"></i>
                        <div><span class="fw-bold">Success:</span> <?php echo e(session('success')); ?></div>
                    </div>
                <?php endif; ?>

                <?php if(session('email_error')): ?>
                    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert" data-admin-freeze>
                        <i class="bi bi-envelope-exclamation mt-1"></i>
                        <div><span class="fw-bold">Email delivery failed:</span> <?php echo e(session('email_error')); ?></div>
                    </div>
                <?php endif; ?>

                <?php if($errors->any()): ?>
                    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert" data-admin-freeze>
                        <i class="bi bi-exclamation-circle mt-1"></i>
                        <div><?php echo e($errors->first()); ?></div>
                    </div>
                <?php endif; ?>

                
                <?php if($filters['account_category'] === 'active' && $disconnectionNotices): ?>
                    <?php
                        $hasNoticeFilters = $filters['notice_search'] !== '' || $filters['notice_status'] !== '' || $filters['notice_membership'] !== '';
                    ?>
                    <div class="card mb-3" id="active-disconnection-notices" data-admin-freeze-section>
                        <div class="consumer-section-controls">
                        <div class="card-header bg-light d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h2 class="fw-bold fs-5 mb-1"><i class="bi bi-journal-text me-2"></i>Disconnection Notice</h2>
                                <div class="small text-secondary">Accounts with notice history. Red rows have an unpaid balance after the three-day grace period.</div>
                            </div>
                            <span class="badge bg-secondary">
                                <?php echo e(number_format($disconnectionNoticeCount)); ?> account<?php echo e($disconnectionNoticeCount === 1 ? '' : 's'); ?>

                            </span>
                        </div>
                        <div class="consumer-section-controls px-4 pt-4">
                            <form method="GET" id="notice-filters" action="<?php echo e(route('consumers.index')); ?>" class="row g-2 mb-3 align-items-end" data-auto-filter data-auto-filter-debounce="450">
                                <input type="hidden" name="account_category" value="active">
                                <?php if(request()->has('page')): ?>
                                    <input type="hidden" name="page" value="<?php echo e($consumers->currentPage()); ?>">
                                <?php endif; ?>
                                <?php if($filters['search'] !== ''): ?>
                                    <input type="hidden" name="search" value="<?php echo e($filters['search']); ?>">
                                <?php endif; ?>
                                <?php if($filters['membership'] !== ''): ?>
                                    <input type="hidden" name="membership" value="<?php echo e($filters['membership']); ?>">
                                <?php endif; ?>
                                <?php if($filters['membership_verification'] !== ''): ?>
                                    <input type="hidden" name="membership_verification" value="<?php echo e($filters['membership_verification']); ?>">
                                <?php endif; ?>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-secondary" for="notice_search">Search</label>
                                    <input type="text" class="form-control" id="notice_search" name="notice_search" value="<?php echo e($filters['notice_search']); ?>" placeholder="Search by name, account, or meter number">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small text-secondary" for="notice_status">Status</label>
                                    <select class="form-select" id="notice_status" name="notice_status">
                                        <?php $__currentLoopData = $noticeStatusFilters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $statusValue => $statusLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($statusValue); ?>" <?php if($filters['notice_status'] === $statusValue): echo 'selected'; endif; ?>><?php echo e($statusLabel); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small text-secondary" for="notice_membership">Membership</label>
                                    <select class="form-select" id="notice_membership" name="notice_membership">
                                        <?php $__currentLoopData = $membershipFilters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $membershipValue => $membershipLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($membershipValue); ?>" <?php if($filters['notice_membership'] === $membershipValue): echo 'selected'; endif; ?>><?php echo e($membershipValue === '' ? 'Disconnection Records' : $membershipLabel); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex gap-2">
                                    <noscript>
                                        <button type="submit" class="btn btn-primary w-100">Apply filters</button>
                                    </noscript>
                                    <a href="<?php echo e(route('consumers.index', array_merge(request()->only(['search', 'membership', 'membership_verification', 'page']), ['account_category' => 'active']))); ?>" class="btn btn-light border w-100">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Clear
                                    </a>
                                </div>
                            </form>
                        </div>
                        </div>
                        <div class="card-body p-4 pt-0">

                                <div class="table-responsive">
                                    <table class="table align-middle mb-0" id="disconnection-records">
                                        <thead>
                                            <tr>
                                                <th>Account Number</th>
                                                <th>Consumer Name</th>
                                                <th>Meter Number</th>
                                                <th>Membership</th>
                                                <th>Outstanding Balance</th>
                                                <th>Payment Status</th>
                                                <th>Disconnection Notice Date</th>
                                                <th>Grace Period / Payment Deadline</th>
                                                <th>Status / History</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $__empty_1 = true; $__currentLoopData = $disconnectionNotices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                                <?php
                                                    $noticeConsumer = $notice['consumer'];
                                                    $noticePayload = base64_encode(json_encode([
                                                        'id' => $noticeConsumer->id,
                                                        'application_reference' => $noticeConsumer->application_reference,
                                                        'full_name' => $noticeConsumer->full_name,
                                                        'email' => $noticeConsumer->email,
                                                        'account_number' => $noticeConsumer->account_number,
                                                        'meter_number' => $noticeConsumer->meter_number,
                                                        'meter_history' => [],
                                                        'membership_type' => $noticeConsumer->membership_type,
                                                        'requested_membership_type' => $noticeConsumer->requested_membership_type,
                                                        'membership_verification_status' => $noticeConsumer->membership_verification_status,
                                                        'membership_verification_notes' => $noticeConsumer->membership_verification_notes,
                                                        'status' => $noticeConsumer->status,
                                                        'status_display' => $consumerStatusService->present($noticeConsumer),
                                                        'property_type' => $noticeConsumer->property_type,
                                                        'ownership_status' => $noticeConsumer->ownership_status,
                                                        'valid_id_presented' => $noticeConsumer->valid_id_presented,
                                                        'service_address' => $noticeConsumer->service_address,
                                                        'address_block' => $noticeConsumer->address_block,
                                                        'address_street' => $noticeConsumer->address_street,
                                                        'contact_number' => $noticeConsumer->contact_number,
                                                        'membership_renewal_date' => optional($noticeConsumer->membership_renewal_date)->toDateString(),
                                                        'reconnection_date' => optional($noticeConsumer->reconnection_date)->toDateString(),
                                                        'installation_date' => optional($noticeConsumer->installation_date)->toDateString(),
                                                        'housing_rights_documented' => (bool) $noticeConsumer->housing_rights_documented,
                                                        'resident_of_community' => (bool) $noticeConsumer->resident_of_community,
                                                        'association_member' => (bool) $noticeConsumer->association_member,
                                                        'association_registered' => (bool) $noticeConsumer->association_registered,
                                                        'housing_loan_contract_submitted' => (bool) $noticeConsumer->housing_loan_contract_submitted,
                                                        'valid_id_submitted' => (bool) $noticeConsumer->valid_id_submitted,
                                                        'proof_of_residency_submitted' => (bool) $noticeConsumer->proof_of_residency_submitted,
                                                        'other_document_details' => $noticeConsumer->other_document_details,
                                                    ]));
                                                ?>
                                                <tr class="<?php echo \Illuminate\Support\Arr::toCssClasses(['table-danger' => $notice['can_confirm_disconnection']]); ?>" data-record-status="<?php echo e($notice['record_status']); ?>">
                                                    <td><?php echo e($noticeConsumer->account_number ?: 'Pending assignment'); ?></td>
                                                    <td>
                                                        <div class="fw-semibold"><?php echo e($noticeConsumer->full_name); ?></div>
                                                        <div class="small text-secondary"><?php echo e($noticeConsumer->application_reference); ?></div>
                                                    </td>
                                                    <td><?php echo e($noticeConsumer->meter_number ?: 'Pending assignment'); ?></td>
                                                    <td>
                                                        <div class="fw-semibold"><?php echo e($noticeConsumer->membership_type); ?></div>
                                                        <?php if($noticeConsumer->hasPendingMembershipVerification()): ?>
                                                            <div class="small text-secondary">Verification pending</div>
                                                        <?php elseif($noticeConsumer->hasVerifiedMembership()): ?>
                                                            <div class="small text-secondary">Verified member</div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="fw-semibold">P <?php echo e(number_format($notice['total_outstanding'], 2)); ?></span>
                                                    </td>
                                                    <td><?php echo e($notice['total_outstanding'] > 0 ? 'Unpaid' : 'Paid'); ?></td>
                                                    <td>
                                                        <div><?php echo e(optional($notice['notice_date'])->format('M d, Y') ?: '—'); ?></div>
                                                        <?php if($notice['notice_billing_period']): ?>
                                                            <div class="small text-secondary">Billing period <?php echo e($notice['notice_billing_period']->format('M d, Y')); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if($notice['payment_deadline']): ?>
                                                            <div class="small text-secondary"><?php echo e(\App\Services\ConsumerBalanceService::PAYMENT_GRACE_DAYS); ?> full days from notice</div>
                                                            <div><?php echo e($notice['payment_deadline']->format('M d, Y g:i A')); ?></div>
                                                            <?php if($notice['can_confirm_disconnection']): ?>
                                                                <span class="badge bg-danger mt-1">Three-day window elapsed</span>
                                                            <?php elseif($notice['has_active_notice']): ?>
                                                                <span class="badge bg-warning text-dark mt-1">Three-day window open</span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-secondary">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="<?php echo \Illuminate\Support\Arr::toCssClasses(['badge', 'bg-danger' => $notice['can_confirm_disconnection'], 'bg-success' => $notice['record_status'] === 'Paid', 'bg-secondary' => ! $notice['can_confirm_disconnection'] && $notice['record_status'] !== 'Paid']); ?>"><?php echo e($notice['record_status']); ?></span>
                                                        <div class="small text-secondary mt-1">Account: <?php echo e($noticeConsumer->status); ?></div>
                                                        <?php if($noticeConsumer->installation_date): ?>
                                                            <?php if (isset($component)) { $__componentOriginal6a8924d58300970ae01ffe6c17a9cc3d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6a8924d58300970ae01ffe6c17a9cc3d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.reschedule-usage','data' => ['usage' => app(\App\Services\InstallationRescheduleService::class)->usage($noticeConsumer)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('reschedule-usage'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['usage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(app(\App\Services\InstallationRescheduleService::class)->usage($noticeConsumer))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6a8924d58300970ae01ffe6c17a9cc3d)): ?>
<?php $attributes = $__attributesOriginal6a8924d58300970ae01ffe6c17a9cc3d; ?>
<?php unset($__attributesOriginal6a8924d58300970ae01ffe6c17a9cc3d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6a8924d58300970ae01ffe6c17a9cc3d)): ?>
<?php $component = $__componentOriginal6a8924d58300970ae01ffe6c17a9cc3d; ?>
<?php unset($__componentOriginal6a8924d58300970ae01ffe6c17a9cc3d); ?>
<?php endif; ?>
                                                        <?php endif; ?>
                                                        <?php if($notice['has_active_notice']): ?>
                                                            <div class="small text-secondary mt-1">Full payment required before disconnection.</div>
                                                        <?php endif; ?>
                                                        <?php if($notice['history']->isNotEmpty()): ?>
                                                            <details class="small mt-2">
                                                                <summary>Notice history (<?php echo e($notice['history']->count()); ?>)</summary>
                                                                <ul class="ps-3 mt-2 mb-0">
                                                                    <?php $__currentLoopData = $notice['history']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $history): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                        <li class="mb-2">
                                                                            <strong><?php echo e($history->status); ?></strong> &middot; Issued <?php echo e($history->issued_at?->format('M d, Y g:i A')); ?>

                                                                            <div>Deadline: <?php echo e($history->deadline_at?->format('M d, Y g:i A') ?: 'Unavailable'); ?></div>
                                                                            <?php if($history->settled_at): ?>
                                                                                <div>Paid: <?php echo e($history->settled_at->format('M d, Y g:i A')); ?></div>
                                                                            <?php endif; ?>
                                                                            <?php if($history->disconnected_at): ?>
                                                                                <div>Disconnected: <?php echo e($history->disconnected_at->format('M d, Y g:i A')); ?> &middot; Confirmed by <?php echo e($history->confirmedBy?->name ?: 'Admin'); ?></div>
                                                                            <?php endif; ?>
                                                                        </li>
                                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                                </ul>
                                                            </details>
                                                        <?php endif; ?>
                                                        <?php if($noticeConsumer->reconnections->isNotEmpty()): ?>
                                                            <details class="small mt-2">
                                                                <summary>Reconnection history (<?php echo e($noticeConsumer->reconnections->count()); ?>)</summary>
                                                                <ul class="ps-3 mt-2 mb-0">
                                                                    <?php $__currentLoopData = $noticeConsumer->reconnections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reconnection): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                        <li>Activated <?php echo e($reconnection->activated_at->format('M d, Y g:i A')); ?>. Reconnection: <?php echo e($reconnection->reconnection_date->format('M d, Y')); ?>. Installation: <?php echo e($reconnection->installation_scheduled_at->format('M d, Y g:i A')); ?>.</li>
                                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                                </ul>
                                                            </details>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <?php if($notice['can_confirm_disconnection']): ?>
                                                            <form
                                                                method="POST"
                                                                action="<?php echo e(route('consumers.confirm-disconnection', $noticeConsumer)); ?>"
                                                                class="d-inline"
                                                                onsubmit="return confirm('Confirm that the physical disconnection has already been carried out for <?php echo e($noticeConsumer->full_name); ?>? The account will move to Inactive Accounts.');"
                                                            >
                                                                <?php echo csrf_field(); ?>
                                                                <label class="visually-hidden" for="disconnection-reason-<?php echo e($noticeConsumer->id); ?>">Disconnection reason</label>
                                                                <input type="text" name="disconnection_reason" id="disconnection-reason-<?php echo e($noticeConsumer->id); ?>" maxlength="1000" class="form-control form-control-sm mb-2" placeholder="Disconnection reason (optional)">
                                                                <button type="submit" class="btn btn-sm btn-danger me-2">
                                                                    <i class="bi bi-plug me-1"></i>Confirm Disconnection
                                                                </button>
                                                            </form>
                                                        <?php elseif($notice['has_active_notice']): ?>
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-outline-secondary me-2"
                                                                disabled
                                                                title="Available only after the three-day payment window closes."
                                                            >
                                                                <i class="bi bi-plug me-1"></i>Confirm Disconnection
                                                            </button>
                                                        <?php endif; ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#consumerEditModal"
                                                            data-consumer='<?php echo e($noticePayload); ?>'
                                                            title="Open this consumer's account details"
                                                        >
                                                            <i class="bi bi-pencil-square me-1"></i>Edit
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                                <tr><td colspan="10" class="text-center text-secondary py-4"><?php echo e($hasNoticeFilters ? 'No disconnection records match the current filters.' : 'No disconnection records found.'); ?></td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                            <?php if($disconnectionNotices->total() > 0): ?>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3">
                                    <p class="text-secondary mb-0">
                                        Showing <?php echo e($disconnectionNotices->firstItem()); ?> to <?php echo e($disconnectionNotices->lastItem()); ?> of <?php echo e($disconnectionNotices->total()); ?> account<?php echo e($disconnectionNotices->total() === 1 ? '' : 's'); ?>

                                    </p>
                                    <?php echo e($disconnectionNotices->links()); ?>

                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if($filters['account_category'] === 'active'): ?>
                <section id="active-accounts" aria-labelledby="active-accounts-title" data-admin-freeze-section>
                <div class="consumer-section-controls" data-admin-freeze>
                    <h2 class="fs-5 fw-bold" id="active-accounts-title">Active Accounts</h2>
                    <p class="small text-secondary">Active accounts without disconnection notice records.</p>
                <?php endif; ?>
                <?php
                    $clearConsumerFilters = array_filter([
                        'account_category' => $filters['account_category'],
                    ], fn ($value) => $value !== null && $value !== '');
                    if ($filters['account_category'] === 'active') {
                        $clearConsumerFilters = array_merge($clearConsumerFilters, request()->only(['notice_search', 'notice_status', 'notice_membership', 'notice_page']));
                    }
                ?>

                <form method="GET" id="account-filters" action="<?php echo e(route('consumers.index')); ?>" class="row g-2 mb-3 align-items-end" data-admin-freeze data-auto-filter data-auto-filter-debounce="450">
                    <?php if($filters['account_category'] === 'active'): ?>
                        <?php $__currentLoopData = request()->only(['notice_search', 'notice_status', 'notice_membership', 'notice_page']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $noticeFilter => $noticeValue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <input type="hidden" name="<?php echo e($noticeFilter); ?>" value="<?php echo e($noticeValue); ?>">
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php endif; ?>
                    <?php if($filters['account_category'] !== ''): ?>
                        <input type="hidden" name="account_category" value="<?php echo e($filters['account_category']); ?>">
                    <?php endif; ?>
                    <?php if(($filters['pending_type'] ?? '') !== '' && $filters['account_category'] === 'pending'): ?>
                        <input type="hidden" name="pending_type" value="<?php echo e($filters['pending_type']); ?>">
                    <?php endif; ?>
                    <?php if($filters['membership_verification'] !== ''): ?>
                        <input type="hidden" name="membership_verification" value="<?php echo e($filters['membership_verification']); ?>">
                    <?php endif; ?>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary" for="search">Search</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?php echo e($filters['search']); ?>" placeholder="Search by name, account, or meter number">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small text-secondary" for="membership">Membership</label>
                        <select class="form-select" id="membership" name="membership">
                            <?php $__currentLoopData = $membershipFilters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $membershipValue => $membershipLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($membershipValue); ?>" <?php if($filters['membership'] === $membershipValue): echo 'selected'; endif; ?>><?php echo e($filters['account_category'] === 'active' && $membershipValue === '' ? 'All Memberships' : $membershipLabel); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <noscript>
                            <button type="submit" class="btn btn-primary w-100">Apply filters</button>
                        </noscript>
                        <a href="<?php echo e(route('consumers.index', $clearConsumerFilters)); ?>" class="btn btn-light border w-100">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Clear
                        </a>
                    </div>
                </form>

                <?php if($filters['account_category'] === 'pending'): ?>
                    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap" data-admin-freeze>
                        <span class="small fw-bold text-secondary text-uppercase me-1">Pending Type:</span>
                        <a href="<?php echo e(route('consumers.index', array_filter(array_merge($filters, ['pending_type' => 'all'])))); ?>"
                           class="btn btn-sm <?php echo e(($pendingType ?? 'all') === 'all' ? 'btn-primary' : 'btn-outline-secondary'); ?>">
                            All Pending (<?php echo e(($pendingApplicationsCount ?? 0) + ($pendingReschedulesCount ?? 0)); ?>)
                        </a>
                        <a href="<?php echo e(route('consumers.index', array_filter(array_merge($filters, ['pending_type' => 'applications'])))); ?>"
                           class="btn btn-sm <?php echo e(($pendingType ?? '') === 'applications' ? 'btn-primary' : 'btn-outline-secondary'); ?>">
                            Connection Applications (<?php echo e($pendingApplicationsCount ?? 0); ?>)
                        </a>
                        <a href="<?php echo e(route('consumers.index', array_filter(array_merge($filters, ['pending_type' => 'reschedules'])))); ?>"
                           class="btn btn-sm <?php echo e(($pendingType ?? '') === 'reschedules' ? 'btn-primary' : 'btn-outline-secondary'); ?>">
                            <i class="bi bi-calendar-event me-1"></i>Reschedule Requests (<?php echo e($pendingReschedulesCount ?? 0); ?>)
                        </a>
                    </div>
                <?php endif; ?>

                <?php if($filters['membership_verification'] === 'pending'): ?>
                    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 mb-3" role="status" <?php if($filters['account_category'] !== 'active'): ?> data-admin-freeze <?php endif; ?>>
                        <span><i class="bi bi-person-check me-2"></i>Showing pending membership verifications.</span>
                        <a
                            href="<?php echo e(route('consumers.index', array_merge(request()->only(['notice_search', 'notice_status', 'notice_membership', 'notice_page']), array_filter(['account_category' => $filters['account_category'], 'membership' => 'member'], fn ($value) => $value !== null && $value !== '')))); ?>"
                            class="small fw-semibold"
                        >View all members</a>
                    </div>
                <?php endif; ?>

                <?php if($filters['account_category'] === 'active'): ?>
                </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table align-middle" id="consumer-accounts-table">
                        <thead>
                            <?php if($filters['account_category'] === 'inactive'): ?>
                                <tr><th>Consumer Name</th><th>Account Number</th><th>Meter Number</th><th>Membership</th><th>Standing</th><th>Account Status</th><th>Connection Status</th><th>Outstanding Balance</th><th class="text-end">Actions</th></tr>
                            <?php else: ?>
                                <tr><th>Application</th><th>Account No</th><th>Membership</th><th>Standing</th><th>Status</th><th class="text-end">Actions</th></tr>
                            <?php endif; ?>
                        </thead>
                        <tbody>
                            <?php $__empty_1 = true; $__currentLoopData = $consumers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $consumer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <?php
                                    $normalizedStatus = str($consumer->status)->trim()->lower()->toString();
                                    $finalStatuses = ['active', 'rejected', 'disconnected'];
                                    $pendingStatuses = ['pending', 'pending verification', 'for review', 'for reconnection'];
                                    $canVerifyConsumer = blank($consumer->user_id)
                                        && blank($consumer->meter_number)
                                        && (
                                            in_array($normalizedStatus, $pendingStatuses, true)
                                            || (blank($consumer->account_number) && ! in_array($normalizedStatus, $finalStatuses, true))
                                        );
                                    $consumerPayload = base64_encode(json_encode([
                                        'id' => $consumer->id,
                                        'application_reference' => $consumer->application_reference,
                                        'full_name' => $consumer->full_name,
                                        'email' => $consumer->email,
                                        'account_number' => $consumer->account_number,
                                        'meter_number' => $consumer->meter_number,
                                        'meter_history' => $meterHistory[$consumer->id] ?? [],
                                        'membership_type' => $consumer->membership_type,
                                        'requested_membership_type' => $consumer->requested_membership_type,
                                        'membership_verification_status' => $consumer->membership_verification_status,
                                        'membership_verification_notes' => $consumer->membership_verification_notes,
                                        'connection_request_status' => optional($newConnectionRequests->get($consumer->id))->status,
                                        'connection_request_admin_notes' => optional($newConnectionRequests->get($consumer->id))->admin_notes,
                                        'status' => $consumer->status,
                                        'status_display' => $consumerStatusService->present($consumer),
                                        'property_type' => $consumer->property_type,
                                        'ownership_status' => $consumer->ownership_status,
                                        'valid_id_presented' => $consumer->valid_id_presented,
                                        'service_address' => $consumer->service_address,
                                        'address_block' => $consumer->address_block,
                                        'address_street' => $consumer->address_street,
                                        'contact_number' => $consumer->contact_number,
                                        'membership_renewal_date' => optional($consumer->membership_renewal_date)->toDateString(),
                                        'reconnection_date' => optional($consumer->reconnection_date)->toDateString(),
                                        'installation_date' => optional($consumer->installation_date)->toDateString(),
                                        'housing_rights_documented' => (bool) $consumer->housing_rights_documented,
                                        'resident_of_community' => (bool) $consumer->resident_of_community,
                                        'association_member' => (bool) $consumer->association_member,
                                        'association_registered' => (bool) $consumer->association_registered,
                                        'housing_loan_contract_submitted' => (bool) $consumer->housing_loan_contract_submitted,
                                        'valid_id_submitted' => (bool) $consumer->valid_id_submitted,
                                        'proof_of_residency_submitted' => (bool) $consumer->proof_of_residency_submitted,
                                        'membership_document_type' => $consumer->membership_document_type,
                                        'other_document_details' => $consumer->other_document_details,
                                        'rejection_reason' => $consumer->rejection_reason,
                                        'rejection_fields' => $correctionFieldsByConsumer[$consumer->id] ?? [],
                                        'rejected_at' => optional($consumer->rejected_at)->format('F d, Y g:i A'),
                                        'resubmitted_at' => optional($consumer->resubmitted_at)->format('F d, Y g:i A'),
                                        'valid_id_url' => $consumer->valid_id_path ? route('uploads.serve', ['path' => $consumer->valid_id_path]) : null,
                                        'proof_of_residency_url' => $consumer->proof_of_residency_path ? route('uploads.serve', ['path' => $consumer->proof_of_residency_path]) : null,
                                        'membership_document_urls' => collect($consumer->membership_document_paths ?? [])
                                            ->map(fn ($path) => route('uploads.serve', ['path' => $path]))
                                            ->values()
                                            ->all(),
                                        'other_document_url' => $consumer->other_document_path ? route('uploads.serve', ['path' => $consumer->other_document_path]) : null,
                                    ]));
                                ?>
                                <tr>
                                    <?php if($filters['account_category'] === 'inactive'): ?>
                                    <td><div class="fw-semibold"><?php echo e($consumer->full_name); ?></div></td>
                                    <td><?php echo e($consumer->account_number ?: 'Pending assignment'); ?></td>
                                    <td><?php echo e($consumer->meter_number ?: 'Pending assignment'); ?></td>
                                    <td><?php echo e($consumer->membership_type); ?></td>
                                    <td><?php echo e(($consumerRag[$consumer->id] ?? ['label' => 'Unknown'])['label']); ?></td>
                                    <td><?php echo e($consumer->status); ?></td>
                                    <td>Disconnected</td>
                                    <td class="fw-semibold">&#8369;<?php echo e(number_format($reconnectionEligibility[$consumer->id]['outstanding'], 2)); ?></td>
                                    <?php else: ?>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($consumer->full_name); ?></div>
                                        <div class="small text-primary fw-semibold"><?php echo e($consumer->application_reference); ?></div>
                                        <?php if($consumer->service_address || $consumer->contact_number): ?>
                                            <div class="small text-secondary">
                                                <?php echo e($consumer->service_address ?: 'No address provided'); ?>

                                                <?php if($consumer->contact_number): ?>
                                                    <span class="mx-1">•</span><?php echo e($consumer->contact_number); ?>

                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e($consumer->account_number ?: 'Pending assignment'); ?></td>
                                    <td>
                                        <div class="fw-semibold">
                                            <?php echo e($consumer->membership_type); ?>

                                            <?php if($consumer->hasPendingMembershipVerification()): ?>
                                                <span class="badge bg-warning text-dark ms-1">Verification pending</span>
                                            <?php elseif($consumer->hasVerifiedMembership()): ?>
                                                <span class="badge bg-success ms-1">Verified member</span>
                                            <?php elseif($consumer->membership_verification_status === \App\Models\Consumer::MEMBERSHIP_VERIFICATION_REJECTED): ?>
                                                <span class="badge bg-danger ms-1">Claim rejected</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if($consumer->hasPendingMembershipVerification()): ?>
                                            <div class="small text-secondary">Existing VIOHOA member &middot; awaiting admin verification</div>
                                        <?php elseif($consumer->isMemberApplicant()
                                            && $consumer->membership_verification_status === \App\Models\Consumer::MEMBERSHIP_VERIFICATION_REJECTED): ?>
                                            <div class="small text-secondary">Declared existing VIOHOA member &middot; not verified</div>
                                        <?php endif; ?>
                                        <div class="small text-secondary">Meter <?php echo e($consumer->meter_number ?: 'Pending assignment'); ?></div>
                                        <?php if($consumer->email): ?>
                                            <div class="small text-secondary"><?php echo e($consumer->email); ?></div>
                                        <?php endif; ?>
                                        <div class="small text-secondary">
                                            Association: <?php echo e($consumer->association_member ? 'Member' : 'Not a member'); ?>

                                            <span class="mx-1">•</span><?php echo e($consumer->association_registered ? 'Officially registered' : 'Registration not confirmed'); ?>

                                            <?php if($consumer->membership_renewal_date): ?>
                                                <span class="mx-1">•</span>Renews <?php echo e($consumer->membership_renewal_date->format('M d, Y')); ?>

                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                            $rag = $consumerRag[$consumer->id] ?? ['level' => 'green', 'label' => 'Unknown'];
                                        ?>
                                        <span class="d-inline-flex align-items-center gap-1">
                                            <span class="rag-dot rag-<?php echo e($rag['level']); ?>"></span>
                                            <span class="rag-<?php echo e($rag['level']); ?>-text" style="font-size:0.82rem;"><?php echo e($rag['label']); ?></span>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($component)) { $__componentOriginal80e56fe1f0ea739e9cdec58b8ec4ba62 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal80e56fe1f0ea739e9cdec58b8ec4ba62 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.consumer-status-badge','data' => ['consumer' => $consumer]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('consumer-status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['consumer' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($consumer)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal80e56fe1f0ea739e9cdec58b8ec4ba62)): ?>
<?php $attributes = $__attributesOriginal80e56fe1f0ea739e9cdec58b8ec4ba62; ?>
<?php unset($__attributesOriginal80e56fe1f0ea739e9cdec58b8ec4ba62); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal80e56fe1f0ea739e9cdec58b8ec4ba62)): ?>
<?php $component = $__componentOriginal80e56fe1f0ea739e9cdec58b8ec4ba62; ?>
<?php unset($__componentOriginal80e56fe1f0ea739e9cdec58b8ec4ba62); ?>
<?php endif; ?>
                                        <?php
                                            $connectionRequest = $newConnectionRequests->get($consumer->id);
                                        ?>
                                        <?php if($connectionRequest): ?>
                                            <div class="small text-secondary mt-2">Connection Request: <?php echo e($connectionRequest->status); ?></div>
                                        <?php endif; ?>
                                        <?php if($consumer->resubmitted_at && $consumer->status === 'Pending Verification'): ?>
                                            <div>
                                                <span class="badge bg-info text-dark mt-1">
                                                    Resubmitted <?php echo e($consumer->resubmitted_at->format('M d, Y')); ?>

                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if($consumer->status === 'Rejected' && ! empty($correctionFieldsByConsumer[$consumer->id])): ?>
                                            <div class="small text-secondary mt-1">
                                                Needs: <?php echo e(implode(', ', $correctionFieldsByConsumer[$consumer->id])); ?>

                                            </div>
                                        <?php endif; ?>
                                        <?php
                                            $consumerPendingReschedules = $pendingReschedulesByConsumer[$consumer->id] ?? collect();
                                        ?>
                                        <?php if($consumer->installation_date): ?>
                                            <?php if (isset($component)) { $__componentOriginal6a8924d58300970ae01ffe6c17a9cc3d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6a8924d58300970ae01ffe6c17a9cc3d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.reschedule-usage','data' => ['usage' => app(\App\Services\InstallationRescheduleService::class)->usage($consumer)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('reschedule-usage'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['usage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(app(\App\Services\InstallationRescheduleService::class)->usage($consumer))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6a8924d58300970ae01ffe6c17a9cc3d)): ?>
<?php $attributes = $__attributesOriginal6a8924d58300970ae01ffe6c17a9cc3d; ?>
<?php unset($__attributesOriginal6a8924d58300970ae01ffe6c17a9cc3d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6a8924d58300970ae01ffe6c17a9cc3d)): ?>
<?php $component = $__componentOriginal6a8924d58300970ae01ffe6c17a9cc3d; ?>
<?php unset($__componentOriginal6a8924d58300970ae01ffe6c17a9cc3d); ?>
<?php endif; ?>
                                        <?php endif; ?>
                                        <?php $__currentLoopData = $consumerPendingReschedules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reschedule): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <div class="mt-1">
                                                <span class="badge <?php echo e($reschedule->isLoan() ? 'bg-primary' : 'bg-info text-dark'); ?>">
                                                    <i class="bi bi-calendar-event me-1"></i>Reschedule Request: <?php echo e($reschedule->isLoan() ? 'Loan' : 'Connection'); ?>

                                                </span>
                                                <div class="small text-secondary" title="<?php echo e($reschedule->reason); ?>">
                                                    Reason: <?php echo e(\Illuminate\Support\Str::limit($reschedule->reason, 35)); ?>

                                                </div>
                                            </div>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </td>
                                    <?php endif; ?>
                                    <td class="text-end">
                                        <?php if($filters['account_category'] === 'inactive'): ?>
                                            <?php echo $__env->make('admin.consumers.reconnect-action', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#consumerEditModal" data-consumer='<?php echo e($consumerPayload); ?>'>Edit</button>
                                            <?php if($consumer->meter_number): ?>
                                                <button type="button" class="btn btn-sm btn-outline-info me-2" data-bs-toggle="modal" data-bs-target="#consumerMeterModal" data-consumer="<?php echo e($consumerPayload); ?>" title="Assign a newly issued meter number"><i class="bi bi-speedometer2 me-1"></i>Assign Meter</button>
                                            <?php endif; ?>
                                            <form method="POST" action="<?php echo e(route('consumers.destroy', $consumer)); ?>" class="d-inline">
                                                <?php echo csrf_field(); ?>
                                                <?php echo method_field('DELETE'); ?>
                                                <button type="submit" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Delete this consumer?')">Delete</button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-outline-primary ms-2" data-inactive-details-toggle aria-expanded="false" aria-controls="inactive-details-<?php echo e($consumer->id); ?>">View More ▼</button>
                                        <?php else: ?>
                                        <?php $__currentLoopData = $consumerPendingReschedules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reschedule): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php
                                                $reschedulePayload = base64_encode(json_encode([
                                                    'id' => $reschedule->id,
                                                    'consumer_id' => $consumer->id,
                                                    'consumer_name' => $consumer->full_name,
                                                    'account_number' => $consumer->account_number ?: $consumer->application_reference,
                                                    'reschedule_type' => $reschedule->reschedule_type,
                                                    'is_loan' => $reschedule->isLoan(),
                                                    'is_reconnection' => $reschedule->isReconnection(),
                                                    'preferred_date' => $reschedule->preferred_installation_date?->toDateString(),
                                                    'preferred_time' => $reschedule->preferred_installation_time ? substr($reschedule->preferred_installation_time, 0, 5) : '',
                                                    'preferred_schedule' => $reschedule->formattedPreferredSchedule(),
                                                    'usage' => app(\App\Services\InstallationRescheduleService::class)->usage($consumer),
                                                    'original_date' => optional($reschedule->original_installation_date)->toDateString(),
                                                    'original_schedule_formatted' => $reschedule->formattedOriginalSchedule(),
                                                    'original_time' => $reschedule->original_installation_time ? substr((string) $reschedule->original_installation_time, 0, 5) : '',
                                                    'reason' => $reschedule->reason,
                                                    'created_at' => $reschedule->created_at->format('M d, Y g:i A'),
                                                    'assign_url' => route('installation-reschedules.assign', $reschedule),
                                                    'history' => ($rescheduleHistoryByConsumer[$consumer->id] ?? collect())->map(fn ($h) => [
                                                        'status' => ucfirst($h->status),
                                                        'original' => $h->formattedOriginalSchedule(),
                                                        'new' => $h->formattedNewSchedule(),
                                                        'reason' => $h->reason,
                                                        'admin_notes' => $h->admin_notes,
                                                        'reviewed_at' => optional($h->reviewed_at)->format('M d, Y'),
                                                    ])->values()->all(),
                                                ]));
                                            ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-info text-white me-2"
                                                data-bs-toggle="modal"
                                                data-bs-target="#adminReviewRescheduleModal"
                                                data-reschedule="<?php echo e($reschedulePayload); ?>"
                                                title="Assign a new installation date and time"
                                                <?php if(! app(\App\Services\InstallationRescheduleService::class)->usage($consumer)['available']): echo 'disabled'; endif; ?>
                                            >
                                                <i class="bi bi-calendar2-check me-1"></i>Assign New Schedule
                                            </button>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        <?php if($consumer->status === 'Active'): ?>
                                            <?php if($consumer->user_id && $consumer->email): ?>
                                                <form
                                                    method="POST"
                                                    action="<?php echo e(route('consumers.resend-credentials', $consumer)); ?>"
                                                    class="d-inline"
                                                    onsubmit="return confirm('Re-send login instructions and a secure password setup link to <?php echo e($consumer->email); ?>?');"
                                                >
                                                    <?php echo csrf_field(); ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-primary me-2" title="Re-send login instructions and a secure password setup link">
                                                        <i class="bi bi-envelope me-1"></i>Resend Login Details
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if($consumer->hasPendingMembershipVerification() && ! $canVerifyConsumer): ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-warning me-2"
                                                data-bs-toggle="modal"
                                                data-bs-target="#consumerEditModal"
                                                data-consumer='<?php echo e($consumerPayload); ?>'
                                                title="Review the existing VIOHOA membership claim"
                                            >
                                                <i class="bi bi-person-check me-1"></i>Review Membership
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if($filters['account_category'] === 'inactive'): ?>
                                            <?php echo $__env->make('admin.consumers.reconnect-action', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                                        <?php endif; ?>
                                        <?php if(in_array($consumer->status, ['Active', 'For Review', 'Disconnected', 'For Reconnection'], true)): ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-secondary me-2"
                                                data-bs-toggle="modal"
                                                data-bs-target="#consumerEditModal"
                                                data-consumer='<?php echo e($consumerPayload); ?>'
                                            >
                                                Edit
                                            </button>
                                        <?php endif; ?>
                                        <?php if($canVerifyConsumer): ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-success me-2"
                                                data-bs-toggle="modal"
                                                data-bs-target="#consumerActivateModal"
                                                data-consumer="<?php echo e($consumerPayload); ?>"
                                            >
                                                <i class="bi bi-check-circle me-1"></i>
                                                Verify
                                            </button>
                                        <?php endif; ?>
                                        <?php if($consumer->meter_number): ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-info me-2"
                                                data-bs-toggle="modal"
                                                data-bs-target="#consumerMeterModal"
                                                data-consumer="<?php echo e($consumerPayload); ?>"
                                                title="Assign a newly issued meter number"
                                            >
                                                <i class="bi bi-speedometer2 me-1"></i>Assign Meter
                                            </button>
                                        <?php endif; ?>
                                        <?php if($consumer->status === 'Rejected' && $consumer->email): ?>
                                            <form
                                                method="POST"
                                                action="<?php echo e(route('consumers.resend-correction-link', $consumer)); ?>"
                                                class="d-inline"
                                                onsubmit="return confirm('Re-send the correction link to <?php echo e($consumer->email); ?>?');"
                                            >
                                                <?php echo csrf_field(); ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger me-2" title="Send a fresh Edit and Resubmit link">
                                                    <i class="bi bi-envelope-arrow-up me-1"></i>Re-send Correction Link
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="<?php echo e(route('consumers.destroy', $consumer)); ?>" class="d-inline">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button type="submit" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Delete this consumer?')">Delete</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if($filters['account_category'] === 'inactive'): ?>
                                    <tr id="inactive-details-<?php echo e($consumer->id); ?>" class="d-none" data-inactive-details-row>
                                        <td colspan="9" class="bg-light">
                                            <div class="row g-3 py-2">
                                                <div class="col-md-6"><strong>Address</strong><div><?php echo e($consumer->service_address ?: 'Not provided'); ?></div></div>
                                                <div class="col-md-6"><strong>Contact</strong><div><?php echo e($consumer->contact_number ?: 'Not provided'); ?></div></div>
                                                <div class="col-md-6"><strong>Email</strong><div><?php echo e($consumer->email ?: 'Not provided'); ?></div></div>
                                                <div class="col-md-6"><strong>Membership and association</strong><div><?php echo e($consumer->membership_type); ?> · <?php echo e($consumer->association_member ? 'Association member' : 'Not an association member'); ?> · <?php echo e($consumer->association_registered ? 'Registered' : 'Not registered'); ?></div></div>
                                                <div class="col-12"><strong>Disconnection history</strong>
                                                    <div><?php echo e($consumer->status); ?> · <?php echo e($consumer->reconnection_date?->format('M d, Y') ?: 'Reconnection date not recorded'); ?></div>
                                                </div>
                                                <?php if($consumer->installation_date): ?><div class="col-md-6"><strong>Reschedule attempts</strong><div><?php if (isset($component)) { $__componentOriginal6a8924d58300970ae01ffe6c17a9cc3d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6a8924d58300970ae01ffe6c17a9cc3d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.reschedule-usage','data' => ['usage' => app(\App\Services\InstallationRescheduleService::class)->usage($consumer)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('reschedule-usage'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['usage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(app(\App\Services\InstallationRescheduleService::class)->usage($consumer))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6a8924d58300970ae01ffe6c17a9cc3d)): ?>
<?php $attributes = $__attributesOriginal6a8924d58300970ae01ffe6c17a9cc3d; ?>
<?php unset($__attributesOriginal6a8924d58300970ae01ffe6c17a9cc3d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6a8924d58300970ae01ffe6c17a9cc3d)): ?>
<?php $component = $__componentOriginal6a8924d58300970ae01ffe6c17a9cc3d; ?>
<?php unset($__componentOriginal6a8924d58300970ae01ffe6c17a9cc3d); ?>
<?php endif; ?></div></div><?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="<?php echo e($filters['account_category'] === 'inactive' ? 9 : 6); ?>" class="text-center py-5">
                                        <div class="text-secondary mb-2"><?php echo e($filters['account_category'] === 'active' ? 'No active accounts match the current filters.' : 'No consumers found.'); ?></div>
                                        <?php if($filters['account_category'] !== 'active'): ?>
                                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#consumerModal">
                                                Add the first consumer
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3">
                    <p class="text-secondary mb-0">
                        <?php if($consumers->total() > 0): ?>
                            Showing <?php echo e($consumers->firstItem()); ?> to <?php echo e($consumers->lastItem()); ?> of <?php echo e($consumers->total()); ?> consumers
                        <?php else: ?>
                            No consumer records yet
                        <?php endif; ?>
                    </p>
                    <?php echo e($consumers->links()); ?>

                </div>
                <?php if($filters['account_category'] === 'active'): ?>
                </section>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>



<div class="modal fade" id="consumerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <h2 class="modal-title fs-5">Add Consumer</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="<?php echo e(route('consumers.store')); ?>" id="consumerCreateForm">
                    <?php echo csrf_field(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="full_name">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo e(old('full_name')); ?>" placeholder="Enter consumer name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="account_number">Account Number</label>
                            <input type="text" class="form-control" id="account_number" name="account_number" value="<?php echo e(old('account_number')); ?>" placeholder="Leave blank to auto-generate 1, 2, 3...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="meter_number">Meter Number</label>
                            <input type="text" class="form-control" id="meter_number" name="meter_number" value="<?php echo e(old('meter_number')); ?>" placeholder="MT-78455" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="membership_type">Membership Type</label>
                            <select class="form-select" id="membership_type" name="membership_type" required>
                                <?php $__currentLoopData = $membershipTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $membershipType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($membershipType); ?>" <?php if(old('membership_type', 'Non-Member') === $membershipType): echo 'selected'; endif; ?>><?php echo e($membershipType); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold" for="service_address">Service Address</label>
                            <input type="text" class="form-control" id="service_address" name="service_address" value="<?php echo e(old('service_address')); ?>" placeholder="Enter service address">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="contact_number">Contact Number</label>
                            <input type="text" inputmode="numeric" pattern="[0-9]{10,15}" class="form-control js-digits-only" id="contact_number" name="contact_number" value="<?php echo e(old('contact_number')); ?>" placeholder="09XXXXXXXXX">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="address_block">Block</label>
                            <input type="text" class="form-control" id="address_block" name="address_block" value="<?php echo e(old('address_block')); ?>" placeholder="e.g. Block 12">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="address_street">Street</label>
                            <input type="text" class="form-control" id="address_street" name="address_street" value="<?php echo e(old('address_street')); ?>" placeholder="e.g. Maharlika Street">
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4 pt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="housing_rights_documented" name="housing_rights_documented" <?php if(old('housing_rights_documented')): echo 'checked'; endif; ?>>
                                <label class="form-check-label" for="housing_rights_documented">Pag-IBIG / house rights documented</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4 pt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="association_member" name="association_member" <?php if(old('association_member')): echo 'checked'; endif; ?>>
                                <label class="form-check-label" for="association_member">Local association member</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4 pt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="association_registered" name="association_registered" <?php if(old('association_registered')): echo 'checked'; endif; ?>>
                                <label class="form-check-label" for="association_registered">Officially registered</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="membership_renewal_date">Membership Date</label>
                            <input type="date" class="form-control" id="membership_renewal_date" name="membership_renewal_date" value="<?php echo e(old('membership_renewal_date')); ?>">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="consumerCreateForm" class="btn btn-primary">Save Consumer</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="consumerEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5">Edit Consumer</h2>
                    <div class="mt-1" id="consumerEditStatusBadge"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="#" id="consumerEditForm">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PATCH'); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="edit_full_name">Full Name</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" value="<?php echo e(old('full_name')); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="edit_email">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" value="<?php echo e(old('email')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_account_number">Account Number</label>
                            <input type="text" class="form-control" id="edit_account_number" name="account_number" value="<?php echo e(old('account_number')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_meter_number">Meter Number</label>
                            <input type="text" class="form-control" id="edit_meter_number" name="meter_number" value="<?php echo e(old('meter_number')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_status">Status</label>
                            <select class="form-select" id="edit_status" name="status" required data-status-locked-on="Disconnected,For Reconnection">
                                <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($status); ?>"><?php echo e($status); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <div class="form-text d-none" id="edit_status_locked_note">
                                Status is locked while the account is awaiting reconnection.
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_membership_type">Membership Type</label>
                            <select class="form-select" id="edit_membership_type" name="membership_type" required>
                                <?php $__currentLoopData = $membershipTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $membershipType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($membershipType); ?>"><?php echo e($membershipType); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_property_type">Property Type</label>
                            <select class="form-select" id="edit_property_type" name="property_type">
                                <option value="">Select property type</option>
                                <option value="Residential">Residential</option>
                                <option value="Commercial">Commercial</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_ownership_status">Ownership Status</label>
                            <select class="form-select" id="edit_ownership_status" name="ownership_status">
                                <option value="">Select ownership status</option>
                                <option value="Owner">Owner</option>
                                <option value="Tenant">Tenant</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="edit_membership_verification_notes">Membership Verification Notes</label>
                            <textarea class="form-control" id="edit_membership_verification_notes" name="membership_verification_notes" rows="2" maxlength="1000" placeholder="Optional: record what was checked or why the membership claim was not verified."><?php echo e(old('membership_verification_notes')); ?></textarea>
                            <div class="form-text">Changing the membership type records a new verification decision with your account and the current time.</div>
                        </div>
                        <div class="col-12 d-none" id="edit_membership_pending_panel">
                            <div class="alert alert-warning mb-0">
                                <div class="fw-semibold"><i class="bi bi-person-check me-1"></i>VIOHOA membership verification pending</div>
                                <div class="small mt-1">
                                    Choosing <strong>Member</strong> verifies the existing membership and unlocks member benefits.
                                    Choosing <strong>Non-Member</strong> records the claim as not verified.
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" value="1" id="edit_membership_verification_confirmed" name="membership_verification_confirmed" <?php if(old('membership_verification_confirmed')): echo 'checked'; endif; ?>>
                                    <label class="form-check-label" for="edit_membership_verification_confirmed">
                                        I reviewed the membership evidence and confirm this decision.
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_address_block">Block</label>
                            <input type="text" class="form-control" id="edit_address_block" name="address_block" value="<?php echo e(old('address_block')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_address_street">Street</label>
                            <input type="text" class="form-control" id="edit_address_street" name="address_street" value="<?php echo e(old('address_street')); ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold" for="edit_service_address">Service Address</label>
                            <input type="text" class="form-control" id="edit_service_address" name="service_address" value="<?php echo e(old('service_address')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_contact_number">Contact Number</label>
                            <input type="text" inputmode="numeric" pattern="[0-9]{10,15}" class="form-control js-digits-only" id="edit_contact_number" name="contact_number" value="<?php echo e(old('contact_number')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="edit_valid_id_presented">Valid ID Presented</label>
                            <input type="text" class="form-control" id="edit_valid_id_presented" name="valid_id_presented" value="<?php echo e(old('valid_id_presented')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="edit_reconnection_date">Reconnection Date</label>
                            <input type="date" class="form-control" id="edit_reconnection_date" name="reconnection_date" value="<?php echo e(old('reconnection_date')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="edit_installation_date">Installation Date</label>
                            <input type="date" class="form-control" id="edit_installation_date" name="installation_date" value="<?php echo e(old('installation_date')); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="edit_other_document_details">Other Document Details</label>
                            <input type="text" class="form-control" id="edit_other_document_details" name="other_document_details" value="<?php echo e(old('other_document_details')); ?>">
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_housing_rights_documented" name="housing_rights_documented" <?php if(old('housing_rights_documented')): echo 'checked'; endif; ?>>
                                <label class="form-check-label" for="edit_housing_rights_documented">Housing rights documented</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_resident_of_community" name="resident_of_community" <?php if(old('resident_of_community')): echo 'checked'; endif; ?>>
                                <label class="form-check-label" for="edit_resident_of_community">Resident of community</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_association_member" name="association_member" <?php if(old('association_member')): echo 'checked'; endif; ?>>
                                <label class="form-check-label" for="edit_association_member">Association member</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_association_registered" name="association_registered" <?php if(old('association_registered')): echo 'checked'; endif; ?>>
                                <label class="form-check-label" for="edit_association_registered">Association registered</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_housing_loan_contract_submitted" name="housing_loan_contract_submitted" <?php if(old('housing_loan_contract_submitted')): echo 'checked'; endif; ?>>
                                <label class="form-check-label" for="edit_housing_loan_contract_submitted">Housing loan contract submitted</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_valid_id_submitted" name="valid_id_submitted" <?php if(old('valid_id_submitted')): echo 'checked'; endif; ?>>
                                <label class="form-check-label" for="edit_valid_id_submitted">Valid ID submitted</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_proof_of_residency_submitted" name="proof_of_residency_submitted" <?php if(old('proof_of_residency_submitted')): echo 'checked'; endif; ?>>
                                <label class="form-check-label" for="edit_proof_of_residency_submitted">Proof of residency submitted</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="consumerEditForm" class="btn btn-primary">Update Consumer</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="consumerActivateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5">Review Application &amp; Verify Account</h2>
                    <div class="mt-1" id="consumerActivateStatusBadge"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="activateInfoContent" class="mb-4">
                    <div class="text-center text-secondary py-3">Loading applicant information...</div>
                </div>

                <hr>

                <form method="POST" action="#" id="consumerActivateForm">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PATCH'); ?>
                    <h6 class="fw-bold mb-3">Activation &amp; Membership Decision</h6>
                    <p class="small text-secondary mb-3">
                        Approving here activates the account, records the membership decision, and marks the linked water connection request as approved.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="activation_membership_type">Membership Type</label>
                        <select class="form-select" id="activation_membership_type" name="membership_type" required>
                            <?php $__currentLoopData = $membershipTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $membershipType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($membershipType); ?>" <?php if(old('membership_type') === $membershipType): echo 'selected'; endif; ?>><?php echo e($membershipType); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <div class="form-text" id="activation_membership_type_help"></div>
                    </div>
                    <div class="alert alert-warning d-none" id="activation_membership_pending_alert">
                        <div class="fw-semibold"><i class="bi bi-person-check me-1"></i>This applicant declared existing VIOHOA membership.</div>
                        <div class="small mt-1">Activating as <strong>Member</strong> verifies the existing membership and applies member billing rates. Activating as <strong>Non-Member</strong> records the claim as not verified. The water connection request is marked approved either way.</div>
                    </div>
                    <div class="border rounded-3 p-3 mb-3 d-none" id="activation_membership_confirmation">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="activation_membership_verification_confirmed" name="membership_verification_confirmed" <?php if(old('membership_verification_confirmed')): echo 'checked'; endif; ?>>
                            <label class="form-check-label fw-semibold" for="activation_membership_verification_confirmed">
                                I reviewed the membership claim and supporting documents and confirm this applicant is an officially registered association member.
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="activation_membership_verification_notes">Membership Verification Notes</label>
                        <textarea class="form-control" id="activation_membership_verification_notes" name="membership_verification_notes" rows="2" maxlength="1000" placeholder="Optional: record what was checked or why a claim was not verified."><?php echo e(old('membership_verification_notes')); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="activation_meter_number">Meter Number</label>
                        <input type="text" class="form-control" id="activation_meter_number" name="meter_number" value="<?php echo e(old('meter_number')); ?>" placeholder="Assign meter number" required>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-semibold" for="activation_installation_date">Installation Date</label>
                        <input type="date" class="form-control" id="activation_installation_date" name="installation_date" value="<?php echo e(old('installation_date')); ?>" required>
                    </div>
                </form>

                <hr>

                <div id="activateRejectSection">
                    <h6 class="fw-bold mb-3 text-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>Reject Application
                    </h6>
                    <p class="small text-secondary">
                        Rejecting the application marks the water connection request as rejected. A still-pending membership claim is also recorded as not verified; an already-verified membership is retained.
                    </p>
                    <form method="POST" action="#" id="consumerRejectForm">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold d-block">Requirements to Correct <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <?php $__currentLoopData = $correctionFieldOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fieldKey => $fieldLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="correction_fields[]"
                                                value="<?php echo e($fieldKey); ?>"
                                                id="correction_field_<?php echo e($fieldKey); ?>"
                                                <?php if(in_array($fieldKey, old('correction_fields', []), true)): echo 'checked'; endif; ?>
                                                <?php if($loop->first): ?> required <?php endif; ?>
                                            >
                                            <label class="form-check-label" for="correction_field_<?php echo e($fieldKey); ?>"><?php echo e($fieldLabel); ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                            <div class="form-text">
                                Only the selected fields and documents can be edited by the applicant when they resubmit.
                                Requirements named in the reason below are added automatically.
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold" for="rejection_reason">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" placeholder="Explain why this connection request is being rejected..." required><?php echo e(old('rejection_reason')); ?></textarea>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="submit" form="consumerRejectForm" class="btn btn-outline-danger">Reject Application</button>
                <div>
                    <button type="button" class="btn btn-light border me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="consumerActivateForm" class="btn btn-success">Verify and Activate</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="consumerMeterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <form method="POST" id="consumerMeterForm">
                <?php echo csrf_field(); ?>
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Assign New Meter Number</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if($errors->any()): ?>
                        <div class="alert alert-danger py-2">
                            <div class="small"><?php echo e($errors->first()); ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Consumer</label>
                        <div class="fw-semibold" id="meter_consumer_name">&mdash;</div>
                        <div class="small text-secondary" id="meter_consumer_account"></div>
                    </div>

                    <div class="alert alert-warning d-flex align-items-start gap-2">
                        <i class="bi bi-info-circle mt-1"></i>
                        <div class="small">
                            Assigning a new meter immediately replaces the current meter number on this account, so the
                            Meter Reader module will use the new number for every future reading.
                            The previous meter number and its readings are kept on record for auditing.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="assign_current_meter">Current Meter Number</label>
                        <input type="text" class="form-control" id="assign_current_meter" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="assign_meter_number">
                            New Meter Number <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control <?php $__errorArgs = ['meter_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="assign_meter_number" name="meter_number" value="<?php echo e(old('meter_number')); ?>" placeholder="MT-90210" required autocomplete="off">
                        <div class="form-text">Must not already belong to another active consumer account.</div>
                        <?php $__errorArgs = ['meter_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <div class="invalid-feedback"><?php echo e($message); ?></div>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="assign_meter_reason">Reason (optional)</label>
                        <input type="text" class="form-control" id="assign_meter_reason" name="reason" value="<?php echo e(old('reason')); ?>" maxlength="255" placeholder="e.g. Defective meter replaced">
                    </div>

                    <div id="assign_meter_history"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Assign Meter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php echo $__env->make('admin.consumers.assign-reschedule-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if($filters['account_category'] === 'inactive'): ?>
    <?php echo $__env->make('admin.consumers.reconnect-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-inactive-details-toggle]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const details = document.getElementById(button.getAttribute('aria-controls'));
                    if (! details) return;
                    const expanded = button.getAttribute('aria-expanded') === 'true';
                    details.classList.toggle('d-none', expanded);
                    button.setAttribute('aria-expanded', String(! expanded));
                    button.textContent = expanded ? 'View More ▼' : 'View Less ▲';
                });
            });

            const activateModal = document.getElementById('consumerActivateModal');
            const activateForm = document.getElementById('consumerActivateForm');
            const editModal = document.getElementById('consumerEditModal');
            const editForm = document.getElementById('consumerEditForm');
            const rejectForm = document.getElementById('consumerRejectForm');
            const meterModal = document.getElementById('consumerMeterModal');
            const meterForm = document.getElementById('consumerMeterForm');

            const renderConsumerStatusBadge = function (elementId, display) {
                const target = document.getElementById(elementId);

                if (! target) {
                    return;
                }

                target.innerHTML = '';

                if (! display || ! display.label) {
                    return;
                }

                const wrapper = document.createElement('span');
                wrapper.className = 'consumer-service-status';

                if (display.description) {
                    wrapper.title = display.description;
                }

                const badge = document.createElement('span');
                badge.className = 'badge consumer-status-badge ' + (display.badge_class || 'badge-soft-primary');

                const icon = document.createElement('i');
                icon.className = 'bi ' + (display.icon || 'bi-info-circle');
                icon.setAttribute('aria-hidden', 'true');

                const label = document.createElement('span');
                label.textContent = display.label;

                badge.append(icon, label);

                const subLabel = document.createElement('span');
                subLabel.className = 'consumer-status-sub ' + (display.sub_class || '');
                subLabel.textContent = display.sub_label || '';

                wrapper.append(badge, subLabel);
                target.appendChild(wrapper);
            };

            if (meterModal && meterForm) {
                meterModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;

                    if (! button) {
                        return;
                    }

                    const payload = JSON.parse(atob(button.getAttribute('data-consumer') || 'e30='));

                    meterForm.action = `<?php echo e(url('/consumers')); ?>/${payload.id}/assign-meter`;
                    document.getElementById('meter_consumer_name').textContent = payload.full_name || '';
                    document.getElementById('meter_consumer_account').textContent = payload.account_number || 'No account number';
                    document.getElementById('assign_current_meter').value = payload.meter_number || '';
                    document.getElementById('assign_meter_number').value = <?php echo json_encode(old('meter_number'), 15, 512) ?> || '';
                    document.getElementById('assign_meter_reason').value = <?php echo json_encode(old('reason'), 15, 512) ?> || '';

                    const history = Array.isArray(payload.meter_history) ? payload.meter_history : [];
                    const historyEl = document.getElementById('assign_meter_history');

                    if (history.length > 1) {
                        let html = '<h6 class="fw-bold mt-3 mb-2">Meter History</h6><ul class="list-group list-group-flush small">';

                        history.forEach(function (entry) {
                            html += '<li class="list-group-item px-0 d-flex justify-content-between"><span>MT ' + entry.meter_number + '</span><span class="text-secondary">' + (entry.retired_at ? 'Retired ' + entry.retired_at : 'Active') + '</span></li>';
                        });

                        html += '</ul>';
                        historyEl.innerHTML = html;
                    } else {
                        historyEl.innerHTML = '';
                    }
                });
            }

            // Contact numbers accept numeric digits only.
            document.querySelectorAll('.js-digits-only').forEach(function (input) {
                input.addEventListener('input', function () {
                    const cleaned = input.value.replace(/[^0-9]/g, '').slice(0, 15);

                    if (cleaned !== input.value) {
                        input.value = cleaned;
                    }
                });
            });

            if (activateModal && activateForm) {
                activateModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;

                    if (! button) {
                        return;
                    }

                    const payload = JSON.parse(atob(button.getAttribute('data-consumer') || 'e30='));
                    const consumerId = payload.id;

                    renderConsumerStatusBadge('consumerActivateStatusBadge', payload.status_display);

                    activateForm.action = `<?php echo e(url('/consumers')); ?>/${consumerId}/activate`;

                    const membershipSelect = document.getElementById('activation_membership_type');
                    membershipSelect.value = <?php echo json_encode(old('membership_type'), 15, 512) ?> || payload.requested_membership_type || payload.membership_type || 'Non-Member';

                    const membershipNotes = document.getElementById('activation_membership_verification_notes');
                    membershipNotes.value = <?php echo json_encode(old('membership_verification_notes'), 15, 512) ?> || payload.membership_verification_notes || '';

                    const membershipConfirmed = document.getElementById('activation_membership_verification_confirmed');
                    membershipConfirmed.checked = Boolean(<?php echo json_encode(old('membership_verification_confirmed'), 15, 512) ?>);

                    const membershipPending = payload.requested_membership_type === 'Member'
                        && payload.membership_verification_status === 'Pending';
                    const membershipPendingAlert = document.getElementById('activation_membership_pending_alert');
                    const membershipConfirmation = document.getElementById('activation_membership_confirmation');
                    const membershipHelp = document.getElementById('activation_membership_type_help');

                    const syncMembershipVerificationUi = function () {
                        const selectedMembership = membershipSelect.value;
                        const needsConfirmation = membershipPending && selectedMembership === 'Member';

                        membershipPendingAlert.classList.toggle('d-none', ! membershipPending);
                        membershipConfirmation.classList.toggle('d-none', ! needsConfirmation);
                        membershipConfirmed.required = needsConfirmation;

                        if (selectedMembership === 'Member') {
                            membershipHelp.textContent = membershipPending
                                ? 'Approving this existing VIOHOA membership verifies it and applies member billing rates.'
                                : 'This account will be recorded as a verified member.';
                        } else {
                            membershipHelp.textContent = membershipPending
                                ? 'Selecting Non-Member records the existing membership claim as not verified and uses the non-member rate.'
                                : 'This account will be recorded as a non-member.';
                        }
                    };

                    membershipSelect.onchange = syncMembershipVerificationUi;
                    syncMembershipVerificationUi();

                    document.getElementById('activation_meter_number').value = <?php echo json_encode(old('meter_number'), 15, 512) ?> || payload.meter_number || '';
                    document.getElementById('activation_installation_date').value = <?php echo json_encode(old('installation_date'), 15, 512) ?> || '';

                    if (rejectForm) {
                        rejectForm.action = `<?php echo e(url('/consumers')); ?>/${consumerId}/reject`;
                        document.getElementById('rejection_reason').value = '';
                        rejectForm.querySelectorAll('input[name="correction_fields[]"]').forEach(function (checkbox) {
                            checkbox.checked = false;
                        });
                    }

                    const infoContent = document.getElementById('activateInfoContent');

                    if (infoContent) {
                        const boolYesNo = function (value) { return value ? 'Yes' : 'No'; };

                        const fields = [
                            { key: 'full_name', label: 'Full Name' },
                            { key: 'email', label: 'Email' },
                            { key: 'contact_number', label: 'Contact Number' },
                            { key: 'service_address', label: 'Service Address' },
                            { key: 'requested_membership_type', label: 'Declared VIOHOA Membership' },
                            { key: 'membership_type', label: 'Effective Classification' },
                            { key: 'membership_verification_status', label: 'Membership Verification' },
                            { key: 'membership_verification_notes', label: 'Membership Verification Notes' },
                            { key: 'connection_request_status', label: 'Water Connection Request' },
                            { key: 'connection_request_admin_notes', label: 'Connection Request Notes' },
                            { key: 'property_type', label: 'Property Type' },
                            { key: 'ownership_status', label: 'Ownership Status' },
                            { key: 'valid_id_presented', label: 'Valid ID Presented' },
                            { key: 'rejection_reason', label: 'Previous Rejection Reason' },
                            { key: 'rejection_fields', label: 'Requirements to Correct', type: 'list' },
                            { key: 'resubmitted_at', label: 'Resubmitted On' },
                            { key: 'housing_rights_documented', label: 'Housing Rights Documented', type: 'bool' },
                            { key: 'resident_of_community', label: 'Resident of Community', type: 'bool' },
                            { key: 'association_member', label: 'Association Member', type: 'bool' },
                            { key: 'association_registered', label: 'Association Registered', type: 'bool' },
                            { key: 'housing_loan_contract_submitted', label: 'Housing Loan Contract Submitted', type: 'bool' },
                            { key: 'membership_document_type', label: 'Selected Document Type' },
                            { key: 'other_document_details', label: 'Other Document Details' },
                        ];

                        let html = '<h6 class="fw-bold mb-3">Applicant Information</h6><div class="row g-3">';

                        for (const field of fields) {
                            let value = payload[field.key];

                            if (value === null || value === undefined || value === '') {
                                value = '<span class="text-secondary">Not provided</span>';
                            } else if (field.type === 'bool') {
                                value = boolYesNo(value);
                            } else if (field.type === 'list') {
                                value = (Array.isArray(value) && value.length > 0)
                                    ? value.join(', ')
                                    : '<span class="text-secondary">None recorded</span>';
                            }

                            html += '<div class="col-md-6"><div class="mb-1"><div class="small text-secondary fw-semibold text-uppercase" style="font-size:0.7rem;">' + field.label + '</div><div class="fw-medium">' + value + '</div></div></div>';
                        }

                        html += '</div>';

                        html += '<h6 class="fw-bold mt-4 mb-3">Valid ID Document</h6>';

                        if (payload.valid_id_url) {
                            html += '<div class="mb-3"><a href="' + payload.valid_id_url + '" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="bi bi-eye me-2"></i>View Valid ID</a><div class="small text-secondary mt-2">Opens the uploaded identification document in a new tab for review.</div></div>';
                        } else {
                            html += '<div class="text-secondary small fst-italic mb-3">No valid ID uploaded</div>';
                        }

                        html += '<h6 class="fw-bold mt-3 mb-2">Proof of Ownership/Residency</h6>';

                        if (payload.proof_of_residency_url) {
                            html += '<div class="mb-3"><a href="' + payload.proof_of_residency_url + '" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="bi bi-eye me-2"></i>View Proof of Ownership/Residency</a></div>';
                        } else {
                            html += '<div class="text-secondary small fst-italic mb-3">No proof of ownership/residency uploaded</div>';
                        }

                        html += '<h6 class="fw-bold mt-3 mb-2">Membership Document Photos</h6>';
                        html += '<div class="small text-secondary mb-2">Selected document type: <strong>' + (payload.membership_document_type || 'Not provided') + '</strong></div>';

                        const membershipDocumentUrls = Array.isArray(payload.membership_document_urls) ? payload.membership_document_urls : [];

                        if (membershipDocumentUrls.length > 0) {
                            html += '<div class="row g-3">';
                            membershipDocumentUrls.forEach(function (url, index) {
                                const label = (payload.membership_document_type || 'Membership Document') + ' Photo ' + (index + 1);
                                html += '<div class="col-md-6"><a href="' + url + '" target="_blank" rel="noopener"><img src="' + url + '" alt="' + label + '" class="img-fluid border rounded-3" style="max-height:220px;width:100%;object-fit:contain;cursor:pointer;background:#f8f9fa;" onerror="this.style.display=\'none\'"></a></div>';
                            });
                            html += '</div>';
                        } else {
                            html += '<div class="text-secondary small fst-italic mb-3">No membership document photos uploaded</div>';
                        }

                        if (payload.other_document_url) {
                            html += '<h6 class="fw-bold mt-3 mb-2">Other Document</h6>';
                            html += '<a href="' + payload.other_document_url + '" target="_blank" rel="noopener"><img src="' + payload.other_document_url + '" alt="Other Document" class="img-fluid border rounded-3" style="max-height:200px;width:100%;object-fit:contain;cursor:pointer;background:#f8f9fa;" onerror="this.style.display=\'none\'"></a>';
                        }

                        infoContent.innerHTML = html;
                    }
                });
            }

            if (editModal && editForm) {
                editModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;

                    if (! button) {
                        return;
                    }

                    const payload = JSON.parse(atob(button.getAttribute('data-consumer') || 'e30='));

                    renderConsumerStatusBadge('consumerEditStatusBadge', payload.status_display);

                    editForm.action = `<?php echo e(url('/consumers')); ?>/${payload.id}`;
                    document.getElementById('edit_full_name').value = <?php echo json_encode(old('full_name'), 15, 512) ?> || payload.full_name || '';
                    document.getElementById('edit_email').value = <?php echo json_encode(old('email'), 15, 512) ?> || payload.email || '';
                    document.getElementById('edit_account_number').value = <?php echo json_encode(old('account_number'), 15, 512) ?> || payload.account_number || '';
                    document.getElementById('edit_meter_number').value = <?php echo json_encode(old('meter_number'), 15, 512) ?> || payload.meter_number || '';
                    document.getElementById('edit_status').value = <?php echo json_encode(old('status'), 15, 512) ?> || payload.status || 'Active';
                    document.getElementById('edit_status').dataset.previousValue = payload.status || 'Active';
                    document.getElementById('edit_status').disabled = false;
                    document.getElementById('edit_status_locked_note').classList.add('d-none');
                    document.getElementById('edit_membership_type').value = <?php echo json_encode(old('membership_type'), 15, 512) ?> || payload.membership_type || 'Non-Member';
                    document.getElementById('edit_membership_verification_notes').value = <?php echo json_encode(old('membership_verification_notes'), 15, 512) ?> || payload.membership_verification_notes || '';

                    const editMembershipPending = payload.membership_verification_status === 'Pending';
                    const editMembershipPendingPanel = document.getElementById('edit_membership_pending_panel');
                    const editMembershipConfirmed = document.getElementById('edit_membership_verification_confirmed');

                    if (editMembershipPendingPanel) {
                        editMembershipPendingPanel.classList.toggle('d-none', ! editMembershipPending);
                    }

                    if (editMembershipConfirmed) {
                        editMembershipConfirmed.checked = editMembershipPending
                            && Boolean(<?php echo json_encode(old('membership_verification_confirmed'), 15, 512) ?>);
                        editMembershipConfirmed.required = editMembershipPending;
                    }

                    document.getElementById('edit_property_type').value = <?php echo json_encode(old('property_type'), 15, 512) ?> || payload.property_type || '';
                    document.getElementById('edit_ownership_status').value = <?php echo json_encode(old('ownership_status'), 15, 512) ?> || payload.ownership_status || '';
                    document.getElementById('edit_service_address').value = <?php echo json_encode(old('service_address'), 15, 512) ?> || payload.service_address || '';
                    document.getElementById('edit_address_block').value = <?php echo json_encode(old('address_block'), 15, 512) ?> || payload.address_block || '';
                    document.getElementById('edit_address_street').value = <?php echo json_encode(old('address_street'), 15, 512) ?> || payload.address_street || '';
                    document.getElementById('edit_contact_number').value = <?php echo json_encode(old('contact_number'), 15, 512) ?> || payload.contact_number || '';
                    document.getElementById('edit_valid_id_presented').value = <?php echo json_encode(old('valid_id_presented'), 15, 512) ?> || payload.valid_id_presented || '';
                    document.getElementById('edit_reconnection_date').value = <?php echo json_encode(old('reconnection_date'), 15, 512) ?> || payload.reconnection_date || '';
                    document.getElementById('edit_installation_date').value = <?php echo json_encode(old('installation_date'), 15, 512) ?> || payload.installation_date || '';

                    // Accounts awaiting reconnection keep their status locked.
                    const lockedStatuses = (document.getElementById('edit_status').dataset.statusLockedOn || '').split(',').map((value) => value.trim());
                    const statusHidden = document.getElementById('edit_status_hidden');
                    if (statusHidden) {
                        statusHidden.remove();
                    }
                    if (lockedStatuses.includes(payload.status)) {
                        document.getElementById('edit_status').disabled = true;
                        document.getElementById('edit_status_locked_note').classList.remove('d-none');
                        // A disabled select is not submitted, so mirror the value.
                        document.getElementById('edit_status').form.appendChild(Object.assign(document.createElement('input'), {
                            type: 'hidden',
                            name: 'status',
                            id: 'edit_status_hidden',
                            value: payload.status,
                        }));
                    }
                    document.getElementById('edit_other_document_details').value = <?php echo json_encode(old('other_document_details'), 15, 512) ?> || payload.other_document_details || '';
                    document.getElementById('edit_housing_rights_documented').checked = Boolean(<?php echo json_encode(old('housing_rights_documented'), 15, 512) ?> || payload.housing_rights_documented);
                    document.getElementById('edit_resident_of_community').checked = Boolean(<?php echo json_encode(old('resident_of_community'), 15, 512) ?> || payload.resident_of_community);
                    document.getElementById('edit_association_member').checked = Boolean(<?php echo json_encode(old('association_member'), 15, 512) ?> || payload.association_member);
                    document.getElementById('edit_association_registered').checked = Boolean(<?php echo json_encode(old('association_registered'), 15, 512) ?> || payload.association_registered);
                    document.getElementById('edit_housing_loan_contract_submitted').checked = Boolean(<?php echo json_encode(old('housing_loan_contract_submitted'), 15, 512) ?> || payload.housing_loan_contract_submitted);
                    document.getElementById('edit_valid_id_submitted').checked = Boolean(<?php echo json_encode(old('valid_id_submitted'), 15, 512) ?> || payload.valid_id_submitted);
                    document.getElementById('edit_proof_of_residency_submitted').checked = Boolean(<?php echo json_encode(old('proof_of_residency_submitted'), 15, 512) ?> || payload.proof_of_residency_submitted);
                });
            }

            const assignmentModal = document.getElementById('adminReviewRescheduleModal');
            if (assignmentModal) {
                assignmentModal.addEventListener('show.bs.modal', function (event) {
                    const trigger = event.relatedTarget;
                    if (!trigger) return;
                    const payload = JSON.parse(atob(trigger.getAttribute('data-reschedule') || 'e30='));
                    const form = document.getElementById('assignRescheduleForm');
                    form.reset();
                    form.action = payload.assign_url;
                    document.getElementById('arr_consumer_name').textContent = payload.consumer_name || '';
                    document.getElementById('arr_account').textContent = payload.account_number || '';
                    document.getElementById('arr_type_badge').textContent = payload.is_reconnection ? 'Reconnection' : (payload.is_loan ? 'Loan Installation' : 'Connection Installation');
                    document.getElementById('arr_original_schedule').textContent = payload.original_schedule_formatted || '';
                    document.getElementById('arr_reason').textContent = (payload.reason || '') + (payload.preferred_schedule ? ' Preferred schedule: ' + payload.preferred_schedule : '');
                    document.getElementById('arr_new_date').value = payload.preferred_date || '';
                    document.getElementById('arr_new_time').value = payload.preferred_time || '';
                    const usage = payload.usage;
                    document.getElementById('arr_usage').textContent = usage.used + ' of ' + usage.limit + ' used; ' + usage.remaining + ' attempts remaining.';
                    document.getElementById('assignRescheduleButton').disabled = !usage.available;
                    const historyList = document.getElementById('arr_history_list');
                    historyList.replaceChildren();
                    const history = payload.history || [];
                    document.getElementById('arr_history_container').classList.toggle('d-none', history.length === 0);
                    history.forEach(function (entry) {
                        const item = document.createElement('p');
                        item.textContent = entry.status + ': ' + entry.original + (entry.new ? ' → ' + entry.new : '') + '. Reason: ' + entry.reason + (entry.admin_notes ? '. Notes: ' + entry.admin_notes : '');
                        historyList.appendChild(item);
                    });
                });
            }
        });
    </script>

    <?php if($errors->any()): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalTarget = <?php echo json_encode(session('consumer_modal', 'single'), 512) ?>;
                const targetElement = document.getElementById(
                    modalTarget === 'bulk'
                        ? 'consumerBulkUploadModal'
                        : (modalTarget === 'activate' ? 'consumerActivateModal' : (modalTarget === 'edit' ? 'consumerEditModal' : (modalTarget === 'meter' ? 'consumerMeterModal' : 'consumerModal')))
                );

                if (! targetElement) {
                    return;
                }

                let modalTrigger = null;

                const findConsumerTrigger = function (selector, consumerId) {
                    return Array.from(document.querySelectorAll('[data-bs-target="' + selector + '"]')).find(function (button) {
                        try {
                            const payload = JSON.parse(atob(button.getAttribute('data-consumer') || 'e30='));

                            return payload.id === consumerId;
                        } catch (error) {
                            return false;
                        }
                    }) || null;
                };

                if (modalTarget === 'activate') {
                    const activateForm = document.getElementById('consumerActivateForm');
                    const consumerId = <?php echo json_encode(session('consumer_activate_id'), 15, 512) ?>;

                    if (activateForm && consumerId) {
                        activateForm.action = `<?php echo e(url('/consumers')); ?>/${consumerId}/activate`;
                        modalTrigger = findConsumerTrigger('#consumerActivateModal', consumerId);
                    }
                }

                if (modalTarget === 'edit') {
                    const editForm = document.getElementById('consumerEditForm');
                    const consumerId = <?php echo json_encode(session('consumer_edit_id'), 15, 512) ?>;

                    if (editForm && consumerId) {
                        editForm.action = `<?php echo e(url('/consumers')); ?>/${consumerId}`;
                        modalTrigger = findConsumerTrigger('#consumerEditModal', consumerId);
                    }
                }

                if (modalTarget === 'meter') {
                    const meterForm = document.getElementById('consumerMeterForm');
                    const consumerId = <?php echo json_encode(session('consumer_meter_id'), 15, 512) ?>;

                    if (meterForm && consumerId) {
                        meterForm.action = `<?php echo e(url('/consumers')); ?>/${consumerId}/assign-meter`;
                        modalTrigger = findConsumerTrigger('#consumerMeterModal', consumerId);
                    }
                }

                const modal = new bootstrap.Modal(targetElement);
                modal.show(modalTrigger || undefined);
            });
        </script>
    <?php endif; ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/admin/consumers/index.blade.php ENDPATH**/ ?>