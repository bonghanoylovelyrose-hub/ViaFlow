<fieldset class="mb-4">
    <legend class="visually-hidden">Are you a VVTHOA member?</legend>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="d-block" for="viohoa_member_yes">
                <input class="form-check-input visually-hidden register-step-option" type="radio" id="viohoa_member_yes" name="is_viohoa_member" value="yes" @checked(old('is_viohoa_member', $membershipAnswer ?? null) === 'yes') required>
                <span class="register-step-card d-block">
                    <span class="register-step-title d-block mb-2">YES</span>
                    <span class="register-step-meta d-block">I already have VVTHOA membership. My details will be verified first.</span>
                </span>
            </label>
        </div>
        <div class="col-md-6">
            <label class="d-block" for="viohoa_member_no">
                <input class="form-check-input visually-hidden register-step-option" type="radio" id="viohoa_member_no" name="is_viohoa_member" value="no" @checked(old('is_viohoa_member', $membershipAnswer ?? null) === 'no') required>
                <span class="register-step-card d-block">
                    <span class="register-step-title d-block mb-2">NO</span>
                    <span class="register-step-meta d-block">I am not a VVTHOA member. Continue to the connection request.</span>
                </span>
            </label>
        </div>
    </div>
    @error('is_viohoa_member')
        <div class="text-danger small mt-2">{{ $message }}</div>
    @enderror
</fieldset>

<div class="alert alert-info register-step-notice d-flex align-items-start gap-2" role="alert">
    <i class="bi bi-info-circle mt-1"></i>
    <div>If you choose YES, an administrator will verify your membership. Member rates apply after approval.</div>
</div>
