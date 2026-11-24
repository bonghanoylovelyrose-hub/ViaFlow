<div class="register-section">
    <div class="register-section-title">1. Housing Rights</div>
    <p class="register-section-copy">Tell us whether you have housing rights documents, such as a Pag-IBIG housing loan contract.</p>
    <div class="register-choice-grid @error('housing_rights_documented') register-group-error @enderror">
        <div class="col-md-6">
            <label class="d-block" for="housing_rights_documented_yes">
                <input class="form-check-input register-choice-input" id="housing_rights_documented_yes" type="radio" name="housing_rights_documented" value="yes" required @checked(old('housing_rights_documented') === 'yes')>
                    <span class="register-choice-card d-block">Yes — I have the documents</span>
            </label>
        </div>
        <div class="col-md-6">
            <label class="d-block" for="housing_rights_documented_no">
                <input class="form-check-input register-choice-input" id="housing_rights_documented_no" type="radio" name="housing_rights_documented" value="no" @checked(old('housing_rights_documented') === 'no')>
                    <span class="register-choice-card d-block">No — I do not have them</span>
            </label>
        </div>
    </div>
</div>

<div class="register-section">
    <div class="register-section-title">2. Residency</div>
    <p class="register-section-copy">These questions help the admin confirm your connection to the community and the local association.</p>
    <div class="register-question-grid">
        <div class="col-md-6 register-question">
            <label class="form-label fw-semibold register-question-label">Are you a resident of the community?</label>
            <div class="register-choice-pair @error('resident_of_community') register-group-error @enderror">
                <label class="d-block" for="resident_of_community_yes">
                    <input class="form-check-input register-choice-input" id="resident_of_community_yes" type="radio" name="resident_of_community" value="yes" required @checked(old('resident_of_community') === 'yes')>
                    <span class="register-choice-card d-block">Yes</span>
                </label>
                <label class="d-block" for="resident_of_community_no">
                    <input class="form-check-input register-choice-input" id="resident_of_community_no" type="radio" name="resident_of_community" value="no" @checked(old('resident_of_community') === 'no')>
                    <span class="register-choice-card d-block">No</span>
                </label>
            </div>
        </div>
        <div class="col-md-6 register-question">
            <label class="form-label fw-semibold register-question-label">Are you part of the local association?</label>
            <div class="register-choice-pair @error('association_member') register-group-error @enderror">
                <label class="d-block" for="association_member_yes">
                    <input class="form-check-input register-choice-input" id="association_member_yes" type="radio" name="association_member" value="yes" @checked(old('association_member', $declaredMember ? 'yes' : 'no') === 'yes') required>
                    <span class="register-choice-card d-block">Yes</span>
                </label>
                <label class="d-block" for="association_member_no">
                    <input class="form-check-input register-choice-input" id="association_member_no" type="radio" name="association_member" value="no" @checked(old('association_member', $declaredMember ? 'yes' : 'no') === 'no')>
                    <span class="register-choice-card d-block">No</span>
                </label>
            </div>
            @error('association_member')
                <div class="text-danger small mt-2">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <p class="register-question-helper">The association answer is pre-selected from Step 1; change it if needed.</p>
</div>

<div class="register-section">
    <div class="register-section-title">3. Registration</div>
    <p class="register-section-copy">Confirm whether you are already officially registered in the association.</p>
    <div class="register-choice-grid @error('association_registered') register-group-error @enderror">
        <div class="col-md-6">
            <label class="d-block" for="association_registered_yes">
                <input class="form-check-input register-choice-input" id="association_registered_yes" type="radio" name="association_registered" value="yes" required @checked(old('association_registered') === 'yes')>
                    <span class="register-choice-card d-block">Yes — officially registered</span>
            </label>
        </div>
        <div class="col-md-6">
            <label class="d-block" for="association_registered_no">
                <input class="form-check-input register-choice-input" id="association_registered_no" type="radio" name="association_registered" value="no" @checked(old('association_registered') === 'no')>
                    <span class="register-choice-card d-block">No — not registered</span>
            </label>
        </div>
    </div>
</div>
