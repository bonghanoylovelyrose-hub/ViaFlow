<div class="modal fade" id="adminReviewRescheduleModal" tabindex="-1" aria-labelledby="assign-reschedule-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" id="assignRescheduleForm" class="modal-content">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PATCH'); ?>
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="assign-reschedule-title">Assign New Schedule</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong id="arr_consumer_name"></strong> &middot; <span id="arr_account"></span></p>
                <p><span id="arr_type_badge"></span> &middot; Current schedule: <strong id="arr_original_schedule"></strong></p>
                <p id="arr_usage" class="fw-semibold"></p>
                <div id="arr_reason" class="alert alert-info"></div>
                <p>Assign a new installation date and time. The consumer will receive the updated schedule by email. Only a successful assignment uses a reschedule attempt.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="arr_new_date">New Installation Date</label>
                        <input type="date" class="form-control" id="arr_new_date" name="new_installation_date" min="<?php echo e(today()->toDateString()); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="arr_new_time">New Installation Time</label>
                        <input type="time" class="form-control" id="arr_new_time" name="new_installation_time" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="arr_assign_notes">Administrator Notes (Optional)</label>
                        <textarea id="arr_assign_notes" name="admin_notes" class="form-control" rows="2" maxlength="1000"></textarea>
                    </div>
                </div>
                <div id="arr_history_container" class="mt-3 d-none">
                    <h3 class="h6">Reschedule History</h3>
                    <div id="arr_history_list" class="small"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="assignRescheduleButton" class="btn btn-primary">Assign New Schedule</button>
            </div>
        </form>
    </div>
</div>
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/admin/consumers/assign-reschedule-modal.blade.php ENDPATH**/ ?>