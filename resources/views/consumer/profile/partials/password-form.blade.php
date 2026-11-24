<div class="panel-card p-4">
    <div class="section-title">Change Password</div>
    <p class="text-secondary mb-4">Use your current password, then set a new password with at least 8 characters.</p>

    <form method="POST" action="{{ route('consumer.profile.password') }}">
        @csrf
        @method('PATCH')

        <div class="mb-3">
            <label for="current_password" class="form-label fw-semibold">Current Password</label>
            <div class="input-group">
                <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required>
                <button class="btn password-toggle" type="button" data-target="current_password" aria-label="Show password" aria-pressed="false">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label fw-semibold">New Password</label>
            <div class="input-group">
                <input type="password" class="form-control" id="password" name="password" minlength="8" autocomplete="new-password" required aria-describedby="passwordIndicator">
                <button class="btn password-toggle" type="button" data-target="password" aria-label="Show password" aria-pressed="false">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            <div class="password-indicator mt-3" id="passwordIndicator" hidden>
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <span class="small fw-semibold text-secondary">Password strength</span>
                    <span class="small fw-bold" data-password-strength-label>Enter password</span>
                </div>
                <div class="password-indicator-bar mb-3" aria-hidden="true">
                    <div class="password-indicator-fill" data-password-strength-fill></div>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6 password-rule" data-password-rule="length">
                        <i class="bi bi-circle me-1"></i>At least 8 characters
                    </div>
                    <div class="col-sm-6 password-rule" data-password-rule="lowercase">
                        <i class="bi bi-circle me-1"></i>Lowercase letter
                    </div>
                    <div class="col-sm-6 password-rule" data-password-rule="uppercase">
                        <i class="bi bi-circle me-1"></i>Uppercase letter
                    </div>
                    <div class="col-sm-6 password-rule" data-password-rule="number">
                        <i class="bi bi-circle me-1"></i>Number
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label fw-semibold">Confirm New Password</label>
            <div class="input-group">
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" autocomplete="new-password" required>
                <button class="btn password-toggle" type="button" data-target="password_confirmation" aria-label="Show password" aria-pressed="false">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            <div class="password-match-feedback" id="passwordMatchFeedback" aria-live="polite"></div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="bi bi-shield-lock me-2"></i>{{ $user->isConsumer() ? 'Change Password' : 'Update Password' }}
        </button>
    </form>
</div>
